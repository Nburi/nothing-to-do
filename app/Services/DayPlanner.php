<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lets a user drag open Tasks/Todos/Agenda-homework onto a specific day —
 * not a specific calendar block — so the plan survives a Training block
 * being moved, resized, or renamed underneath it. A day's shown "capacity"
 * still comes from real work-session time (that day's Pomodoro-enabled
 * category blocks), never a made-up number: an earlier, unshipped prototype
 * of this app auto-filled all free time and felt oppressive, and reusing
 * that same restriction here keeps the day-board honest about what a day
 * can actually hold.
 *
 * Placement is 100% manual by default — nothing here runs on a schedule or
 * a page load the way the old block-filling planner's reconcile() did.
 * autoFillBacklog() is the one algorithmic action, and it is purely
 * additive: it only ever touches tasks with no placement yet, so it can
 * never reshuffle something the user has already dragged into place.
 *
 * Stateless, like TaskSuggestor/PomodoroCycle/ProgressStats. Every public
 * method no-ops when the feature is off (users.planner_enabled).
 */
class DayPlanner
{
    /** How many day-columns the board shows. Fixed for v1, not user-configurable. */
    public const HORIZON_DAYS = 14;

    /** A hard deadline is treated as due this many days earlier, for buffer. A soft Wunschtermin/homework date is not. */
    public const DEADLINE_BUFFER_DAYS = 2;

    /** Fallback duration (minutes) used only for capacity math when a task has no estimate of its own — never written back to the record. */
    public const DEFAULT_DURATION = ['todos' => 10, 'tasks' => 25, 'projects' => 25];

    /** Same reasoning as DEFAULT_DURATION — homework is typically task-sized, not todo-sized. */
    public const DEFAULT_HOMEWORK_DURATION = 25;

    public const URGENCY_WEIGHT = 1.0;

    public const FIT_WEIGHT = 0.5;

    public const IMPORTANCE_BONUS = 0.15;

    /**
     * The board: HORIZON_DAYS days starting today, each with its capacity
     * (from that day's Pomodoro-enabled blocks) and its planned tasks in
     * order. Keyed by Y-m-d.
     *
     * @return Collection<string, array{date: Carbon, capacityTotal: int, capacityUsed: int, blockLabels: array<string>, tasks: Collection<int, array>}>
     */
    public static function board(User $user): Collection
    {
        if (! $user->planner_enabled) {
            return collect();
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS - 1);

        ScheduleEvent::materializeRange($user, $today, $horizonEnd);

        $capacities = self::dayCapacities($user, $today, $horizonEnd);

        $plans = TaskDayPlan::query()
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->whereDate('planned_date', '>=', $today->toDateString())
            ->whereDate('planned_date', '<=', $horizonEnd->toDateString())
            ->with('task')
            ->ordered()
            ->get()
            ->groupBy(fn (TaskDayPlan $p) => $p->planned_date->toDateString());

        $days = collect();

        for ($i = 0; $i < self::HORIZON_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $key = $date->toDateString();
            $cap = $capacities->get($key, ['total' => 0, 'labels' => []]);
            $dayPlans = $plans->get($key, collect());

            $tasks = $dayPlans->map(fn (TaskDayPlan $p) => self::itemFromTask($p->task, $today))->values();

            $days->put($key, [
                'date' => $date,
                'capacityTotal' => $cap['total'],
                'capacityUsed' => $tasks->sum('duration'),
                'blockLabels' => $cap['labels'],
                'tasks' => $tasks,
            ]);
        }

        return $days;
    }

    /**
     * Every active, dated-or-not board/project task with no day yet, plus
     * open Agenda homework not yet promoted into a task — sorted by
     * deadline urgency so the most pressing unplaced items sit at the top.
     * A homework entry already promoted (via an earlier assignment or
     * TaskBoard::promoteHomeworkToday()) is represented by that task
     * instead, never listed twice.
     *
     * @return Collection<int, array{type: string, id: int, title: string, duration: int, deadlineOffset: ?int, deadlineDate: ?Carbon, isImportant: bool}>
     */
    public static function backlog(User $user): Collection
    {
        if (! $user->planner_enabled) {
            return collect();
        }

        $today = $user->localToday();
        $items = collect();

        Task::forUser($user)->active()
            ->whereIn('list', ['todos', 'tasks', 'projects'])
            ->whereDoesntHave('dayPlan')
            ->get()
            ->each(fn (Task $task) => $items->push(self::itemFromTask($task, $today)));

        $promotedAgendaIds = Task::forUser($user)->active()->whereNotNull('agenda_entry_id')->pluck('agenda_entry_id')->all();

        AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)
            ->whereNotIn('id', $promotedAgendaIds)
            ->get()
            ->each(fn (AgendaEntry $entry) => $items->push(self::itemFromAgendaEntry($entry, $today)));

