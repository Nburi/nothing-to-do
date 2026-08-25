<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Automatically assigns open Tasks/Todos/Agenda-homework into upcoming
 * Pomodoro-enabled work-blocks, so the user can see — well before a deadline
 * sneaks up on a day that turns out to be full of training or competitions —
 * whether everything will actually get done in time.
 *
 * Stateless, like TaskSuggestor/PomodoroCycle/ProgressStats. Every public
 * method no-ops when the feature is off (users.planner_enabled), so callers
 * (PomodoroSessionService, the Planner page) never need their own guard.
 *
 * Persistence reuses schedule_event_task_links (the same pivot
 * Zeitplan-Eintrag-Aufgaben-Verknüpfung already uses for manual binds) rather
 * than a parallel data model — WorkPlanner is just what writes 'auto' rows
 * into it instead of a human picking them. A 'manual' row is never touched by
 * reconcile(); only the explicit "Neu planen" action (regenerate()) discards
 * it, mirroring the user's own "take the steer into your hands" requirement.
 *
 * Homework has no Task row of its own, and schedule_event_task_links can only
 * point at a Task (its FK is to the tasks table) — so the moment a homework
 * item is actually placed into a block, it is promoted into a real Task
 * first (mirroring TaskBoard::promoteHomeworkToday()'s shape), and that Task
 * is what gets linked. From then on it behaves like any other task,
 * including the existing homework-provenance icon and two-way completion
 * sync (Task::syncLinkedAgendaEntry()).
 */
class WorkPlanner
{
    /** How far ahead the planner looks. Fixed for v1, not user-configurable. */
    public const HORIZON_DAYS = 14;

    /** A hard deadline is treated as due this many days earlier, for buffer. A soft Wunschtermin/homework-date-once-promoted is not. */
    public const DEADLINE_BUFFER_DAYS = 2;

    /** Fallback duration (minutes) used only for scoring/fitting when a task has no estimate of its own — never written back to the record. */
    public const DEFAULT_DURATION = ['todos' => 10, 'tasks' => 25, 'projects' => 25];

    /** Same reasoning as DEFAULT_DURATION — homework is typically task-sized, not todo-sized. */
    public const DEFAULT_HOMEWORK_DURATION = 25;

    public const URGENCY_WEIGHT = 1.0;

    public const FIT_WEIGHT = 0.5;

    public const IMPORTANCE_BONUS = 0.15;

    /**
     * Passive recompute: keeps every 'auto' placement fresh (drops ones that
     * are no longer valid, fills whatever's still open) while leaving every
     * 'manual' placement exactly where the user put it. Safe to call often —
     * cheap when there is nothing to do, and a full no-op while the feature
     * is disabled.
     */
    public static function reconcile(User $user): void
    {
        if (! $user->planner_enabled) {
            return;
        }

        self::plan($user, keepManual: true);
    }

    /**
     * "Neu planen" — a full, explicit replan. Unlike reconcile(), this also
     * discards manual placements: the one deliberate way to undo an
     * accidental manual move, exactly as asked for. The UI gates this behind
     * the armed double-click, since it's the one action here that can throw
     * away a user's own choice.
     */
    public static function regenerate(User $user): void
    {
        if (! $user->planner_enabled) {
            return;
        }

        self::plan($user, keepManual: false);
    }

    /**
     * Every dated Task/homework item that has no placement on or before its
     * own effective deadline — the "doesn't fit" list the Planer page always
     * shows. A manual placement counts the same as an auto one here: this is
     * purely "is it actually covered", not "did the algorithm do it".
     */
    public static function conflicts(User $user): Collection
    {
        if (! $user->planner_enabled) {
            return collect();
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS);

        return self::eligibleItems($user, $today, $horizonEnd)
            ->reject(fn (array $item) => self::hasTimelyPlacement($user, $item))
            ->values();
    }

    /**
     * Upcoming work-blocks (Pomodoro-enabled category occurrences) in the
     * horizon, with their linked tasks eager-loaded, grouped by day — the
     * Planer page's main list.
     */
    public static function upcomingBlocks(User $user): Collection
    {
        if (! $user->planner_enabled) {
            return collect();
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS);

        ScheduleEvent::materializeRange($user, $today, $horizonEnd);

        return self::workBlocksQuery($user, $today, $horizonEnd)
            ->with(['category', 'linkedTasks'])
            ->get()
            ->groupBy(fn (ScheduleEvent $e) => $e->date->toDateString());
    }

    // ── Core planning routine ──────────────────────────────────────────

    private static function plan(User $user, bool $keepManual): void
    {
        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS);

        ScheduleEvent::materializeRange($user, $today, $horizonEnd);

        $blocks = self::workBlocksQuery($user, $today, $horizonEnd)
            ->with('linkedTasks')
            ->get();

        if ($blocks->isEmpty()) {
            return;
        }

        // In-memory working state: blockId => ['event' => ScheduleEvent, 'capacity' => int, 'occupied' => [item, ...]].
        $state = [];
        $placedKeys = [];

        foreach ($blocks as $block) {
            $occupied = [];

            foreach ($block->linkedTasks as $task) {
                if ($keepManual && $task->pivot->source === 'manual') {
                    // A manually-pinned task might have no deadline/due_date at all
                    // (the user can bind anything by hand) — deadlineInfoForTask()
                    // returns null for that, same as any other undated item.
                    $occupied[] = [
                        'type' => 'task',
                        'id' => $task->id,
                        'title' => $task->title,
                        'duration' => self::durationForTask($task),
                        'deadline' => self::deadlineInfoForTask($task)['effective'] ?? null,
                        'is_important' => (bool) $task->is_important,
                        'source' => 'manual',
                        'sort_order' => (int) $task->pivot->sort_order,
                    ];
                    $placedKeys[] = 'task:'.$task->id;
                }
                // Everything else (every 'auto' row, and every 'manual' row when
                // !$keepManual) is deliberately dropped here — regenerated below.
            }

            $state[$block->id] = [
                'event' => $block,
                'capacity' => $block->durationMinutes(),
                'occupied' => $occupied,
            ];
        }

        $pool = self::eligibleItems($user, $today, $horizonEnd)
            ->concat(self::fillerItems($user))
            ->reject(fn (array $item) => in_array($item['type'].':'.$item['id'], $placedKeys, true))
            ->values();

        // Chronological greedy fill: each block takes the best-scoring candidate
        // that still fits, repeatedly, until full or nothing left fits.
        foreach ($state as $blockId => &$entry) {
            $pool = self::fillBlock($entry, $pool, $today);
        }
        unset($entry);

        // Bounded repair pass: try to rescue every still-unplaced *dated* item
        // (filler is never a conflict, so never worth rescuing) with one swap.
        foreach ($pool->filter(fn (array $i) => $i['deadline'] !== null) as $victim) {
            self::attemptRescue($victim, $state);
        }

        // Persist: each block's final occupied set replaces its current links.
        // Homework items are promoted to a real Task at this point, not earlier,
        // so a plan that gets rearranged during repair never creates a task it
        // then throws away.
        foreach ($state as $entry) {
            $sync = [];

            foreach ($entry['occupied'] as $row) {
                $taskId = $row['type'] === 'agenda'
                    ? self::resolveOrPromote($user, $row['id'])->id
                    : $row['id'];

                $sync[$taskId] = ['sort_order' => $row['sort_order'], 'source' => $row['source']];
            }

            $entry['event']->linkedTasks()->sync($sync);
        }
    }

    /**
     * Fills one block's remaining capacity from the pool, greedily, and
     * returns the pool with whatever got placed removed from it.
     *
     * @param  array{event: ScheduleEvent, capacity: int, occupied: array}  $entry
     */
    private static function fillBlock(array &$entry, Collection $pool, Carbon $today): Collection
    {
        $block = $entry['event'];
        $nextOrder = self::nextSortOrder($entry);

        while (true) {
            $usedMinutes = collect($entry['occupied'])->sum('duration');
            $remaining = $entry['capacity'] - $usedMinutes;

            if ($remaining <= 0 || $pool->isEmpty()) {
                break;
            }

            $bestIndex = null;
            $bestScore = -INF;

            foreach ($pool as $index => $item) {
                if ($item['duration'] > $remaining) {
                    continue;
                }

                // Never place something into a block sitting on or after its own
                // deadline — a filler item (no deadline) has no such constraint.
                if ($item['deadline'] !== null && $block->date->toDateString() > $item['deadline']->toDateString()) {
                    continue;
                }

                $score = self::score($item, $remaining, $today);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                }
            }

            if ($bestIndex === null) {
                break;
            }

            $chosen = $pool->get($bestIndex);
            $entry['occupied'][] = [...$chosen, 'source' => 'auto', 'sort_order' => $nextOrder++];
            $pool = $pool->reject(fn ($i, $idx) => $idx === $bestIndex)->values();
        }

        return $pool;
    }

    /**
     * urgency (0 at the edge of the horizon, 1.0 due today, beyond 1 the more
     * overdue) weighted highest, fit (how completely it uses the block's
     * remaining time) weighted half as much, plus a small flat bonus for a
     * starred task — never enough on its own to beat real urgency.
     *
     * Deliberately measured from `$today` (the planning anchor), never from
     * the block being filled — an item's urgency is a property of the item,
     * not of which block happens to be under consideration. Scoring it
     * against the block's own date would make the same item look *more*
     * urgent the later the block is, which would bias placement toward
     * later blocks — backwards from "front-load work into the earliest
     * block that fits", which is the entire point of processing blocks in
     * chronological order.
     */
    private static function score(array $item, int $remaining, Carbon $today): float
    {
        // Positive when the deadline is still ahead, negative once overdue.
        // Verified empirically (not just derived) via
        // WorkPlannerTest::test_a_more_urgent_task_wins_the_only_slot... —
        // an earlier version called this the other way around and silently
        // inverted the entire urgency ranking.
        $urgency = $item['deadline'] !== null
            ? (self::HORIZON_DAYS - $today->diffInDays($item['deadline'], false)) / self::HORIZON_DAYS
            : 0.0;

        $fit = $item['duration'] / $remaining;

        return $urgency * self::URGENCY_WEIGHT
            + $fit * self::FIT_WEIGHT
            + ($item['is_important'] ? self::IMPORTANCE_BONUS : 0.0);
    }

    /**
     * One rescue attempt per victim: first try bumping a filler item (free,
     * nothing to relocate), then a genuinely less-urgent dated item (which
     * must be relocated to a later block it still meets its own deadline
     * in). Never touches a 'manual' occupant. Not a general solver — if
     * neither pass finds a clean swap, the victim stays unplaced and shows
     * up as a real conflict, which is the honest outcome.
     */
    private static function attemptRescue(array $victim, array &$state): bool
    {
        $orderedIds = collect($state)
            ->filter(fn ($e) => $e['event']->date->toDateString() <= $victim['deadline']->toDateString())
            ->sortBy(fn ($e) => $e['event']->date->toDateString().$e['event']->start_time)
            ->keys();

        foreach ($orderedIds as $blockId) {
            $remaining = $state[$blockId]['capacity'] - collect($state[$blockId]['occupied'])->sum('duration');

            foreach ($state[$blockId]['occupied'] as $i => $occ) {
                if ($occ['source'] !== 'auto' || $occ['deadline'] !== null) {
                    continue;
                }
                if ($remaining + $occ['duration'] < $victim['duration']) {
                    continue;
                }

                unset($state[$blockId]['occupied'][$i]);
                $state[$blockId]['occupied'] = array_values($state[$blockId]['occupied']);
                $state[$blockId]['occupied'][] = [...$victim, 'source' => 'auto', 'sort_order' => self::nextSortOrder($state[$blockId])];

                return true;
            }
        }

        foreach ($orderedIds as $blockId) {
            $remaining = $state[$blockId]['capacity'] - collect($state[$blockId]['occupied'])->sum('duration');

            foreach ($state[$blockId]['occupied'] as $i => $occ) {
                if ($occ['source'] !== 'auto' || $occ['deadline'] === null) {
                    continue;
                }
                if (! $occ['deadline']->greaterThan($victim['deadline'])) {
                    continue; // not strictly less urgent than the victim
                }
                if ($remaining + $occ['duration'] < $victim['duration']) {
                    continue;
                }

                $newHomeId = collect($state)
                    ->filter(function ($e, $id) use ($blockId, $occ, $state) {
                        return $id !== $blockId
                            && $e['event']->date->greaterThan($state[$blockId]['event']->date)
                            && $e['event']->date->toDateString() <= $occ['deadline']->toDateString()
                            && ($e['capacity'] - collect($e['occupied'])->sum('duration')) >= $occ['duration'];
                    })
                    ->sortBy(fn ($e) => $e['event']->date->toDateString().$e['event']->start_time)
                    ->keys()
                    ->first();

                if ($newHomeId === null) {
                    continue;
                }

                unset($state[$blockId]['occupied'][$i]);
                $state[$blockId]['occupied'] = array_values($state[$blockId]['occupied']);
                $state[$blockId]['occupied'][] = [...$victim, 'source' => 'auto', 'sort_order' => self::nextSortOrder($state[$blockId])];
                $state[$newHomeId]['occupied'][] = [...$occ, 'sort_order' => self::nextSortOrder($state[$newHomeId])];

                return true;
            }
        }

        return false;
    }

    private static function nextSortOrder(array $entry): int
    {
        return $entry['occupied'] === [] ? 0 : (max(array_column($entry['occupied'], 'sort_order')) + 1);
    }

    // ── Eligibility ───────────────────────────────────────────────────

    /** Pomodoro-enabled category blocks in range — the only valid targets, never raw free time. */
    private static function workBlocksQuery(User $user, Carbon $from, Carbon $to)
    {
        return ScheduleEvent::forUser($user)
            ->visible()
            ->whereHas('category', fn ($q) => $q->where('pomodoro_enabled', true))
            ->forRange($from, $to)
            ->ordered();
    }

    /**
     * Dated candidates: board/project/group Tasks (never Inbox — untriaged)
     * with a deadline or due date, plus open Agenda homework (never exams —
     * there is no "work session" concept for an exam itself). A homework
     * entry already promoted into a live Task is represented by that Task
     * instead, so it is never listed twice.
     */
    private static function eligibleItems(User $user, Carbon $today, Carbon $horizonEnd): Collection
    {
        $items = collect();

        Task::forUser($user)->active()
            ->whereIn('list', ['todos', 'tasks', 'projects'])
            ->where(fn ($q) => $q->whereNotNull('deadline')->orWhereNotNull('due_date'))
            ->get()
            ->each(function (Task $task) use ($items) {
                $info = self::deadlineInfoForTask($task);

                if ($info === null) {
                    return;
                }

                $items->push([
                    'type' => 'task',
                    'id' => $task->id,
                    'title' => $task->title,
                    'duration' => self::durationForTask($task),
                    'deadline' => $info['effective'],
                    'raw_date' => $info['raw'],
                    'is_important' => (bool) $task->is_important,
                ]);
            });

        $promotedAgendaIds = Task::forUser($user)->active()->whereNotNull('agenda_entry_id')->pluck('agenda_entry_id')->all();

        AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)
            ->whereNotIn('id', $promotedAgendaIds)
            ->get()
            ->each(function (AgendaEntry $entry) use ($items) {
                $items->push([
                    'type' => 'agenda',
                    'id' => $entry->id,
                    'title' => "{$entry->subject}: {$entry->title}",
                    'duration' => $entry->duration_minutes ?? self::DEFAULT_HOMEWORK_DURATION,
                    'deadline' => $entry->date->copy()->subDays(self::DEADLINE_BUFFER_DAYS),
                    'raw_date' => $entry->date->copy(),
                    'is_important' => false,
                ]);
            });

        return $items->filter(fn (array $i) => $i['deadline']->lessThanOrEqualTo($horizonEnd));
    }

    /**
     * Undated backlog, lowest priority — only ever used to top up a block's
     * remaining space once every dated item has had its chance, mirroring
     * TaskSuggestor's own "today -> todos -> generic fallback" tiering so
     * this planner isn't a second brain with different opinions. Capped at a
     * reasonable batch rather than the whole backlog.
     */
    private static function fillerItems(User $user): Collection
    {
        return Task::forUser($user)->active()
            ->whereIn('list', ['todos', 'tasks'])
            ->whereNull('deadline')->whereNull('due_date')
            ->boardOrdered()
            ->limit(50)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'id' => $task->id,
                'title' => $task->title,
                'duration' => self::durationForTask($task),
                'deadline' => null,
                'is_important' => (bool) $task->is_important,
            ]);
    }

    private static function durationForTask(Task $task): int
    {
        return $task->duration_minutes ?? (self::DEFAULT_DURATION[$task->list] ?? 25);
    }

    /**
     * Hard deadline minus the buffer, or the soft Wunschtermin as-is; the
     * earlier of the two when both are set. Returns both the buffered
     * `effective` date (what actually drives scoring/conflict-detection) and
     * the real, unbuffered `raw` date behind it — the conflict list shows the
     * latter, since showing someone a date two days earlier than the one
     * they actually typed in would just be confusing. Null when neither
     * deadline/due_date is set.
     *
     * @return array{effective: Carbon, raw: Carbon}|null
     */
    private static function deadlineInfoForTask(Task $task): ?array
    {
        $best = null;

        if ($task->deadline !== null) {
            $best = ['effective' => $task->deadline->copy()->subDays(self::DEADLINE_BUFFER_DAYS), 'raw' => $task->deadline->copy()];
        }

        if ($task->due_date !== null) {
            $dueEffective = $task->due_date->copy();

            if ($best === null || $dueEffective->lessThan($best['effective'])) {
                $best = ['effective' => $dueEffective, 'raw' => $task->due_date->copy()];
            }
        }

        return $best;
    }

    /** Does this eligible item have a link (any source) to a block on or before its own effective deadline? */
    private static function hasTimelyPlacement(User $user, array $item): bool
    {
        if ($item['type'] === 'agenda') {
            $taskId = Task::forUser($user)->active()->where('agenda_entry_id', $item['id'])->value('id');

            if ($taskId === null) {
                return false; // never promoted, so definitely not linked anywhere yet
            }
        } else {
            $taskId = $item['id'];
        }

        return DB::table('schedule_event_task_links')
            ->join('schedule_events', 'schedule_events.id', '=', 'schedule_event_task_links.schedule_event_id')
            ->where('schedule_event_task_links.task_id', $taskId)
            ->whereDate('schedule_events.date', '<=', $item['deadline']->toDateString())
            ->exists();
    }

    /** Reuses an already-promoted Task if one exists (idempotent); otherwise creates one, mirroring TaskBoard::promoteHomeworkToday()'s shape. */
    private static function resolveOrPromote(User $user, int $agendaEntryId): Task
    {
        $existing = Task::forUser($user)->active()->where('agenda_entry_id', $agendaEntryId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $entry = AgendaEntry::visibleTo($user)->findOrFail($agendaEntryId);

        return $user->tasks()->create([
            'title' => "{$entry->subject}: {$entry->title}",
            'list' => 'tasks',
            'deadline' => $entry->date,
            'duration_minutes' => $entry->duration_minutes,
            'agenda_entry_id' => $entry->id,
            'sort_order' => 0,
        ]);
    }
}
