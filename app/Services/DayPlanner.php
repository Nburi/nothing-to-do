<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        // A split task's TaskDayPlan row is only the anchor mirroring its next open part
        // (see syncAnchor()) — its parts are drawn from task_sessions below instead.
        $plans = TaskDayPlan::query()
            ->whereHas('task', fn ($q) => $q->forUser($user)->active()->whereDoesntHave('sessions'))
            ->whereDate('planned_date', '>=', $today->toDateString())
            ->whereDate('planned_date', '<=', $horizonEnd->toDateString())
            ->with(['task', 'task.sessions'])
            ->ordered()
            ->get()
            ->groupBy(fn (TaskDayPlan $p) => $p->planned_date->toDateString());

        $parts = TaskSession::query()->open()
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->whereDate('planned_date', '>=', $today->toDateString())
            ->whereDate('planned_date', '<=', $horizonEnd->toDateString())
            ->with('task.sessions')
            ->ordered()
            ->get()
            ->groupBy(fn (TaskSession $s) => $s->planned_date->toDateString());

        $days = collect();

        for ($i = 0; $i < self::HORIZON_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $key = $date->toDateString();
            $cap = $capacities->get($key, ['total' => 0, 'labels' => []]);
            $dayPlans = $plans->get($key, collect());

            $tasks = $dayPlans
                ->map(fn (TaskDayPlan $p) => ['sort' => $p->sort_order, 'item' => self::itemFromTask($p->task, $today)])
                ->concat($parts->get($key, collect())->map(fn (TaskSession $s) => ['sort' => $s->sort_order, 'item' => self::itemFromSession($s, $today)]))
                ->sortBy('sort')
                ->pluck('item')
                ->values();

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
     * @return Collection<int, array{type: string, id: int, title: string, list?: string, subject?: string, duration: int, deadlineOffset: ?int, deadlineDate: ?Carbon, isImportant: bool}>
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
            ->with('sessions')
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
     * Day-plans whose date has already passed, for a task that never made it
     * onto the board's own Today list in the meantime (is_today still
     * false). Deliberately *not* every past-dated TaskDayPlan row: once a
     * task is flagged Today — normally via PromoteDayPlansToToday, the
     * passive cron equivalent of promoteIfToday() above — it's already
     * visible and actionable on the main board, and showing it here too
     * would just be noise. What this *does* still catch: a task whose day
     * came and went while it was ineligible for that promotion in the first
     * place (a Project-owned task, onBoard()-excluded — see
     * promoteIfToday()'s own docblock) or one manually un-todayed later
     * without its Planer placement ever being touched. Read-only by design
     * (see the feature's own decision): nothing here rewrites planned_date
     * or moves anything automatically — a chip just sits here, draggable
     * like any backlog chip, until the user places it themselves.
     *
     * @return Collection<int, array>
     */
    public static function rollover(User $user): Collection
    {
        if (! $user->planner_enabled) {
            return collect();
        }

        $today = $user->localToday();

        return TaskDayPlan::query()
            ->whereDate('planned_date', '<', $today->toDateString())
            ->whereHas('task', fn ($q) => $q->forUser($user)->active()->where('is_today', false))
            ->with(['task', 'task.sessions'])
            ->orderBy('planned_date')
            ->get()
            ->map(fn (TaskDayPlan $p) => array_merge(
                self::rolloverItem($p, $today),
                [
                    'plannedDate' => $p->planned_date->toDateString(),
                    'plannedDateLabel' => self::pastDateLabel($p->planned_date, $today),
                ]
            ))
            ->values();
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

        $plainIds = [];
        $partTaskIds = [];

        foreach (array_values($items) as $position => $item) {
            [$type, $id] = array_pad(explode(':', (string) $item, 2), 2, null);
            $id = (int) $id;

            if ($type === 'session') {
                $taskId = self::placeSession($user, $id, $date, $position);

                if ($taskId !== null) {
                    $partTaskIds[$taskId] = true;
                }

                continue;
            }

            $taskId = match (true) {
                $type === 'agenda' => self::resolveOrPromote($user, $id)->id,
                $type === 'task' && $user->tasks()->whereKey($id)->exists() => $id,
                default => null,
            };

            if ($taskId !== null && ! isset($plainIds[$taskId])) {
                $plainIds[$taskId] = $position;
            }
        }

        if ($plainIds !== []) {
            // Placing a task as a whole again (an all-parts-done split task coming back from the backlog) ends its split.
            TaskSession::query()->whereIn('task_id', array_keys($plainIds))->delete();

            $now = now();
            $rows = collect($plainIds)->map(fn (int $position, int $taskId) => [
                'task_id' => $taskId,
                'planned_date' => $date,
                'sort_order' => $position,
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ])->values();

            TaskDayPlan::upsert($rows->all(), uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);
        }

        self::promoteIfToday($user, [...array_keys($plainIds), ...array_keys($partTaskIds)], $date);
    }

    /** Releases a task back to the backlog (a split task loses all its parts). Ownership must already be verified by the caller. */
    public static function unassignTask(Task $task): void
    {
        TaskSession::where('task_id', $task->id)->delete();
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

        if ($type === 'session') {
            $taskId = self::placeSession($user, $id, $date, self::nextSortOrder($user, $date));

            if ($taskId !== null) {
                self::promoteIfToday($user, [$taskId], $date);
            }

            return;
        }

        $taskId = match (true) {
            $type === 'agenda' => self::resolveOrPromote($user, $id)->id,
            $type === 'task' && $user->tasks()->whereKey($id)->exists() => $id,
            default => null,
        };

        if ($taskId === null) {
            return;
        }

        // Placing a task as a whole again ends a split it may still carry.
        TaskSession::where('task_id', $taskId)->delete();

        TaskDayPlan::upsert([[
            'task_id' => $taskId,
            'planned_date' => $date,
            'sort_order' => self::nextSortOrder($user, $date),
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]], uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);

        self::promoteIfToday($user, [$taskId], $date);
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
                self::promoteIfToday($user, $placed->pluck('id'), $key);
            }
        }
    }

    /**
     * The shared half of "planned in the Planer, becomes Heute when the day
     * begins": flags every not-yet-Heute task among the given ids as Heute,
     * but only when $date is the user's own local today — a no-op for a
     * future-day placement. The three call sites above cover the immediate
     * case (drag/autofill straight onto today's column); the passive case —
     * a day-plan just sitting there until its date arrives with nobody
     * touching the Planer — is App\Console\Commands\PromoteDayPlansToToday,
     * which calls this same helper once a minute with $date = today itself.
     *
     * Mirrors TaskBoard::setToday()'s own is_today/today_date pairing
     * (Task::todayDateFor()) and its isInbox()/isInProject() guard exactly
     * (onBoard() = project_id IS NULL covers the Project half; list=inbox
     * is excluded explicitly since nothing stops a stray id ending up here
     * even though backlog() itself never offers an Inbox task to plan) — a
     * Project-owned task never surfaces on the board's Today zone, so
     * silently flagging one here would inflate ProgressStats' streak with a
     * task nobody can see or complete (see CLAUDE.md §7 Task-Gruppen).
     *
     * Idempotency lives in task_day_plans.promoted_for_date, stamped below
     * for every eligible task (already Heute or just flagged) and read by
     * PromoteDayPlansToToday — NOT in is_today=false, which would also match
     * a task the user deliberately removed from Heute and bounce it back.
     * This helper itself always promotes: reaching it via a drag/autofill
     * onto today is an explicit user decision, only the cron tick isn't.
     *
     * @param  iterable<int>  $taskIds
     */
    public static function promoteIfToday(User $user, iterable $taskIds, string $date): void
    {
        $today = $user->localToday();

        if ($date !== $today->toDateString()) {
            return;
        }

        $ids = $taskIds instanceof Collection ? $taskIds->all() : (is_array($taskIds) ? $taskIds : iterator_to_array($taskIds));

        if (empty($ids)) {
            return;
        }

        $eligible = Task::query()
            ->forUser($user)
            ->active()
            ->onBoard()
            ->where('list', '!=', 'inbox')
            ->whereIn('id', $ids)
            ->get();

        $eligible->where('is_today', false)->each(fn (Task $task) => $task->update([
            'is_today' => true,
            'today_date' => $task->todayDateFor(true, $today),
        ]));

        // Mark these plans as handled for their current date (see docblock).
        TaskDayPlan::query()
            ->whereIn('task_id', $eligible->modelKeys())
            ->update(['promoted_for_date' => DB::raw('planned_date')]);
    }

    // ── Aufteilen: one task, several days ─────────────────────────────

    /** Fewest / most minutes one part may carry. */
    public const MIN_PART_MINUTES = 5;

    public const MAX_PART_MINUTES = 600;

    /** A part is rounded to this many minutes so the sheet never shows "37 min". */
    public const PART_MINUTES_STEP = 5;

    /**
     * Everything the split sheet needs to draw its 14-day strip: per day the
     * Arbeit-Block capacity (this task's own open parts left out, so the
     * sheet can add the selection on top without double counting), the
     * weekday/date labels, whether a done part already occupies the day, and
     * where the task's (buffered) deadline falls. Null when the Planer is off
     * or the task can't be split (not an active board/project task).
     *
     * @return array{days: array<int, array<string, mixed>>, deadlineOffset: ?int, deadlineLabel: ?string, selected: array<int, string>, doneCount: int, parts: array<int, array<string, mixed>>, defaultTotal: int, isSplit: bool}|null
     */
    public static function splitContext(User $user, Task $task): ?array
    {
        if (! self::canSplit($user, $task)) {
            return null;
        }

        $task->loadMissing('sessions');
        $today = $user->localToday();
        $board = self::board($user);
        $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $info = self::deadlineInfoForTask($task);
        $sessions = $task->sessions;

        $days = [];

        foreach ($board->values() as $offset => $day) {
            $key = $day['date']->toDateString();
            $own = $sessions->first(fn (TaskSession $s) => ! $s->isDone() && $s->planned_date->toDateString() === $key);
            $done = $sessions->first(fn (TaskSession $s) => $s->isDone() && $s->planned_date->toDateString() === $key);

            $days[] = [
                'date' => $key,
                'offset' => $offset,
                'weekday' => $wd[$day['date']->dayOfWeek],
                'dayNumber' => $day['date']->day,
                'label' => match (true) {
                    $offset === 0 => 'Heute',
                    $offset === 1 => 'Morgen',
                    default => $wd[$day['date']->dayOfWeek].' '.$day['date']->isoFormat('D.M.'),
                },
                'capacityTotal' => $day['capacityTotal'],
                'capacityUsed' => max(0, $day['capacityUsed'] - ($own?->minutes ?? 0)),
                'blockLabels' => $day['blockLabels'],
                'locked' => $done !== null,
                'lockedMinutes' => $done?->minutes,
            ];
        }

        $open = $sessions->filter(fn (TaskSession $s) => ! $s->isDone());

        return [
            'days' => $days,
            'deadlineOffset' => $info === null ? null : (int) $today->diffInDays($info['effective'], false),
            'deadlineLabel' => $task->effectiveDateLabel(),
            'selected' => $open->map(fn (TaskSession $s) => $s->planned_date->toDateString())->values()->all(),
            'doneCount' => $sessions->count() - $open->count(),
            'parts' => $sessions->map(fn (TaskSession $s, int $i) => [
                'id' => $s->id,
                'date' => $s->planned_date->toDateString(),
                'minutes' => $s->minutes,
                'done' => $s->isDone(),
                'position' => $i + 1,
            ])->values()->all(),
            'defaultTotal' => $open->isNotEmpty() ? (int) $open->sum('minutes') : self::durationForTask($task),
            'isSplit' => $sessions->isNotEmpty(),
        ];
    }

    /** How many parts the sheet starts with: roughly an hour of work each, at least two. */
    public static function defaultPartCount(int $totalMinutes): int
    {
        return max(2, min(8, (int) ceil($totalMinutes / 60)));
    }

    /**
     * The app's proposal for which days to put $parts parts on. Prefers days
     * whose Arbeit-Blöcke still have room for one part, then days without any
     * block, and only then days that are already full; never a day after the
     * (buffered) deadline and never a day a finished part already sits on.
     * With a deadline the picks are spread evenly across the usable days —
     * not all piled onto the last one; without one the earliest days win.
     *
     * @return array<int, string> Y-m-d, ascending
     */
    public static function suggestSplitDates(User $user, Task $task, int $parts, int $totalMinutes): array
    {
        $context = self::splitContext($user, $task);

        if ($context === null) {
            return [];
        }

        $parts = max(1, min(self::HORIZON_DAYS, $parts));
        $perPart = self::roundMinutes($totalMinutes / $parts);
        $deadlineOffset = $context['deadlineOffset'];

        $usable = collect($context['days'])
            ->reject(fn (array $d) => $d['locked'])
            ->filter(fn (array $d) => $deadlineOffset === null || $deadlineOffset < 0 || $d['offset'] <= $deadlineOffset)
            ->values();

        if ($usable->isEmpty()) {
            return [];
        }

        $rank = fn (array $d): int => match (true) {
            $d['capacityTotal'] > 0 && $d['capacityTotal'] - $d['capacityUsed'] >= $perPart => 0,
            $d['capacityTotal'] === 0 => 1,
            default => 2,
        };

        $best = $usable->filter(fn (array $d) => $rank($d) === 0)->values();
        $pool = $best->count() >= $parts ? $best : $usable->sortBy(fn (array $d) => [$rank($d), $d['offset']])->take(max($parts, $best->count()))->sortBy('offset')->values();

        if ($pool->count() <= $parts) {
            return $pool->pluck('date')->all();
        }

        // Dated tasks spread over the usable window; undated ones start right away.
        if ($deadlineOffset === null || $parts === 1) {
            return $pool->take($parts)->pluck('date')->all();
        }

        return collect(range(0, $parts - 1))
            ->map(fn (int $i) => $pool->get((int) round($i * ($pool->count() - 1) / ($parts - 1)))['date'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Splits (or re-splits) a task across the given days. $totalMinutes is the
     * work still to be planned — divided evenly over the open parts. Parts
     * that are already done are never touched; open parts on a day that stays
     * selected keep their row (and with it their place in that day's order).
     * At least two parts must exist afterwards, counting finished ones.
     *
     * @param  array<int, string>  $dates  Y-m-d
     */
    public static function saveSplit(User $user, Task $task, array $dates, int $totalMinutes): bool
    {
        if (! self::canSplit($user, $task)) {
            return false;
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS - 1);
        $task->loadMissing('sessions');

        $doneDates = $task->sessions->filter(fn (TaskSession $s) => $s->isDone())
            ->map(fn (TaskSession $s) => $s->planned_date->toDateString());

        $dates = collect($dates)
            ->filter(fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1)
            ->filter(fn (string $d) => $d >= $today->toDateString() && $d <= $horizonEnd->toDateString())
            ->reject(fn (string $d) => $doneDates->contains($d))
            ->unique()->sort()->values();

        if ($dates->isEmpty() || $dates->count() + $doneDates->count() < 2) {
            return false;
        }

        $perPart = max(self::MIN_PART_MINUTES, min(self::MAX_PART_MINUTES, self::roundMinutes($totalMinutes / $dates->count())));

        DB::transaction(function () use ($user, $task, $dates, $perPart) {
            $existingOpen = $task->sessions->filter(fn (TaskSession $s) => ! $s->isDone())
                ->keyBy(fn (TaskSession $s) => $s->planned_date->toDateString());

            $existingOpen->reject(fn (TaskSession $s, string $d) => $dates->contains($d))->each->delete();

            foreach ($dates as $date) {
                $existing = $existingOpen->get($date);

                if ($existing !== null) {
                    $existing->update(['minutes' => $perPart]);

                    continue;
                }

                TaskSession::create([
                    'task_id' => $task->id,
                    'planned_date' => $date,
                    'minutes' => $perPart,
                    'sort_order' => self::nextSortOrder($user, $date),
                ]);
            }

            self::syncAnchor($task);
        });

        // Only a part that is due today puts the task on Heute — promoting for any other
        // reason would also stamp the anchor as "handled" and the cron would skip its real day.
        if ($dates->first() === $today->toDateString()) {
            self::promoteIfToday($user, [$task->id], $today->toDateString());
        }

        return true;
    }

    /** Ends the split: every part goes, the task returns to the backlog. */
    public static function unsplit(User $user, Task $task): void
    {
        if (! $user->planner_enabled || $task->user_id !== $user->id) {
            return;
        }

        self::unassignTask($task);
    }

    /**
     * Removes one open part (the "×" on its chip). The task's other parts stay.
     * If that leaves a single open part and nothing finished, it stops being a
     * "split" altogether and simply becomes an ordinarily placed task.
     */
    public static function removeSession(User $user, int $sessionId): void
    {
        $session = self::ownedSession($user, $sessionId);

        if ($session === null || $session->isDone()) {
            return;
        }

        $task = $session->task;
        $session->delete();
        $task->unsetRelation('sessions');
        $task->load('sessions');

        if ($task->sessions->isEmpty()) {
            TaskDayPlan::where('task_id', $task->id)->delete();

            return;
        }

        self::syncAnchor($task);

        if ($task->sessions->count() === 1 && ! $task->sessions->first()->isDone()) {
            // Anchor now points at the lone part's day — it stays as a plain placement.
            $task->sessions->first()->delete();
        }
    }

    /**
     * Ticks a part off (or back open with $done = false). Finishing the part
     * that put the task on Heute takes it off Heute again — the next part
     * brings it back when its day arrives (the anchor moves, so the promote
     * cron treats it as a fresh date). Returns the task's progress afterwards
     * plus the celebration to show when ticking this part cleared today's
     * list; null when the part isn't the user's.
     *
     * @return array{progress: ?array, celebration: ?array}|null
     */
    public static function completeSession(User $user, int $sessionId, bool $done = true): ?array
    {
        $session = self::ownedSession($user, $sessionId);

        if ($session === null) {
            return null;
        }

        $task = $session->task;
        $today = $user->localToday()->toDateString();

        $session->update(['done_at' => $done ? now() : null]);
        $task->unsetRelation('sessions');
        $task->load('sessions');
        self::syncAnchor($task);

        $openDueNow = $task->sessions->contains(fn (TaskSession $s) => ! $s->isDone() && $s->planned_date->toDateString() <= $today);

        if ($done && $task->is_today && ! $openDueNow && $session->planned_date->toDateString() <= $today) {
            $task->update(['is_today' => false, 'today_date' => $task->todayDateFor(false)]);
        }

        if (! $done && $session->planned_date->toDateString() === $today) {
            self::promoteIfToday($user, [$task->id], $today);
        }

        return [
            'progress' => $task->sessionProgress(),
            'celebration' => $done ? ProgressStats::recordPerfectDayIfReached($user) : null,
        ];
    }

    /** Whether the user may split this task right now: Planer on, theirs, still open, and a list the Planer plans. */
    public static function canSplit(User $user, Task $task): bool
    {
        return $user->planner_enabled
            && $task->user_id === $user->id
            && ! $task->is_completed
            && in_array($task->list, ['todos', 'tasks', 'projects'], true);
    }

    // ── Internals ─────────────────────────────────────────────────────

    /** An open part of one of the user's active tasks, with its task loaded; null for anything else. */
    private static function ownedSession(User $user, int $sessionId): ?TaskSession
    {
        return TaskSession::query()
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->with('task')
            ->find($sessionId);
    }

    /**
     * Moves one open part onto $date at $position. Refused (null) when the
     * part is finished or the task already has another part on that day.
     * Returns the task id so the caller can promote it to Heute.
     */
    private static function placeSession(User $user, int $sessionId, string $date, int $position): ?int
    {
        $session = self::ownedSession($user, $sessionId);

        if ($session === null || $session->isDone()) {
            return null;
        }

        $clash = TaskSession::query()
            ->where('task_id', $session->task_id)
            ->whereDate('planned_date', $date)
            ->where('id', '!=', $session->id)
            ->exists();

        if ($clash) {
            return null;
        }

        $session->update(['planned_date' => $date, 'sort_order' => $position]);
        self::syncAnchor($session->task);

        return $session->task_id;
    }

    /**
     * Keeps task_day_plans in step with a split task: its one row mirrors the
     * earliest still-open part, and disappears once every part is done. Does
     * nothing for a task without parts, so a plain placement is never touched.
     */
    private static function syncAnchor(Task $task): void
    {
        if (! TaskSession::where('task_id', $task->id)->exists()) {
            return;
        }

        $next = TaskSession::where('task_id', $task->id)->open()->orderBy('planned_date')->first();

        if ($next === null) {
            TaskDayPlan::where('task_id', $task->id)->delete();

            return;
        }

        TaskDayPlan::upsert([[
            'task_id' => $task->id,
            'planned_date' => $next->planned_date->toDateString(),
            'sort_order' => $next->sort_order,
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]], uniqueBy: ['task_id'], update: ['planned_date', 'sort_order', 'source', 'updated_at']);
    }

    /** The next free slot at the end of a day, counting plain placements and open parts alike. */
    private static function nextSortOrder(User $user, string $date): int
    {
        $plan = TaskDayPlan::query()->forDate($date)
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->max('sort_order');

        $part = TaskSession::query()->open()->forDate($date)
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->max('sort_order');

        $max = max($plan ?? -1, $part ?? -1);

        return $max + 1;
    }

    private static function roundMinutes(float|int $minutes): int
    {
        $step = self::PART_MINUTES_STEP;

        return max($step, (int) (round($minutes / $step) * $step));
    }

    /** A rollover chip: the open part that was due, for a split task; the plain task otherwise. */
    private static function rolloverItem(TaskDayPlan $plan, Carbon $today): array
    {
        $task = $plan->task;

        if ($task->sessions->isNotEmpty()) {
            $part = $task->sessions->first(fn (TaskSession $s) => ! $s->isDone() && $s->planned_date->isSameDay($plan->planned_date));

            if ($part !== null) {
                $part->setRelation('task', $task);

                return self::itemFromSession($part, $today);
            }
        }

        return self::itemFromTask($task, $today);
    }

    /** A placed part, shaped like itemFromTask() plus the part marker ("Teil 2/3") and the part's own id as the chip id. */
    private static function itemFromSession(TaskSession $session, Carbon $today): array
    {
        $task = $session->task;
        $all = $task->sessions;
        $position = $all->search(fn (TaskSession $s) => $s->id === $session->id);

        return array_merge(self::itemFromTask($task, $today), [
            'type' => 'session',
            'id' => $session->id,
            'taskId' => $task->id,
            'duration' => $session->minutes,
            'hasEstimate' => true,
            'partLabel' => 'Teil '.(($position === false ? 0 : $position) + 1).'/'.$all->count(),
        ]);
    }

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

    /**
     * Short, calm "when was this actually for" label — gestern/weekday/d.m.,
     * mirroring the app's other hand-rolled date labels (never
     * diffForHumans(), see Task::effectiveDateLabel()). Reparses both sides
     * from their plain Y-m-d strings rather than diffing the Carbon
     * instances directly — $today (a startOfDay() instant) and $date (an
     * already-midnight 'date:Y-m-d' cast) have no business disagreeing, but
     * re-parsing from the date-only string is what actually guarantees an
     * exact whole-day count regardless of how either instant got built (see
     * CLAUDE.md's Known Issues entry on exact-date comparisons).
     */
    private static function pastDateLabel(Carbon $date, Carbon $today): string
    {
        $daysAgo = abs((int) Carbon::parse($today->toDateString())->diffInDays(Carbon::parse($date->toDateString())));

        return match (true) {
            $daysAgo === 1 => 'gestern',
            $daysAgo >= 2 && $daysAgo <= 6 => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$date->dayOfWeek],
            default => $date->isoFormat('D.M.'),
        };
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
     * date-formatting implementation here. `list` rides along so the chip
     * can show a To-Do/Task/Projekt tag — the Planer board mixes all three
     * (and homework) in one flat list, unlike the main board where the
     * column itself already says which list a card belongs to.
     */
    private static function itemFromTask(Task $task, Carbon $today): array
    {
        $info = self::deadlineInfoForTask($task);

        return [
            'type' => 'task',
            'id' => $task->id,
            'title' => $task->title,
            'list' => $task->list,
            'duration' => self::durationForTask($task),
            'hasEstimate' => $task->duration_minutes !== null,
            'deadlineOffset' => $info === null ? null : (int) $today->diffInDays($info['effective'], false),
            'deadlineLabel' => $task->effectiveDateLabel(),
            'isImportant' => (bool) $task->is_important,
            // Only set when every part of a split task is ticked but the task itself isn't — the cue to finish it.
            'partLabel' => $task->sessions->isNotEmpty() && $task->sessionProgress()['allDone'] ? 'alle Teile erledigt' : null,
        ];
    }

    /**
     * `subject` rides along separately from `title` (rather than baked in
     * as "Subject: Title", the way a *promoted* Task's own title is built
     * in resolveOrPromote() below) so the chip can show it as its own
     * coloured tag instead of repeating it inline with the title text.
     */
    private static function itemFromAgendaEntry(AgendaEntry $entry, Carbon $today): array
    {
        $effective = $entry->date->copy()->subDays(self::DEADLINE_BUFFER_DAYS);

        return [
            'type' => 'agenda',
            'id' => $entry->id,
            'title' => $entry->title,
            'subject' => $entry->subject,
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