        return $items->sortBy(fn (array $i) => $i['deadlineOffset'] ?? PHP_INT_MAX)->values();
    }

    /**
     * Dated backlog items whose effective deadline has already passed —
     * the only thing left that's genuinely too late, not merely "not yet
     * decided". (A manual-first board means most dated items sit unplanned
     * for a while by design; that's not a conflict, it's just the backlog.)
     * Placing an item anywhere — even today — removes it from backlog() and
     * therefore from here too, by construction.
     */
    public static function conflicts(User $user): Collection
    {
        return self::backlog($user)
            ->filter(fn (array $i) => $i['deadlineOffset'] !== null && $i['deadlineOffset'] < 0)
            ->values();
    }

    /**
     * Persists one day's full task order — the destination day of a drag,
     * same "send the whole ordered list" shape TaskBoard::reorder() and the
     * old Planer's reorderBlock() already used. Each entry is a
     * "task:<id>"/"agenda:<id>" token; an agenda token is promoted to a real
     * task at this point (not earlier — a rearranged drag never creates a
     * task it then discards). Since task_id is unique on task_day_plans, an
     * upsert-by-task_id both places and *relocates*: a task already planned
     * for another day is simply moved here, with nothing left behind to
     * clean up on the day it came from.
     *
     * @param  array<int, string>  $items
     */
    public static function assignDay(User $user, string $date, array $items): void
    {
        if (! $user->planner_enabled) {
            return;
        }

        $taskIds = collect($items)
            ->map(function (string $item) use ($user) {
                [$type, $id] = array_pad(explode(':', $item, 2), 2, null);
                $id = (int) $id;

                if ($type === 'agenda') {
                    return self::resolveOrPromote($user, $id)->id;
                }

                return $type === 'task' && $user->tasks()->whereKey($id)->exists() ? $id : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($taskIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $taskIds->map(fn (int $id, int $position) => [
            'task_id' => $id,
            'planned_date' => $date,
            'sort_order' => $position,
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        TaskDayPlan::upsert($rows->all(), uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);
    }

    /** Releases a task back to the backlog. Ownership must already be verified by the caller. */
    public static function unassignTask(Task $task): void
    {
        TaskDayPlan::where('task_id', $task->id)->delete();
    }

    /**
     * Single-item convenience for the mobile day-picker sheet: append one
     * chip to the end of a day rather than requiring the caller to already
     * know that day's full existing order the way assignDay() does. Same
     * agenda-token-promotion and upsert-relocates-by-task_id behavior.
     */
    public static function moveToDay(User $user, string $token, string $date): void
    {
        if (! $user->planner_enabled) {
            return;
        }

        [$type, $id] = array_pad(explode(':', $token, 2), 2, null);
        $id = (int) $id;

        $taskId = match (true) {
            $type === 'agenda' => self::resolveOrPromote($user, $id)->id,
            $type === 'task' && $user->tasks()->whereKey($id)->exists() => $id,
            default => null,
        };

        if ($taskId === null) {
            return;
        }

        $nextOrder = TaskDayPlan::query()->forDate($date)
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->max('sort_order');

        TaskDayPlan::upsert([[
            'task_id' => $taskId,
            'planned_date' => $date,
            'sort_order' => $nextOrder === null ? 0 : $nextOrder + 1,
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]], uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);
    }

    /**
     * "Rest automatisch einplanen" — the one algorithmic action. Purely
     * additive: only ever reads/writes tasks that have no day yet (see
     * backlog()), so a placement the user already made by hand can never be
     * touched, and there is nothing here to "undo" the way the old block
     * planner's regenerate() needed an armed confirmation for.
     *
     * Deliberately simpler than the old block-filling algorithm's repair
     * pass: since this is now an optional convenience rather than the only
     * way to place anything, a single greedy, chronological fill (best
     * urgency/fit/importance score wins each remaining slot) is
     * proportionate — a still-unplaced item after this just stays in the
     * backlog, which is an honest, visible, harmless outcome.
     */
    public static function autoFillBacklog(User $user): void
    {
        if (! $user->planner_enabled) {
            return;
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS - 1);

        $dated = self::backlog($user)->filter(fn (array $i) => $i['deadlineOffset'] !== null && $i['deadlineOffset'] >= 0);

        $undated = Task::forUser($user)->active()
            ->whereIn('list', ['todos', 'tasks'])
            ->whereNull('deadline')->whereNull('due_date')
            ->whereDoesntHave('dayPlan')
            ->boardOrdered()
            ->limit(50)
            ->get()
            ->map(fn (Task $t) => [
                'type' => 'task', 'id' => $t->id, 'duration' => self::durationForTask($t),
                'deadlineOffset' => null, 'isImportant' => (bool) $t->is_important,
            ]);

        $pool = $dated->concat($undated)->values();

        if ($pool->isEmpty()) {
            return;
        }

        $board = self::board($user);

        for ($i = 0; $i < self::HORIZON_DAYS && $pool->isNotEmpty(); $i++) {
            $date = $today->copy()->addDays($i);
            $key = $date->toDateString();
            $day = $board->get($key);
            $remaining = ($day['capacityTotal'] ?? 0) - ($day['capacityUsed'] ?? 0);

            if ($remaining <= 0) {
                continue;
            }

            $placed = collect();

            while ($remaining > 0) {
                $bestIndex = null;
                $bestScore = -INF;

                foreach ($pool as $index => $item) {
                    if ($item['duration'] > $remaining) {
                        continue;
                    }
                    if ($item['deadlineOffset'] !== null && $item['deadlineOffset'] < $i) {
                        continue; // this day is after the item's own deadline
                    }

                    $score = self::score($item, $remaining);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIndex = $index;
                    }
                }

                if ($bestIndex === null) {
                    break;
                }

                $chosen = $pool->get($bestIndex);
                $placed->push($chosen);
                $remaining -= $chosen['duration'];
                $pool = $pool->reject(fn ($_, $idx) => $idx === $bestIndex)->values();
            }

            if ($placed->isNotEmpty()) {
                self::commitAutoPlacements($key, $placed, startingAt: $day['tasks']->count());
            }
        }
    }

    // ── Internals ─────────────────────────────────────────────────────

    /** Per-day totals from that day's Pomodoro-enabled category blocks — the only valid capacity source, never raw free time. */
    private static function dayCapacities(User $user, Carbon $from, Carbon $to): Collection
    {
        return ScheduleEvent::forUser($user)->visible()
            ->whereHas('category', fn ($q) => $q->where('pomodoro_enabled', true))
            ->forRange($from, $to)
            ->with('category')
            ->get()
            ->groupBy(fn (ScheduleEvent $e) => $e->date->toDateString())
            ->map(fn (Collection $events) => [
                'total' => $events->sum(fn (ScheduleEvent $e) => $e->durationMinutes()),
                'labels' => $events->pluck('category.name')->filter()->unique()->values()->all(),
            ]);
    }

    private static function commitAutoPlacements(string $date, Collection $items, int $startingAt): void
    {
        $now = now();
        $rows = $items->values()->map(fn (array $item, int $i) => [
            'task_id' => $item['id'],
            'planned_date' => $date,
            'sort_order' => $startingAt + $i,
            'source' => 'auto',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        TaskDayPlan::upsert($rows->all(), uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);
    }

    /**
     * urgency (0 at the edge of the horizon, 1.0 due today, beyond 1 the
     * more overdue) weighted highest, fit (how completely the item uses the
     * day's remaining time) weighted half as much, plus a small flat bonus
     * for a starred task.
     */
    private static function score(array $item, int $remaining): float
    {
        $urgency = $item['deadlineOffset'] !== null
            ? (self::HORIZON_DAYS - $item['deadlineOffset']) / self::HORIZON_DAYS
            : 0.0;

        $fit = $item['duration'] / $remaining;

        return $urgency * self::URGENCY_WEIGHT
            + $fit * self::FIT_WEIGHT
            + ($item['isImportant'] ? self::IMPORTANCE_BONUS : 0.0);
    }

    private static function durationForTask(Task $task): int
    {
        return $task->duration_minutes ?? (self::DEFAULT_DURATION[$task->list] ?? 25);
    }

    /**
     * Normalizes a Task into the same shape used everywhere on the board
     * (backlog rows and placed chips alike): duration/importance, the
     * buffered deadlineOffset the tier wave classifies days against, and
     * the human label Task::effectiveDateLabel() already knows how to
     * build (heute/morgen/weekday/d.m./überfällig) — no second
     * date-formatting implementation here.
     */
    private static function itemFromTask(Task $task, Carbon $today): array
    {
        $info = self::deadlineInfoForTask($task);

        return [
            'type' => 'task',
            'id' => $task->id,
            'title' => $task->title,
            'duration' => self::durationForTask($task),
            'hasEstimate' => $task->duration_minutes !== null,
            'deadlineOffset' => $info === null ? null : (int) $today->diffInDays($info['effective'], false),
            'deadlineLabel' => $task->effectiveDateLabel(),
            'isImportant' => (bool) $task->is_important,
        ];
    }

    private static function itemFromAgendaEntry(AgendaEntry $entry, Carbon $today): array
    {
        $effective = $entry->date->copy()->subDays(self::DEADLINE_BUFFER_DAYS);

        return [
            'type' => 'agenda',
            'id' => $entry->id,
            'title' => "{$entry->subject}: {$entry->title}",
            'duration' => $entry->duration_minutes ?? self::DEFAULT_HOMEWORK_DURATION,
            'hasEstimate' => $entry->duration_minutes !== null,
            'deadlineOffset' => (int) $today->diffInDays($effective, false),
            'deadlineLabel' => $entry->dateLabel(),
            'isImportant' => false,
        ];
    }

    /**
     * Hard deadline minus the buffer, or the soft Wunschtermin as-is; the
     * earlier of the two when both are set. Returns both the buffered
     * `effective` date (drives the tier classification) and the real,
     * unbuffered `raw` date (shown to the user — a deadline two days
     * earlier than what they actually typed in would just be confusing).
     * Null when neither deadline/due_date is set.
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
