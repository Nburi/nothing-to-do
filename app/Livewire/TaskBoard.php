<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\AppModules;
use App\Services\ListConcepts;
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

    /** Active mobile page: inbox | todos | tasks | today. */
    public string $mobileTab = 'inbox';

    /**
     * "Eisenhower" concept's "Krisenring" signature moment — ids already
     * seen in the "Wichtig & Dringend" quadrant on some earlier render this
     * page life (see trackEisenhowerCrisisEntries(), render(), and
     * .eisenhower-crisis-ring in app.css). Public so Livewire persists it
     * across requests; seeded once in mount() rather than lazily, since a
     * private "have I seeded yet" flag would itself reset to its default on
     * every request (only public properties survive between them) and would
     * make every single request look like the first one.
     *
     * @var list<int>
     */
    public array $eisenhowerCrisisSeenIds = [];

    /**
     * The "Heute" header badge (HeaderBadges::todayBadge()) links here with
     * ?tab=today so its click behaves like an actual shortcut on mobile, not
     * just a generic link to the board — desktop has no separate Today view
     * to jump to (Heute-flagged tasks already surface pinned inside their
     * own column), so the query param is simply inert there.
     */
    public function mount(): void
    {
        $tab = request()->query('tab');

        if (in_array($tab, ['inbox', 'todos', 'tasks', 'today'], true)) {
            $this->mobileTab = $tab;
        }

        // The Zeitplan's "tap the linked-task icon twice" gesture (schedule-event.blade.php)
        // lands here — best-effort only: a stale/foreign/deleted id just opens nothing,
        // never a broken page load, same pattern as Schedule::mount()'s own ?event=.
        $taskId = request()->query('task');

        if ($taskId !== null && ctype_digit((string) $taskId)) {
            $task = auth()->user()->tasks()->find((int) $taskId);

            if ($task !== null) {
                $this->startEdit($task->id);
            }
        }

        if (ListConcepts::for(auth()->user()) === 'eisenhower') {
            // Whatever's already in the crisis quadrant the moment the page
            // first loads is the starting state, not something that just
            // "arrived" — seeding here (mount() runs once per page load,
            // never on a later action request) is what keeps the very first
            // render from pulsing on every single task already sitting there.
            $this->eisenhowerCrisisSeenIds = $this->eisenhowerQuadrants['important_urgent']->pluck('id')->all();
        }
    }

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
     * Which mental model the board renders through — the one seam the view
     * branches on (see task-board.blade.php's `@switch`). Self-healing via
     * ListConcepts::for(): a stored value for a concept that isn't available
     * (or never was a real key) quietly falls back to 'three_things' instead
     * of rendering nothing.
     */
    #[Computed]
    public function listConcept(): string
    {
        return ListConcepts::for(auth()->user());
    }

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
            ->inList($list)
            ->with('dayPlan')
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
            ->where('is_today', true)
            ->with('dayPlan')
            ->boardOrdered()
            ->get();
    }

    /**
     * "Simple" concept's one flat list: every active on-board task across
     * every `list` value (Inbox/To-Dos/Tasks), ignoring
     * `list` for display entirely — see ListConcepts/PLAN_LIST_CONCEPTS.md §3.
     * Identical filter shape to boardTasks() (recently-completed visibility
     * window) minus the `inList()` call,
     * so nothing about which tasks are visible changes, only that every list
     * is merged into one instead of three.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function simpleTasks(): Collection
    {
        $windowStart = auth()->user()->completedWindowStart();

        return Task::query()
            ->forUser(auth()->user())
            ->with('dayPlan')
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')
            ->boardOrdered()
            ->get();
    }

    /**
     * "Eisenhower" concept's four quadrants — Wichtig×Dringend / Wichtig×Nicht
     * Dringend / Nicht Wichtig×Dringend / Nicht Wichtig×Nicht Dringend — built
     * from the SAME two signals every other concept already reads:
     * `is_important` and `Task::isUrgent()` (deadline/due-date within
     * Task::URGENCY_DAYS). No new axis data at all — see ListConcepts and
     * PLAN_LIST_CONCEPTS.md §1/§3.
     *
     * Fetches once with the identical filter shape as boardTasks() (same
     * completed-visibility window) minus the `inList()` call, then buckets the
     * ACTIVE tasks into the four quadrants in PHP — is_important/isUrgent()
     * are plain flags/methods, not worth four separate queries.
     * boardOrdered()'s own tie-breakers (due-soonest, then manual order)
     * already give a sensible in-quadrant order for free, since every task
     * in one quadrant already agrees on the coarse bucket that sort
     * partitions by. Completed tasks (same recently-completed window as
     * every other concept) come back separately under 'done' — completion
     * ends a task's relationship with either axis, so it gets one shared
     * strip below the grid instead of living in a quadrant it may no longer
     * even qualify for.
     *
     * @return array{important_urgent: Collection<int, Task>, important_not_urgent: Collection<int, Task>, not_important_urgent: Collection<int, Task>, not_important_not_urgent: Collection<int, Task>, done: Collection<int, Task>}
     */
    #[Computed]
    public function eisenhowerQuadrants(): array
    {
        $windowStart = auth()->user()->completedWindowStart();

        $tasks = Task::query()
            ->forUser(auth()->user())
            ->with('dayPlan')
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')
            ->boardOrdered()
            ->get();

        $active = $tasks->where('is_completed', false);

        $bucket = fn (bool $important, bool $urgent) => $active
            ->filter(fn (Task $t) => $t->is_important === $important && $t->isUrgent() === $urgent)
            ->values();

        return [
            'important_urgent' => $bucket(true, true),
            'important_not_urgent' => $bucket(true, false),
            'not_important_urgent' => $bucket(false, true),
            'not_important_not_urgent' => $bucket(false, false),
            'done' => $tasks->where('is_completed', true)->values(),
        ];
    }

    /**
     * "Kanban" concept's three columns — Backlog / In Arbeit / Erledigt —
     * built from the SAME two signals every other write path already uses:
     * an active, not-today task is Backlog; an active, today-flagged task is
     * In Arbeit; a completed task (within the board's existing recently-
     * completed visibility window, same as every other concept) is Erledigt.
     * No new axis data at all — see ListConcepts and PLAN_LIST_CONCEPTS.md §1/§3.
     *
     * Identical filter shape to boardTasks()/eisenhowerQuadrants() (same
     * completed-visibility window) minus the `inList()` call, then bucketed in PHP —
     * is_completed/is_today are plain flags, not worth three separate queries.
     * boardOrdered()'s own tie-breakers already give a sensible in-column
     * order for free, preserved by the bucketing (Collection filters keep
     * relative order).
     *
     * @return array{backlog: Collection<int, Task>, in_progress: Collection<int, Task>, done: Collection<int, Task>}
     */
    #[Computed]
    public function kanbanColumns(): array
    {
        $windowStart = auth()->user()->completedWindowStart();

        $tasks = Task::query()
            ->forUser(auth()->user())
            ->with('dayPlan')
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')
            ->boardOrdered()
            ->get();

        return [
            'backlog' => $tasks->where('is_completed', false)->where('is_today', false)->values(),
            'in_progress' => $tasks->where('is_completed', false)->where('is_today', true)->values(),
            'done' => $tasks->where('is_completed', true)->values(),
        ];
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
            return TaskSuggestor::suggest(auth()->user(), 1, $session->id, $session->category, $session->nextLinkedTask());
        }

        // While frozen between phases, judge relevance by what's coming next.
        $effectivePhase = $phase['awaiting_next'] ? $phase['next_phase'] : $phase['phase'];
        $effectiveCycle = $phase['awaiting_next'] ? $phase['next_cycle'] : $phase['cycle'];

        if ($effectivePhase !== 'work') {
            return null;
        }

        return TaskSuggestor::suggest(auth()->user(), $effectiveCycle, $session->id, $session->category, $session->nextLinkedTask());
    }

    /**
     * Signature moment: a quiet, self-dismissing notice the moment a
     * linked task/list is cleared out during a session that has actually
     * been started (Läuft or frozen awaiting a continue — never the
     * never-started "Bereit" state, since nothing "just finished" there).
     * Stays null forever for a 'text' link or once dismissLinkedSourceNotice()
     * has stamped this session as already notified. The event's own task
     * link (more specific, see TaskSuggestor) is checked before the
     * category's — once it exists it's authoritative for this occurrence.
     */
    #[Computed]
    public function linkedSourceNotice(): ?string
    {
        $session = $this->focusSession;

        if ($session === null || $session->pomodoro_phase === null || $session->pomodoro_linked_notified) {
            return null;
        }

        if ($session->linkedTasks()->exists()) {
            return $session->linkedTasksRemainingCount() === 0 ? 'Die gebundenen Aufgaben sind fertig.' : null;
        }

        $category = $session->category;

        if ($category === null) {
            return null;
        }

        if (TaskSuggestor::linkedSourceRemainingCount($category, auth()->user()) !== 0) {
            return null;
        }

        return $category->taskSourceFinishedMessage();
    }

    /** Auto-called by the focus card a few seconds after the notice above appears, so it shows once per session. */
    public function dismissLinkedSourceNotice(): void
    {
        $this->focusSession?->update(['pomodoro_linked_notified' => true]);
    }

    /** Active-task counts only — completed tasks don't inflate the badges. */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox' => $this->inbox->where('is_completed', false)->count(),
            'todos' => $this->todosAll->where('is_completed', false)->count(),
            'tasks' => $this->tasksAll->where('is_completed', false)->count(),
            'today' => $this->today->count(),
        ];
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'today'], true)) {
            return;
        }

        $this->mobileTab = $tab;
    }

    /** Set/clear the Today focus. Inbox tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox()) {
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
        if (! in_array($list, Task::BOARD_LISTS, true)) {
            return;
        }

        // Inbox has no Today area.
        $today = $list === 'inbox' ? false : $today;
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

    // ── "Simple" concept — flat-list drag & Today zone ─────────────────

    /**
     * Simple's flat-list drag reorder — now zone-based, mirroring reorder()'s
     * own shape: `$today` is which zone the drop landed in (see
     * partials/board-simple.blade.php's Heute box vs. the rest of the list),
     * and every id in that zone gets `is_today`/`today_date` set to match,
     * exactly like a 3-Things column's own Heute area. Unlike reorder(),
     * `list` itself is still never touched — Simple ignores `list` for
     * display entirely (see ListConcepts/PLAN_LIST_CONCEPTS.md §3), so
     * switching back to "3 Things" still shows every task under whichever
     * list it already carried — except the same isInbox() fix
     * setTodaySimple() already applies: entering Today from `list='inbox'`
     * still moves it to `'tasks'`, upholding the same invariant the rest of
     * the app relies on (no task has is_today=true while list='inbox').
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorderSimple(bool $today, array $ids): void
    {
        $targetDate = $today ? auth()->user()->localToday() : null;

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $updates = [
                'is_today' => $today,
                'today_date' => $task->todayDateFor($today, $targetDate),
                'sort_order' => $position,
            ];

            if ($today && $task->list === 'inbox') {
                $updates['list'] = 'tasks';
            }

            $task->update($updates);
        }
    }

    // ── "Eisenhower" concept — quadrant drag, Today toggle ─────────────

    /**
     * Eisenhower's cross- and within-quadrant drag. Unlike reorder(), the
     * destination is described by two flags — $important (row) and $urgent
     * (column) — instead of a `list`/`today` pair, since a quadrant is a
     * position on those two axes, not a board list.
     *
     * The importance axis is a plain, always-safe write: `is_important` is
     * already a directly user-owned flag everywhere else in this app (the
     * card's own title-tap toggles it identically), so setting it to match
     * the destination row is exactly what dropping a card there means.
     *
     * The urgency axis is NOT a stored flag — it's `Task::isUrgent()`,
     * derived from `deadline ?? due_date`, and `effectiveDate()` always
     * prefers a hard `deadline` over the soft `due_date` when both exist.
     * So this only ever touches `due_date`, never `deadline` — silently
     * rewriting a hard, externally-imposed deadline just because a card
     * landed in a different column would be exactly the kind of surprise
     * this app avoids everywhere else that distinction matters (CLAUDE.md's
     * deadline-vs-due_date convention). For a task with `isUrgencyLocked()`
     * (a real deadline), touching due_date could never move it anyway — the
     * client already refuses that drop up front (see the `put` predicate in
     * window.eisenhowerQuadrantSortable, board-eisenhower.blade.php's
     * data-urgency-locked), and the guard below is kept as a second,
     * server-side line of defence rather than trusted client-only.
     *
     * Making a task "dringend" sets due_date to the far edge of the urgency
     * window (today + Task::URGENCY_DAYS, still inside it) rather than
     * today — a self-imposed "due soon", not an invented same-day deadline.
     * Making it "nicht dringend" clears due_date outright — always safe,
     * since a soft date is freely reversible by design.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorderEisenhower(bool $important, bool $urgent, array $ids): void
    {
        $today = auth()->user()->localToday();

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue;
            }

            $updates = [
                'sort_order' => $position,
                'is_important' => $important,
            ];

            if (! $task->isUrgencyLocked()) {
                $currentlyUrgent = $task->isUrgent();

                if ($urgent && ! $currentlyUrgent) {
                    $updates['due_date'] = $today->copy()->addDays(Task::URGENCY_DAYS)->toDateString();
                } elseif (! $urgent && $currentlyUrgent) {
                    $updates['due_date'] = null;
                }
            }

            $task->update($updates);
        }
    }

    // ── "Kanban" concept — column drag, move pill, swipe ───────────────

    /** The three Kanban columns, in their fixed left-to-right order. */
    private const KANBAN_COLUMNS = ['backlog', 'in_progress', 'done'];

    /** Which Kanban column a task currently sits in, derived from is_completed/is_today — never stored. */
    private function kanbanColumnOf(Task $task): string
    {
        if ($task->is_completed) {
            return 'done';
        }

        return $task->is_today ? 'in_progress' : 'backlog';
    }

    /**
     * Writes a task's state to match a Kanban column — the single-task
     * primitive both setKanbanColumn() (the per-card move pill) and
     * reorderKanban() (desktop drag, below) build on.
     *
     * Reuses ManagesTasks::toggleComplete() verbatim for the is_completed
     * axis — celebration/streak side effects included, exactly
     * as the checkbox already on every card triggers — but only when the
     * task's current completion state doesn't already match the destination,
     * so reordering within an already-correct column (see reorderKanban())
     * never re-fires it. The is_today axis reuses Task::todayDateFor()
     * exactly like every other is_today write site in this app (see
     * CLAUDE.md's "today_date" audit-lesson entry) rather than writing it
     * inline. Deliberately leaves is_today untouched while landing in/
     * leaving Erledigt — completing a task doesn't touch is_today anywhere
     * else in this app either, so un-completing one (via the checkbox, the
     * only way back out of Erledigt — see window.kanbanColumnSortable in
     * app.js for why drag can't do it) correctly returns it to wherever it
     * was before, Backlog or In Arbeit.
     */
    private function applyKanbanColumn(Task $task, string $column): Task
    {
        $shouldBeCompleted = $column === 'done';

        if ($task->is_completed !== $shouldBeCompleted) {
            $this->toggleComplete($task->id);
            $task->refresh();
        }

        if (! $shouldBeCompleted) {
            $task->update([
                'is_today' => $column === 'in_progress',
                'today_date' => $task->todayDateFor($column === 'in_progress', auth()->user()->localToday()),
            ]);
        }

        return $task;
    }

    /**
     * Single-card column move. The desktop board no longer has a per-card
     * button for this (see board-kanban.blade.php — the Backlog ⇄ In Arbeit
     * pill was removed as pure redundancy once drag between the same two
     * columns already existed); this is now reached only by Kanban's mobile
     * "advance" swipe (swipeIntentKanban() below). The Erledigt transitions
     * never call this directly: the checkbox already on every card calls
     * ManagesTasks::toggleComplete() itself, which is exactly what this
     * method would do for that axis too.
     */
    public function setKanbanColumn(int $id, string $column): void
    {
        if (! in_array($column, self::KANBAN_COLUMNS, true)) {
            return;
        }

        $this->applyKanbanColumn($this->userTask($id), $column);
    }

    /**
     * Desktop drag & drop across all three columns — one shared Sortable
     * group (see window.kanbanColumnSortable), mirroring the "receive the
     * destination zone's full ordered id list" shape reorder() already
     * uses. Idempotent for a card that's already in the right column (only
     * its sort_order changes), so reordering within a column never re-fires
     * applyKanbanColumn()'s toggleComplete() call for anything but the one
     * card that actually moved.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorderKanban(string $column, array $ids): void
    {
        if (! in_array($column, self::KANBAN_COLUMNS, true)) {
            return;
        }

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $task = $this->applyKanbanColumn($task, $column);
            $task->update(['sort_order' => $position]);
        }
    }

    /**
     * Simple's single-task Today toggle — now only reached via the mobile
     * swipe path (swipeIntentSimple() below); the desktop board sets
     * is_today through reorderSimple() instead, by dropping into or out of
     * the Heute zone (see partials/board-simple.blade.php). Unlike
     * setToday()/swipeIntent(), never blocks on isInbox() — Simple has no
     * Inbox concept to exclude from Today. Turning one ON for a task that
     * still carries list='inbox' (left over from before switching concepts)
     * also moves it to 'tasks': not a display change under Simple (list is
     * invisible here), but it upholds an invariant the rest of the app
     * relies on — no task has is_today=true while list='inbox' (see
     * setToday()'s own isInbox() guard, and reorderSimple()'s matching
     * fix) — so switching back to "3 Things" never surfaces a
     * structurally-impossible Inbox+Heute combination, and the shared edit
     * sheet's own list picker (ManagesTasks::saveEdit(), which resets
     * is_today for anything outside Task::TODAY_LISTS) doesn't quietly
     * clear the flag the next time this task is edited.
     */
    public function setTodaySimple(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        $updates = [
            'is_today' => $value,
            'today_date' => $task->todayDateFor($value, auth()->user()->localToday()),
        ];

        if ($value && $task->isInbox()) {
            $updates['list'] = 'tasks';
        }

        $task->update($updates);
    }

    /**
     * Eisenhower's Today toggle — same isInbox()-fixup shape as every other
     * concept's own override (see 3-Things' plain setToday()/swipeIntent()):
     * is_today has no quadrant of its own to live in (the grid is already
     * spoken for by importance/urgency), so it surfaces as a plain badge on
     * the card face instead, exactly the way the data-mapping table
     * (PLAN_LIST_CONCEPTS.md §3) describes it for every concept but 3-Things.
     */
    public function setTodayEisenhower(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        $updates = [
            'is_today' => $value,
            'today_date' => $task->todayDateFor($value, auth()->user()->localToday()),
        ];

        if ($value && $task->isInbox()) {
            $updates['list'] = 'tasks';
        }

        $task->update($updates);
    }

    /**
     * Simple's mobile swipe outcomes — only ever 'today'/'untoday' reach
     * here ('edit' is handled client-side by swipeCard before any $wire call
     * is made). Routes 'today' through setTodaySimple() so the isInbox()
     * list-fix above applies to a swipe exactly like it does to the desktop
     * badge.
     */
    public function swipeIntentSimple(int $id, string $intent): void
    {
        match ($intent) {
            'today' => $this->setTodaySimple($id, true),
            'untoday' => $this->setTodaySimple($id, false),
            default => null,
        };
    }

    /** Eisenhower's mobile swipe outcomes — routes today/untoday through setTodayEisenhower(). */
    public function swipeIntentEisenhower(int $id, string $intent): void
    {
        match ($intent) {
            'today' => $this->setTodayEisenhower($id, true),
            'untoday' => $this->setTodayEisenhower($id, false),
            default => null,
        };
    }

    /**
     * Kanban's mobile swipe — right always means "move one column forward"
     * (Backlog → In Arbeit → Erledigt), resolved server-side from the
     * task's actual current state rather than baked into whichever tab the
     * swipe happened in, so it stays correct regardless. Left is 'edit',
     * handled entirely client-side by swipeCard before any $wire call — the
     * Erledigt tab's cards swipe nowhere at all (rightIntent is '' there,
     * see board-kanban.blade.php), so only 'advance' ever reaches here.
     */
    public function swipeIntentKanban(int $id, string $intent): void
    {
        if ($intent !== 'advance') {
            return;
        }

        $task = $this->userTask($id);
        $next = match ($this->kanbanColumnOf($task)) {
            'backlog' => 'in_progress',
            'in_progress' => 'done',
            default => null,
        };

        if ($next !== null) {
            $this->setKanbanColumn($id, $next);
        }
    }

    /**
     * Detects a task genuinely ENTERING the crisis quadrant since the last
     * render — by drag, by tapping the star, by setting/clearing a date, or
     * by a fresh quadrant-tap capture — regardless of which of those caused
     * it, since this simply diffs the quadrant's current membership against
     * what was there last time (see $eisenhowerCrisisSeenIds), rather than
     * hooking each individual mutation (several of which — toggleImportant,
     * quickSetDates — are shared code this concept must not modify).
     * Dispatches 'eisenhower-crisis' with the newly-arrived ids for app.js
     * to animate. Mirrors WeekPlan's own dispatch-driven ripple, deliberately
     * NOT an x-init/wire:key trick — a nested element's x-init does not
     * reliably re-fire on every Livewire morph, only a genuine node
     * replacement does (see CLAUDE.md §10), so a dispatched browser event is
     * the reliable way to fire a one-shot animation on every relevant
     * update, not just the first render.
     */
    private function trackEisenhowerCrisisEntries(Collection $crisisTasks): void
    {
        $currentIds = $crisisTasks->pluck('id')->all();
        $newIds = array_values(array_diff($currentIds, $this->eisenhowerCrisisSeenIds));
        $this->eisenhowerCrisisSeenIds = $currentIds;

        if ($newIds !== []) {
            $this->dispatch('eisenhower-crisis', ids: $newIds);
        }
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

        app(PomodoroSessionService::class)->start($event);
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
        app(PomodoroSessionService::class)->transition($event, $user->pomodoro());
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

        app(PomodoroSessionService::class)->skipBreak($event, $user->pomodoro());
    }

    public function render()
    {
        if ($this->listConcept === 'eisenhower') {
            $this->trackEisenhowerCrisisEntries($this->eisenhowerQuadrants['important_urgent']);
        }

        return view('livewire.task-board');
    }
}
