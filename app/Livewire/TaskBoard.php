<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\AgendaEntry;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskGroup;
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

    /** The just-created group whose inline name field is open, if any. */
    public ?int $namingGroupId = null;

    public string $groupNameDraft = '';

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
            // A grouped task lives inside its group's box, not loose in the
            // column — unless it's flagged important or for today. Those two are
            // explicit "this one matters now" signals and outrank the bundling,
            // so they show up as ordinary cards (see CLAUDE.md §7 Task-Gruppen).
            ->where(function ($q) {
                $q->whereNull('group_id')
                    ->orWhere('is_important', true)
                    ->orWhere('is_today', true);
            })
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
     * The user's task groups with their working set — one query for the cards,
     * one for the completed counts, regardless of how many groups there are.
     *
     * @return Collection<int, TaskGroup>
     */
    #[Computed]
    public function taskGroups(): Collection
    {
        return TaskGroup::query()
            ->forUser(auth()->user())
            ->ordered()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('activeTasks')
            ->get();
    }

    /**
     * The group boxes to render inside one board column.
     *
     * A group appears in To-Dos and in Tasks whenever it has open work there,
     * previewing its next two entries. A group with no open board work at all
     * (everything still in its own inbox, or everything done) would otherwise
     * be invisible on the board — so it gets one compact box in the Tasks
     * column instead, which is the difference between "tucked away" and "lost".
     *
     * @return Collection<int, array{group: TaskGroup, preview: Collection<int, Task>, more: int, done: int, total: int, inbox: int, compact: bool}>
     */
    public function groupBoxesFor(string $list): Collection
    {
        // The Inbox column never shows group boxes: a group's own inbox is
        // triage that belongs inside the group, and mixing it into the board's
        // inbox would put two different kinds of "unsorted" in one pile.
        if ($list === 'inbox') {
            return collect();
        }

        return $this->taskGroups
            ->map(function (TaskGroup $group) use ($list) {
                $active = $group->activeTasks;
                $inList = $active->where('list', $list)->values();
                $hasBoardWork = $active->whereIn('list', ['todos', 'tasks'])->isNotEmpty();

                // The compact fallback lives in Tasks and only for groups that
                // have nothing to show in either working column.
                $compact = ! $hasBoardWork;

                if ($compact && $list !== 'tasks') {
                    return null;
                }

                if (! $compact && $inList->isEmpty()) {
                    return null;
                }

                // Important / today tasks already render as ordinary cards in
                // this column, so previewing them again would just duplicate.
                $previewable = $inList->where('is_important', false)->where('is_today', false)->values();

                return [
                    'group' => $group,
                    'preview' => $previewable->take(2),
                    'more' => max($inList->count() - $previewable->take(2)->count(), 0),
                    'done' => (int) $group->done_count,
                    'total' => $active->count() + (int) $group->done_count,
                    'inbox' => $active->where('list', 'inbox')->count(),
                    'compact' => $compact,
                ];
            })
            ->filter()
            ->values();
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

    /**
     * Homework entries already pulled into today's focus (an active task with
     * a matching agenda_entry_id exists) — lets the preview strip mark a card
     * "already in Today" instead of inviting a duplicate drag/swipe. One
     * query, read alongside homeworkPreview() rather than per-card.
     */
    #[Computed]
    public function promotedHomeworkEntryIds(): array
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->whereNotNull('agenda_entry_id')
            ->pluck('agenda_entry_id')
            ->all();
    }

    /**
     * Marks a previewed homework entry done for this person — it then drops
     * out of the preview on its own. If it's already been dragged/swiped
     * into today's focus, routes through toggleComplete() on the linked
     * task instead of touching the entry a second time, so the two stay in
     * sync (completion side effects like celebrations included) no matter
     * which checkbox you actually tap — without this, ticking the entry
     * done here while its promoted task sits open elsewhere on the board
     * would leave a stale, seemingly-forgotten task behind.
     */
    public function toggleHomeworkPreviewDone(int $id): void
    {
        $user = auth()->user();
        $entry = AgendaEntry::visibleTo($user)->findOrFail($id);

        $task = Task::query()
            ->forUser($user)
            ->where('agenda_entry_id', $entry->id)
            ->orderBy('is_completed') // active (not yet done) first
            ->orderByDesc('updated_at')
            ->first();

        if ($task) {
            $this->toggleComplete($task->id);

            return;
        }

        $entry->toggleDoneFor($user);
    }

    /**
     * Drag (desktop) or swipe-up (mobile) a homework preview card into
     * today's focus. Reuses an already-active linked task if one exists
     * (dragging the same homework twice just re-confirms it, never
     * duplicates); otherwise spins up a new task carrying the homework's
     * title, subject, due date and note along with it, so acting on it
     * doesn't mean re-typing what it already says in the Agenda.
     *
     * $list is either 'todos'/'tasks' (a real desktop drop zone) or the
     * mobile swipe's fixed 'today' sentinel — anything that isn't a genuine
     * Today list falls back to 'tasks', the closer-sized default for a piece
     * of schoolwork.
     */
    public function promoteHomeworkToday(int $agendaEntryId, string $list): void
    {
        $user = auth()->user();
        $entry = AgendaEntry::query()->visibleTo($user)->findOrFail($agendaEntryId);
        $targetList = in_array($list, Task::TODAY_LISTS, true) ? $list : 'tasks';

        $existing = Task::query()
            ->forUser($user)
            ->active()
            ->where('agenda_entry_id', $entry->id)
            ->first();

        if ($existing) {
            $existing->update([
                'list' => $targetList,
                'is_today' => true,
                'today_date' => $existing->todayDateFor(true, $user->localToday()),
            ]);

            return;
        }

        $user->tasks()->create([
            'title' => "{$entry->subject}: {$entry->title}",
            'list' => $targetList,
            'is_today' => true,
            'today_date' => $user->localToday(),
            'deadline' => $entry->date,
            'notes' => $this->agendaNoteForTask($entry->notes),
            'agenda_entry_id' => $entry->id,
            'sort_order' => 0,
        ]);
    }

    /**
     * The reverse of promoteHomeworkToday(): drag a homework-derived task
     * card back onto the "Bald fällige Hausaufgaben" strip to undo the
     * promotion. Deletes the task — the Agenda entry itself is never
     * touched, so it simply becomes open and re-promotable again, exactly
     * as if it had never been dragged in. A no-op for an ordinary task
     * (guards against the destination accepting anything that isn't
     * actually agenda-linked, in case the client-side gate is ever wrong).
     */
    public function removeHomeworkFromToday(int $id): void
    {
        $task = $this->userTask($id);

        if ($task->agenda_entry_id === null) {
            return;
        }

        $this->deleteTask($task->id);
    }

    /**
     * Agenda notes are plain text (see AgendaEntry::notesPreview()); Task
     * notes are Markdown source rendered on the card/edit sheet. Escaping
     * just a leading list/heading marker stops a note like "- Seite 12"
     * from silently turning into an actual bullet once it's living on a
     * task, without mangling the far more common case of ordinary prose.
     */
    private function agendaNoteForTask(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        return preg_replace('/^([-*+#]|\d+[.)])(\s)/', '\\\\$1$2', $notes);
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
        // Grouped tasks count towards the column they surface in, whether they
        // show as an ordinary card or inside their group's box.
        $grouped = fn (string $list) => $this->taskGroups
            ->sum(fn (TaskGroup $g) => $g->activeTasks
                ->where('list', $list)
                ->where('is_important', false)
                ->where('is_today', false)
                ->count());

        return [
            'inbox' => $this->inbox->where('is_completed', false)->count() + $this->emergencyTasksFor('inbox')->count(),
            'todos' => $this->todosAll->where('is_completed', false)->count() + $this->emergencyTasksFor('todos')->count() + $grouped('todos'),
            'tasks' => $this->tasksAll->where('is_completed', false)->count() + $this->emergencyTasksFor('tasks')->count() + $grouped('tasks'),
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
        $oldGroup = $task->group; // a task belongs to a project or a group, never both

        $task->update([
            'project_id' => $project->id,
            'group_id' => null,
            'list' => 'projects',
            'is_today' => false,
            'today_date' => null,
        ]);

        $oldGroup?->pruneIfTooSmall();
    }

    /**
     * Desktop drag & drop: one task card held over another for ~350 ms and
     * dropped there (see boardSortable's grouping arm in app.js). Bundles both
     * into a fresh group and opens its inline name field — the group exists
     * immediately, naming it is the next, optional step, so the gesture never
     * blocks on a dialog.
     *
     * The dragged task adopts the target's list: that's the column the user
     * dropped it in, and anything else would silently move the card back.
     */
    public function groupTasks(int $taskId, int $targetTaskId): void
    {
        if ($taskId === $targetTaskId) {
            return;
        }

        $task = $this->userTask($taskId);
        $target = $this->userTask($targetTaskId);

        // A task belongs to a project or to a group, never both.
        if ($task->isInProject() || $target->isInProject()) {
            return;
        }

        // Captured before the merge — the dragged task may already have
        // belonged to a different group, which this join then leaves behind.
        $oldGroup = $task->group;

        // Dropped onto a card that is already grouped? Then this is simply
        // "add to that group" — no reason to make the user undo one first.
        $group = $target->group ?? auth()->user()->taskGroups()->create([
            'name' => TaskGroup::DEFAULT_NAME,
            'sort_order' => 0,
        ]);

        $target->update(['group_id' => $group->id]);
        $task->update(['group_id' => $group->id, 'list' => $target->list]);

        if ($oldGroup !== null && $oldGroup->id !== $group->id) {
            $oldGroup->pruneIfTooSmall();
        }

        // Only a brand-new group opens its name field; adding to an existing
        // one shouldn't ask for a name it already has.
        if ($group->wasRecentlyCreated) {
            $this->namingGroupId = $group->id;
            $this->groupNameDraft = '';
        }
    }

    /** Drop onto an existing group box — the task keeps its list, so an inbox task lands in the group's inbox. */
    public function assignTaskToGroup(int $taskId, int $groupId): void
    {
        $task = $this->userTask($taskId);
        $group = auth()->user()->taskGroups()->findOrFail($groupId);

        if ($task->isInProject()) {
            return;
        }

        $oldGroup = $task->group;

        $task->update(['group_id' => $group->id]);

        if ($oldGroup !== null && $oldGroup->id !== $group->id) {
            $oldGroup->pruneIfTooSmall();
        }
    }

    /**
     * Desktop drag & drop: a card dragged out of its group's box and dropped
     * onto a plain board column (see groupDropZone's onEnd in app.js, which
     * also calls reorder() for the new position). Releases the task and, if
     * that leaves the group with one task or none, dissolves it — a bundle of
     * one is not a group.
     */
    public function ungroupTask(int $taskId): void
    {
        $task = $this->userTask($taskId);
        $group = $task->group;

        if ($group === null) {
            return;
        }

        $task->update(['group_id' => null]);
        $group->pruneIfTooSmall();
    }

    /** Save the inline name of a freshly created group. Empty simply keeps the default. */
    public function saveGroupName(): void
    {
        if ($this->namingGroupId === null) {
            return;
        }

        $this->groupNameDraft = trim($this->groupNameDraft);

        $this->validate(
            ['groupNameDraft' => ['nullable', 'string', 'max:255']],
            ['groupNameDraft.max' => 'Höchstens 255 Zeichen.'],
        );

        if ($this->groupNameDraft !== '') {
            auth()->user()->taskGroups()->find($this->namingGroupId)
                ?->update(['name' => $this->groupNameDraft]);
        }

        $this->stopNamingGroup();
    }

    public function stopNamingGroup(): void
    {
        $this->reset(['namingGroupId', 'groupNameDraft']);
    }

    /**
     * Long-press "Neues Projekt" entry: creates a project named after the task,
     * then deletes the task — it's replaced by the project, not duplicated.
     */
    public function createProjectFromTask(int $taskId): void
    {
        $task = $this->userTask($taskId);
        $group = $task->group;

        auth()->user()->projects()->create([
            'name' => $task->title,
            'sort_order' => 0,
        ]);

        $task->delete();
        $group?->pruneIfTooSmall();
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
