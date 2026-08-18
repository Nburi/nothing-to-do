<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\AgendaEntry;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\PomodoroSessionService;
use App\Services\TaskSuggestor;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskBoard extends Component
{
    use ManagesTasks;

    /** Active mobile page: inbox | todos | tasks | today | projects. */
    public string $mobileTab = 'inbox';

    /**
     * Capture happens in the app-wide QuickCapture panel now, which is a
     * separate component — so a new entry doesn't re-render this one on its
     * own. Listening for its event is what keeps the board in sync.
     */
    #[On('captured')]
    public function refreshBoard(): void
    {
        // No body needed: handling the event is itself a re-render, and every
        // read is a computed property that re-evaluates on the way out.
    }

    // ── Reads (computed, cached per request) ──────────────────────────

    /**
     * Tasks visible in a board column: active tasks + tasks completed within the
     * current visibility window (since the user's daily reset time). Active tasks
     * come first (orderBy is_completed ASC prepended before boardOrdered).
     *
     * @return Collection<int, Task>
     */
    private function boardTasks(string $list): Collection
    {
        $windowStart = auth()->user()->completedWindowStart();

        return Task::query()
            ->forUser(auth()->user())
            ->onBoard()
            ->inList($list)
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')   // active (0) before completed (1)
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function inbox(): Collection
    {
        return $this->boardTasks('inbox');
    }

    /** Whole-list collections (active + recently completed), fetched once and split below. */
    #[Computed]
    public function todosAll(): Collection
    {
        return $this->boardTasks('todos');
    }

    #[Computed]
    public function tasksAll(): Collection
    {
        return $this->boardTasks('tasks');
    }

    /** Active todos flagged for today's focus. */
    #[Computed]
    public function todosToday(): Collection
    {
        return $this->todosAll->where('is_completed', false)->where('is_today', true)->values();
    }

    /** Active todos not in today's focus (completed ones are passed separately to the column partial). */
    #[Computed]
    public function todosRest(): Collection
    {
        return $this->todosAll->where('is_completed', false)->where('is_today', false)->values();
    }

    #[Computed]
    public function tasksToday(): Collection
    {
        return $this->tasksAll->where('is_completed', false)->where('is_today', true)->values();
    }

    #[Computed]
    public function tasksRest(): Collection
    {
        return $this->tasksAll->where('is_completed', false)->where('is_today', false)->values();
    }

    /** Mobile "Today" page: every focused board task across todos + tasks. */
    #[Computed]
    public function today(): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->onBoard()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();
    }

    /**
     * Standalone tasks placed in the Projects column but not inside any project.
     * These are on-board tasks (project_id IS NULL) with list = 'projects'.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function projectTasks(): Collection
    {
        return $this->boardTasks('projects');
    }

    /** The project currently in "emergency mode", or null if not active. */
    #[Computed]
    public function emergencyProject(): ?Project
    {
        $id = auth()->user()->emergency_project_id;

        return $id ? auth()->user()->projects()->find($id) : null;
    }

    /**
     * All of the emergency project's active tasks, in the sequence order set
     * up on the arrange screen — sliced per board column below. Empty when
     * emergency mode isn't active.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function emergencyActiveTasks(): Collection
    {
        $project = $this->emergencyProject;

        if ($project === null) {
            return collect();
        }

        return $project->tasks()->active()->orderBy('sort_order')->orderBy('created_at')->get();
    }

    /** Emergency-project tasks tagged for one board column, in sequence order. */
    public function emergencyTasksFor(string $list): Collection
    {
        return $this->emergencyActiveTasks->where('emergency_list', $list)->values();
    }

    /** Progress + "what's next" for the dashboard banner — null when not active. */
    #[Computed]
    public function emergencyProgress(): ?array
    {
        $project = $this->emergencyProject;

        if ($project === null) {
            return null;
        }

        $active = $this->emergencyActiveTasks;
        $done = $project->tasks()->where('is_completed', true)->count();

        return [
            'done' => $done,
            'total' => $done + $active->count(),
            'next' => $active->first(),
        ];
    }

    /**
     * Projects with their working set: every active task (ordered) for the
     * card preview + open count, plus a completed count for the progress label.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->forUser(auth()->user())
            ->ordered()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('activeTasks')
            ->get();
    }

    /** Today's timeline events (recurring series materialised on read). */
    #[Computed]
    public function scheduleToday(): Collection
    {
        $today = auth()->user()->localToday();

        ScheduleEvent::materializeRange(auth()->user(), $today, $today->copy());

        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forDay($today)
            ->ordered()
            ->with('category')
            ->get();
    }

    /**
     * The Pomodoro-enabled category block that is running now, starts within 5
     * minutes, or already has a session going (running or frozen awaiting a
     * manual continue) — the trigger that swaps the header strip for the
     * focus card/ring. A started session keeps its event as the focus session
     * even past the block's own scheduled window, so the ring never
     * disappears mid-cycle.
     */
    #[Computed]
    public function focusSession(): ?ScheduleEvent
    {
        $now = auth()->user()->localNow();
        $nowMin = $now->hour * 60 + $now->minute;

        return $this->scheduleToday->first(function (ScheduleEvent $e) use ($now, $nowMin) {
            if (! $e->category?->pomodoro_enabled) {
                return false;
            }

            if ($e->pomodoro_phase !== null) {
                return true;
            }

            $untilStart = $e->startMinutes() - $nowMin;

            return $e->isActive($now) || ($untilStart >= 0 && $untilStart <= 5);
        });
    }

    /** The focus session's current Pomodoro state, or null if not started yet. */
    #[Computed]
    public function focusPhase(): ?array
    {
        return $this->focusSession?->pomodoroPhaseNow(now(), auth()->user()->pomodoro(), (bool) auth()->user()->pomodoro_autostart);
    }

    /**
     * "What to work on" for the focus session — null once it's not the focus
     * session, or during a break. Before the timer is started ("Bereit"), or
     * while frozen awaiting a continue into the next work session, this
     * previews the upcoming cycle's suggestion.
     */
    #[Computed]
    public function taskSuggestion(): ?array
    {
        $session = $this->focusSession;

        if ($session === null) {
            return null;
        }

        $phase = $this->focusPhase;

        if ($phase === null) {
            return TaskSuggestor::suggest(auth()->user(), 1, $session->id);
        }

        // While frozen between phases, judge relevance by what's coming next.
        $effectivePhase = $phase['awaiting_next'] ? $phase['next_phase'] : $phase['phase'];
        $effectiveCycle = $phase['awaiting_next'] ? $phase['next_cycle'] : $phase['cycle'];

        if ($effectivePhase !== 'work') {
            return null;
        }

        return TaskSuggestor::suggest(auth()->user(), $effectiveCycle, $session->id);
    }

    /**
     * The automatic-reminder in-app banner: only in "automatic" reminder
     * mode, only during the relevant half of the day for the user's
     * morning/evening setting, only if today's Vorbereitung isn't done yet,
     * and only if it hasn't already been dismissed today. "fixed" mode has no
     * in-app banner — it's push-only, exactly at the chosen time.
     */
    #[Computed]
    public function showPreparePrompt(): bool
    {
        $user = auth()->user();

        return $user->prepare_reminder_mode === 'automatic'
            && ! $user->hasPreparedToday()
            && $user->prepare_prompt_dismissed_on?->toDateString() !== $user->localToday()->toDateString()
            && $user->isWithinPrepareWindow();
    }

    /** Hides the banner for the rest of the user's local day — reappears tomorrow if still relevant. */
    public function dismissPreparePrompt(): void
    {
        auth()->user()->update(['prepare_prompt_dismissed_on' => auth()->user()->localToday()->toDateString()]);
    }

    /**
     * Open homework due within the next few weekdays — empty (not just hidden) when the setting
     * is off, so the partial's `isNotEmpty()` check covers both "off" and "nothing due" the same way.
     */
    #[Computed]
    public function homeworkPreview(): Collection
    {
        $user = auth()->user();

        if (! $user->homework_preview_enabled) {
            return collect();
        }

        return AgendaEntry::homeworkPreviewFor($user);
    }

    /** Marks a previewed homework entry done for this person — it then drops out of the preview on its own. */
    public function toggleHomeworkPreviewDone(int $id): void
    {
        AgendaEntry::visibleTo(auth()->user())->findOrFail($id)->toggleDoneFor(auth()->user());
    }

    /** Ends emergency mode — the project and its task order are left exactly as arranged. */
    public function endEmergencyMode(): void
    {
        auth()->user()->update(['emergency_project_id' => null]);
    }

    /**
     * Active-task counts only — completed tasks don't inflate the badges.
     * Includes the emergency project's tasks pinned into each column, since
     * those are genuinely visible there too.
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox' => $this->inbox->where('is_completed', false)->count() + $this->emergencyTasksFor('inbox')->count(),
            'todos' => $this->todosAll->where('is_completed', false)->count() + $this->emergencyTasksFor('todos')->count(),
            'tasks' => $this->tasksAll->where('is_completed', false)->count() + $this->emergencyTasksFor('tasks')->count(),
            'today' => $this->today->count(),
            'projects' => $this->projects->count() + $this->projectTasks->where('is_completed', false)->count(),
        ];
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'today', 'projects'], true)) {
            return;
        }

        $this->mobileTab = $tab;
    }

    /**
     * Desktop drag & drop: a board task card dropped onto a project card.
     * Moves the task into that project (and off the board / out of Today).
     */
    public function assignTaskToProject(int $taskId, int $projectId): void
    {
        $task = $this->userTask($taskId);
        $project = auth()->user()->projects()->findOrFail($projectId);

        $task->update([
            'project_id' => $project->id,
            'list' => 'projects',
            'is_today' => false,
            'today_date' => null,
        ]);
    }

    /**
     * Long-press "Neues Projekt" entry: creates a project named after the task,
     * then deletes the task — it's replaced by the project, not duplicated.
     */
    public function createProjectFromTask(int $taskId): void
    {
        $task = $this->userTask($taskId);

        auth()->user()->projects()->create([
            'name' => $task->title,
            'sort_order' => 0,
        ]);

        $task->delete();
    }

    /** Set/clear the Today focus. Inbox & project tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox() || $task->isInProject()) {
            return;
        }

        $task->update([
            'is_today' => $value,
            'today_date' => $task->todayDateFor($value, auth()->user()->localToday()),
        ]);
    }

    /**
     * Drag & drop persistence. Receives the full ordered list of task ids now
     * sitting in one zone (a column, or a column's Today area) and rewrites
     * their list / today / order to match. The source zone needs no update —
     * a moved task simply drops out of its old column's query.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(string $list, bool $today, array $ids): void
    {
        // Board columns + standalone project list are valid drag targets.
        if (! in_array($list, [...Task::BOARD_LISTS, 'projects'], true)) {
            return;
        }

        // Inbox and project list have no Today area.
        $today = in_array($list, ['inbox', 'projects'], true) ? false : $today;
        $targetDate = $today ? auth()->user()->localToday() : null;

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $updates = [
                'list' => $list,
                'is_today' => $today,
                'today_date' => $task->todayDateFor($today, $targetDate),
                'sort_order' => $position,
            ];

            // Moving into the standalone project list clears the project assignment.
            if ($list === 'projects') {
                $updates['project_id'] = null;
            }

            $task->update($updates);
        }
    }

    /** Mobile swipe outcomes. */
    public function swipeIntent(int $id, string $intent): void
    {
        $task = $this->userTask($id);

        match ($intent) {
            'todos' => $task->update(['list' => 'todos']),
            'tasks' => $task->update(['list' => 'tasks']),
            'today' => $task->isInbox() ? null : $task->update([
                'is_today' => true,
                'today_date' => $task->todayDateFor(true, auth()->user()->localToday()),
            ]),
            'untoday' => $task->isInbox() ? null : $task->update(['is_today' => false, 'today_date' => null]),
            default => null,
        };
    }

    protected function userScheduleEvent(int $id): ScheduleEvent
    {
        return auth()->user()->scheduleEvents()->findOrFail($id);
    }

    /**
     * Start the Pomodoro focus timer on a category block. A tap before the
     * block's scheduled start (inside the lead-in window) starts the cycle
     * now, not at the scheduled time — reaching the time never auto-starts it.
     * Always manual, regardless of the autostart setting — that only governs
     * transitions *after* this first session.
     */
    public function startFocusTimer(int $id): void
    {
        $event = $this->userScheduleEvent($id);

        if (! $event->category?->pomodoro_enabled) {
            return;
        }

        app(PomodoroSessionService::class)->start($event, auth()->user());
    }

    /** Fully ends the session — a fresh tap on "Start" is needed to begin again. */
    public function stopFocusTimer(int $id): void
    {
        app(PomodoroSessionService::class)->stop($this->userScheduleEvent($id));
    }

    /**
     * Called by the client's local countdown when it reaches zero. Re-checked
     * server-side before acting (never trust the client's clock blindly). With
     * autostart enabled this immediately continues into the next phase; with
     * it disabled, the clock freezes here — `continuePhase()` is needed to
     * move on. The same tick is applied by the per-minute scheduled command
     * regardless of whether any tab is open (see PomodoroSessionService::handleTick).
     */
    public function handlePhaseComplete(int $id): void
    {
        app(PomodoroSessionService::class)->handleTick($this->userScheduleEvent($id), auth()->user());
    }

    /** Manually start the next queued phase — the button shown while frozen awaiting a continue. */
    public function continuePhase(int $id): void
    {
        $event = $this->userScheduleEvent($id);

        if ($event->pomodoro_phase === null) {
            return;
        }

        $user = auth()->user();
        app(PomodoroSessionService::class)->transition($event, $user, $user->pomodoro());
    }

    /**
     * Skip the current or upcoming break entirely and jump straight into the
     * next work session. Works whether the break is actively running or
     * still frozen awaiting its own manual start.
     */
    public function skipBreak(int $id): void
    {
        $event = $this->userScheduleEvent($id);
        $user = auth()->user();

        app(PomodoroSessionService::class)->skipBreak($event, $user, $user->pomodoro());
    }

    public function render()
    {
        return view('livewire.task-board');
    }
}
