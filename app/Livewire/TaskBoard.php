<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\TaskSuggestor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskBoard extends Component
{
    use ManagesTasks;

    /** Quick-add. */
    public string $newTitle = '';

    public string $newList = 'inbox';

    public ?string $newDeadline = null;

    public ?string $newDueDate = null;

    /** Add-project (Projects column / tab). */
    public string $newProjectName = '';

    public ?string $newProjectDeadline = null;

    /** Active mobile page: inbox | todos | tasks | today | projects. */
    public string $mobileTab = 'inbox';

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
        $today = Carbon::today();

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
     * minutes, or already has a timer running on it — the trigger that swaps
     * the header strip for the focus card/ring. A running timer keeps its
     * event as the focus session even past the block's own scheduled window,
     * so the ring never disappears mid-cycle.
     */
    #[Computed]
    public function focusSession(): ?ScheduleEvent
    {
        $now = now();
        $nowMin = $now->hour * 60 + $now->minute;

        return $this->scheduleToday->first(function (ScheduleEvent $e) use ($now, $nowMin) {
            if (! $e->category?->pomodoro_enabled) {
                return false;
            }

            if ($e->pomodoro_started_at !== null) {
                return true;
            }

            $untilStart = $e->startMinutes() - $nowMin;

            return $e->isActive($now) || ($untilStart >= 0 && $untilStart <= 5);
        });
    }

    /** The focus session's current Pomodoro phase, or null if not started yet. */
    #[Computed]
    public function focusPhase(): ?array
    {
        return $this->focusSession?->pomodoroPhaseNow(now(), auth()->user()->pomodoro());
    }

    /**
     * "What to work on" for the focus session — null once it's not the focus
     * session, or during a break. Before the timer is started ("Bereit") this
     * previews cycle 1's suggestion, since that's the session about to begin.
     */
    #[Computed]
    public function taskSuggestion(): ?array
    {
        $session = $this->focusSession;

        if ($session === null) {
            return null;
        }

        $phase = $this->focusPhase;

        if ($phase !== null && $phase['phase'] !== 'work') {
            return null;
        }

        return TaskSuggestor::suggest(auth()->user(), $phase['cycle'] ?? 1, $session->id);
    }

    /** Active-task counts only — completed tasks don't inflate the badges. */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox'    => $this->inbox->where('is_completed', false)->count(),
            'todos'    => $this->todosAll->where('is_completed', false)->count(),
            'tasks'    => $this->tasksAll->where('is_completed', false)->count(),
            'today'    => $this->today->count(),
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

        // Quick-add on a board-list tab targets that list. Today & Projects
        // don't feed the task quick-add, so leave the target untouched.
        if (in_array($tab, Task::BOARD_LISTS, true)) {
            $this->newList = $tab;
        }
    }

    public function addTask(): void
    {
        // Trim first so a whitespace-only title fails the required rule.
        $this->newTitle = trim($this->newTitle);

        $data = $this->validate([
            'newTitle'    => ['required', 'string', 'max:255'],
            'newList'     => ['required', 'in:inbox,todos,tasks'],
            'newDeadline' => ['nullable', 'date'],
            'newDueDate'  => ['nullable', 'date'],
        ]);

        auth()->user()->tasks()->create([
            'title'    => $data['newTitle'],
            'list'     => $data['newList'],
            'deadline' => $data['newDeadline'] ?: null,
            'due_date' => $data['newDueDate'] ?: null,
            'sort_order' => 0,
        ]);

        $this->reset(['newTitle', 'newDeadline', 'newDueDate']);
        $this->dispatch('task-added');
    }

    public function addProject(): void
    {
        $this->newProjectName = trim($this->newProjectName);

        $data = $this->validate([
            'newProjectName'     => ['required', 'string', 'max:255'],
            'newProjectDeadline' => ['nullable', 'date'],
        ]);

        auth()->user()->projects()->create([
            'name'     => $data['newProjectName'],
            'deadline' => $data['newProjectDeadline'] ?: null,
            'sort_order' => 0,
        ]);

        $this->reset(['newProjectName', 'newProjectDeadline']);
        $this->dispatch('project-added');
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
        ]);
    }

    /** Set/clear the Today focus. Inbox & project tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox() || $task->isInProject()) {
            return;
        }

        $task->update(['is_today' => $value]);
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
        if (! in_array($list, [... Task::BOARD_LISTS, 'projects'], true)) {
            return;
        }

        // Inbox and project list have no Today area.
        $today = in_array($list, ['inbox', 'projects'], true) ? false : $today;

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $updates = [
                'list'       => $list,
                'is_today'   => $today,
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
            'today' => $task->isInbox() ? null : $task->update(['is_today' => true]),
            'untoday' => $task->isInbox() ? null : $task->update(['is_today' => false]),
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
     */
    public function startFocusTimer(int $id): void
    {
        $event = $this->userScheduleEvent($id);

        if (! $event->category?->pomodoro_enabled) {
            return;
        }

        $event->update(['pomodoro_started_at' => now()]);
    }

    public function stopFocusTimer(int $id): void
    {
        $this->userScheduleEvent($id)->update(['pomodoro_started_at' => null]);
    }

    public function render()
    {
        return view('livewire.task-board');
    }
}
