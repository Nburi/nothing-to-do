<?php

namespace App\Services;

use App\Models\StreakDayOutcome;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the app's one source of truth for "work happened" — tasks.completed_at
 * — into the numbers the /app/progress page and the celebration triggers need.
 * Every day-bucket uses the user's local CALENDAR day (localToday()), never
 * User::completedWindowStart() — that's a different, board-only concept
 * (which completed task *cards* still show), not "which day did this happen
 * on". Deliberately scoped to Task only: Agenda/CraftIdea completions don't
 * feed the streak, this is specifically "did you do your to-dos".
 *
 * ── What counts as a "perfect" day (the streak's basis) ─────────────────
 *
 *   1. A day with a today-list (tasks.today_date) or, while Planner is
 *      enabled, a Planer day-plan (TaskDayPlan) is perfect once every task in
 *      the UNION of both is done. The union is deliberate, not a hard
 *      either/or switch: today_date always still counts even with Planner
 *      on, so flipping planner_enabled never retroactively blanks out a
 *      history that only ever used the plain "Heute" flag (a past day simply
 *      has no TaskDayPlan rows to add, so the union degrades to exactly the
 *      old behavior for it). What Planner adds on top is real: a
 *      Project-owned task is deliberately never promoted to is_today (see
 *      DayPlanner::promoteIfToday()'s onBoard() guard) yet can still sit on
 *      a day's plan, so without the union it could never contribute to a
 *      perfect day at all.
 *   2. A day with NEITHER (nothing flagged today, nothing planned) is
 *      perfect if the day's total completed count alone reaches the daily
 *      goal — being productive without a today-list still counts.
 *   3. Clearing the whole board (every active Inbox/To-Do/Task) also makes
 *      today perfect outright, regardless of 1/2 — see isBoardFullyClear().
 *   4. A genuinely empty, under-goal day can "freeze" instead of breaking the
 *      streak — up to MAX_FREEZES_PER_WEEK times in any trailing
 *      FREEZE_WINDOW_DAYS window. A frozen day neither extends nor breaks the
 *      streak (see currentStreak()/bestStreak()). Freeze decisions are made
 *      once, retrospectively, by App\Console\Commands\EvaluateStreakDays —
 *      never live for "today", since the day might still get worked on.
 *
 * Once a day's outcome is decided (perfect or frozen), it is written to
 * StreakDayOutcome and never revisited: this is what stops a today-task
 * added AFTER today was already fully cleared (e.g. jotting something down
 * for tomorrow while today's list is already done) from silently un-perfecting
 * the day. See dailyOutcomeMap()/recordOutcome().
 */
class ProgressStats
{
    /** Heatmap span, in weeks — see heatmap(). */
    public const HEATMAP_WEEKS = 12;

    /** How many empty, under-goal days can "freeze" the streak instead of breaking it. */
    public const MAX_FREEZES_PER_WEEK = 2;

    /** The trailing window (in days, inclusive of the day being decided) a freeze budget is spent against. */
    public const FREEZE_WINDOW_DAYS = 7;

    /**
     * Every local day that has at least one completed task, mapped to how
     * many. One query, reused by every other method here — compute it once
     * per request/action and pass it along, never call this in a loop.
     *
     * Each timestamp is shifted using the user's offset *at that instant*
     * (not "now") — DST-auto users have a different offset in July than in
     * January, and this bucket spans a user's whole history.
     *
     * @return array<string, int> keyed by 'Y-m-d'
     */
    public static function completedCountsByDay(User $user): array
    {
        return Task::query()
            ->forUser($user)
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->get(['completed_at'])
            ->countBy(fn (Task $task) => $task->completed_at
                ->copy()
                ->addMinutes($user->utcOffsetMinutes($task->completed_at))
                ->toDateString())
            ->all();
    }

    /** How many tasks this user has completed today (local calendar day). */
    public static function todayCount(User $user, ?array $counts = null): int
    {
        $counts ??= self::completedCountsByDay($user);

        return $counts[$user->localToday()->toDateString()] ?? 0;
    }

    /**
     * Every local day with a "today set" — the UNION of tasks.today_date and
     * (while planner_enabled) TaskDayPlan.planned_date, deduped by task id —
     * mapped to {total, done}. One query for the today_date half, a second
     * (only issued when Planner is on) for the planner half; reused by every
     * "cleared the day" stat below. A day simply isn't a key here at all if
     * neither source ever named it — distinct from {total:0, done:0}, which
     * can't occur.
     *
     * @return array<string, array{total: int, done: int}>
     */
    public static function todayListStatsByDay(User $user): array
    {
        $byDate = [];

        $add = function (string $date, int $taskId, bool $done) use (&$byDate): void {
            $byDate[$date] ??= ['ids' => [], 'done' => []];
            $byDate[$date]['ids'][$taskId] = true;
            if ($done) {
                $byDate[$date]['done'][$taskId] = true;
            }
        };

        foreach (Task::query()->forUser($user)->whereNotNull('today_date')->get(['id', 'today_date', 'is_completed']) as $task) {
            $add($task->today_date->toDateString(), $task->id, $task->is_completed);
        }

        if ($user->planner_enabled) {
            foreach (TaskDayPlan::query()->whereHas('task', fn ($q) => $q->forUser($user))->with('task:id,is_completed')->get() as $plan) {
                $add($plan->planned_date->toDateString(), $plan->task_id, (bool) $plan->task?->is_completed);
            }
        }

        return array_map(
            fn (array $day) => ['total' => count($day['ids']), 'done' => count($day['done'])],
            $byDate,
        );
    }

    /** True once every active Inbox/To-Do/Task is completed — see the class docblock, point 3. */
    public static function isBoardFullyClear(User $user): bool
    {
        return Task::query()->forUser($user)->active()->whereIn('list', Task::BOARD_LISTS)->doesntExist();
    }

    /**
     * The today-set's task ids for ONE specific date — the same union rule
     * as todayListStatsByDay() (today_date ∪, while planner_enabled,
     * TaskDayPlan), but scoped to a single day instead of scanning a user's
     * whole history. Deliberately a separate, smaller query rather than
     * reusing todayListStatsByDay() for this: that method is built to
     * compute a multi-day map in two total queries, which would be wasteful
     * for asking about just one day, and it only ever returns counts, never
     * the actual task ids — see streakTasksNeeded(), the one caller that
     * needs the real rows.
     *
     * @return Collection<int, int>
     */
    public static function todaySetTaskIds(User $user, Carbon $date): Collection
    {
        $dateStr = $date->toDateString();

        $ids = Task::query()->forUser($user)->whereDate('today_date', $dateStr)->pluck('id');

        if ($user->planner_enabled) {
            $plannerIds = TaskDayPlan::query()
                ->whereHas('task', fn ($q) => $q->forUser($user))
                ->whereDate('planned_date', $dateStr)
                ->pluck('task_id');

            $ids = $ids->merge($plannerIds);
        }

        return $ids->unique()->values();
    }

    /**
     * The concrete answer to "what do I still need to do to keep my streak
     * today" — for the Fortschritt page and the get_progress MCP tool.
     * `secured` is true the moment today is already decided perfect (any of
     * the four rules in the class docblock), in which case there is nothing
     * left to show. Otherwise:
     *   - if today has a today-set (today-list and/or Planer plan), the
     *     SPECIFIC still-open tasks in it — completing all of them is what
     *     satisfies rule 1;
     *   - if it doesn't, how many MORE tasks (of any kind — rule 2 doesn't
     *     care which ones) would still reach the daily goal. Never both at
     *     once: a today-set, once it exists, is what rule 1 judges the day
     *     by, regardless of the overall daily count.
     *
     * @return array{secured: bool, openTasks: Collection<int, Task>, remainingForGoal: ?int}
     */
    public static function streakTasksNeeded(User $user): array
    {
        $today = $user->localToday();
        $todayKey = $today->toDateString();

        if ((self::dailyOutcomeMap($user)[$todayKey] ?? null) === StreakDayOutcome::OUTCOME_PERFECT) {
            return ['secured' => true, 'openTasks' => collect(), 'remainingForGoal' => null];
        }

        $ids = self::todaySetTaskIds($user, $today);

        if ($ids->isNotEmpty()) {
            $openTasks = Task::query()
                ->whereIn('id', $ids)
                ->where('is_completed', false)
                ->boardOrdered()
                ->get();

            return ['secured' => false, 'openTasks' => $openTasks, 'remainingForGoal' => null];
        }

        $remaining = max(0, $user->dailyTaskGoal() - self::todayCount($user));

        return ['secured' => false, 'openTasks' => collect(), 'remainingForGoal' => $remaining];
    }

    /**
     * The authoritative {date => 'perfect'|'frozen'|'broken'} outcome map
     * every streak calculation and stat walks. 'broken' is never persisted
     * (see StreakDayOutcome::OUTCOME_BROKEN) — it's filled in here purely so
     * perfectDaysCount()/perfectDayRate() can still tell "attempted and
     * missed" apart from "never attempted at all" the way the original,
     * boolean-only design did. Streak-walking treats 'broken' exactly like
     * "absent" (anything that isn't 'perfect'/'frozen' breaks the run).
     *
     * Combines, in this order (later steps only ever UPGRADE a day to
     * 'perfect', never downgrade or clear a decided one):
     *
     *   1. Every persisted StreakDayOutcome row for this user — durable,
     *      final decisions, including anything a past run of this method (or
     *      EvaluateStreakDays) already recorded.
     *   2. Any day whose today-set (todayListStatsByDay) is currently fully
     *      cleared — covers a day not yet persisted, and safely re-affirms
     *      one that already is. A total>0 day that ISN'T fully cleared is
     *      recorded 'broken', but only if nothing better already decided it
     *      (a day already 'perfect' via goal/full-clear, then given a
     *      still-open today-task later the same day, must stay 'perfect').
     *   3. TODAY specifically, via the two rules that need no today-set at
     *      all (goal reached on an empty day / whole board cleared) — a past
     *      day's outcome is already fixed by step 1, EvaluateStreakDays is
     *      what decides those.
     *
     * @param  array<string, array{total: int, done: int}>|null  $todayStats
     * @return array<string, 'perfect'|'frozen'|'broken'>
     */
    public static function dailyOutcomeMap(User $user, ?array $todayStats = null, ?array $counts = null): array
    {
        $todayStats ??= self::todayListStatsByDay($user);
        $todayKey = $user->localToday()->toDateString();

        $map = [];

        foreach (StreakDayOutcome::query()->forUser($user)->get(['date', 'outcome']) as $row) {
            $map[$row->date->toDateString()] = $row->outcome;
        }

        foreach ($todayStats as $date => $stats) {
            if ($stats['total'] === 0) {
                continue;
            }

            if ($stats['done'] === $stats['total']) {
                $map[$date] = StreakDayOutcome::OUTCOME_PERFECT;
            } elseif (! isset($map[$date])) {
                $map[$date] = StreakDayOutcome::OUTCOME_BROKEN;
            }
        }

        if (($map[$todayKey] ?? null) !== StreakDayOutcome::OUTCOME_PERFECT && ($todayStats[$todayKey]['total'] ?? 0) === 0) {
            $counts ??= self::completedCountsByDay($user);
            $completedToday = $counts[$todayKey] ?? 0;

            // completedToday > 0 guards isBoardFullyClear() specifically: an
            // account (or day) with literally zero tasks ever created would
            // otherwise vacuously satisfy "doesntExist() of an active task" —
            // "cleared everything" requires there to have been something to
            // clear. The goal check is already safe on its own (the daily
            // goal is always >= 1), but the guard costs nothing to share.
            if ($completedToday > 0 && ($completedToday >= $user->dailyTaskGoal() || self::isBoardFullyClear($user))) {
                $map[$todayKey] = StreakDayOutcome::OUTCOME_PERFECT;
            }
        }

        return $map;
    }

    /**
     * Persist a day's outcome — but only the first time. A date that already
     * has a row keeps it forever, whichever it is; see the class docblock on
     * why a decided day must never un-decide itself. Returns true only when
     * this call is the one that actually wrote it.
     */
    public static function recordOutcome(User $user, Carbon $date, string $outcome, ?string $reason = null): bool
    {
        return StreakDayOutcome::query()->firstOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            ['outcome' => $outcome, 'reason' => $reason],
        )->wasRecentlyCreated;
    }

    /**
     * How many 'frozen' days already fall inside the FREEZE_WINDOW_DAYS
     * window ending the day BEFORE $date (i.e. not counting $date itself,
     * which isn't decided yet) — so granting $date a freeze on top never
     * pushes the trailing FREEZE_WINDOW_DAYS-day window (now including
     * $date) past MAX_FREEZES_PER_WEEK.
     */
    public static function freezesUsedInTrailingWeek(User $user, Carbon $date): int
    {
        $windowStart = $date->copy()->subDays(self::FREEZE_WINDOW_DAYS - 1);
        $windowEnd = $date->copy()->subDay();

        return StreakDayOutcome::query()
            ->forUser($user)
            ->where('outcome', StreakDayOutcome::OUTCOME_FROZEN)
            ->whereBetween('date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->count();
    }

    /**
     * Decide a single PAST day's outcome once and for all — called by
     * App\Console\Commands\EvaluateStreakDays for every local day between a
     * user's last evaluated date and yesterday. A no-op if the day is already
     * decided (e.g. it was live-recorded as 'perfect' while it was still
     * "today" — see recordOutcome()/celebrationFor()). Never called for
     * "today" itself: a day's task set and completions are only truly final
     * once it's over.
     */
    public static function evaluatePastDay(User $user, Carbon $date): void
    {
        $dateKey = $date->toDateString();

        if (StreakDayOutcome::query()->forUser($user)->whereDate('date', $dateKey)->exists()) {
            return;
        }

        $stats = self::todayListStatsByDay($user)[$dateKey] ?? ['total' => 0, 'done' => 0];

        if ($stats['total'] > 0) {
            if ($stats['done'] === $stats['total']) {
                self::recordOutcome($user, $date, StreakDayOutcome::OUTCOME_PERFECT, 'today_list');
            }

            return; // an incomplete today-set is a genuine break — nothing to persist.
        }

        $completed = self::completedCountsByDay($user)[$dateKey] ?? 0;

        if ($completed >= $user->dailyTaskGoal()) {
            self::recordOutcome($user, $date, StreakDayOutcome::OUTCOME_PERFECT, 'goal_empty_day');

            return;
        }

        if (self::freezesUsedInTrailingWeek($user, $date) < self::MAX_FREEZES_PER_WEEK) {
            self::recordOutcome($user, $date, StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');
        }

        // Otherwise: budget spent, and nothing else earns the day — stays
        // undecided, a genuine break.
    }

    /**
     * Consecutive local days, ending today or yesterday, that were "perfect"
     * (see the class docblock) — a 'frozen' day is passed through without
     * incrementing the count, but doesn't break the run either ("can't
     * improve, can't lose"). A day not yet perfect today doesn't break the
     * streak until tomorrow starts without it — it's counted through
     * yesterday instead, "at risk" rather than already broken (see the
     * streak-risk reminder).
     */
    public static function currentStreak(User $user, ?array $outcomeMap = null): int
    {
        $outcomeMap ??= self::dailyOutcomeMap($user);
        $today = $user->localToday();

        $cursor = (($outcomeMap[$today->toDateString()] ?? null) === StreakDayOutcome::OUTCOME_PERFECT)
            ? $today
            : $today->subDay();

        $streak = 0;

        while (true) {
            $outcome = $outcomeMap[$cursor->toDateString()] ?? null;

            if ($outcome === StreakDayOutcome::OUTCOME_PERFECT) {
                $streak++;
            } elseif ($outcome !== StreakDayOutcome::OUTCOME_FROZEN) {
                break;
            }

            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * The longest run of consecutive "perfect" local days, ever — a 'frozen'
     * day bridges across without extending the run, same rule as
     * currentStreak(). Walks day by day across the map's full date range
     * (rather than diffing gaps between listed dates) since a frozen day
     * needs to be recognized even where it isn't itself a 'perfect' entry.
     *
     * @param  array<string, 'perfect'|'frozen'>  $outcomeMap
     */
    public static function bestStreak(array $outcomeMap): int
    {
        if ($outcomeMap === []) {
            return 0;
        }

        $dates = collect(array_keys($outcomeMap))->map(fn (string $d) => Carbon::parse($d))->sort()->values();
        $cursor = $dates->first()->copy();
        $end = $dates->last();

        $best = 0;
        $run = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            $outcome = $outcomeMap[$cursor->toDateString()] ?? null;

            if ($outcome === StreakDayOutcome::OUTCOME_PERFECT) {
                $run++;
                $best = max($best, $run);
            } elseif ($outcome !== StreakDayOutcome::OUTCOME_FROZEN) {
                $run = 0;
            }

            $cursor = $cursor->addDay();
        }

        return $best;
    }

    /** Lifetime count of "perfect" days — a different axis from raw completion volume. */
    public static function perfectDaysCount(array $outcomeMap): int
    {
        return count(array_filter($outcomeMap, fn (string $o) => $o === StreakDayOutcome::OUTCOME_PERFECT));
    }

    /**
     * Of the days in the map (perfect, frozen, or an attempted-but-incomplete
     * "broken" today-set), what share were perfect — a consistency measure,
     * not a volume one. Null (not 0) when nothing has ever been decided or
     * attempted, since 0% would misleadingly read as "you always fail"
     * rather than "not applicable yet".
     */
    public static function perfectDayRate(array $outcomeMap): ?int
    {
        if ($outcomeMap === []) {
            return null;
        }

        return (int) round(100 * self::perfectDaysCount($outcomeMap) / count($outcomeMap));
    }

    /** The most tasks ever completed in a single local day, optionally excluding one date. */
    public static function bestDailyCount(array $counts, ?string $excluding = null): int
    {
        if ($excluding !== null) {
            unset($counts[$excluding]);
        }

        return $counts === [] ? 0 : max($counts);
    }

    /**
     * A flat, chronological list of complete weeks (Monday–Sunday) ending
     * with the current week — HEATMAP_WEEKS*7 cells. Chronological order
     * doubles as CSS grid-auto-flow:column order (7 rows tall): day 0 is
     * row 1/col 1, day 1 is row 2/col 1, day 7 is row 1/col 2, and so on —
     * no separate reordering step needed before rendering.
     *
     * $outcomeMap is optional and additive: isStreakDay/isFrozen ride along
     * on a DIFFERENT axis than `level` (raw completion volume relative to
     * goal) — a day can be a "perfect" streak day (today-list/planner fully
     * cleared, or a frozen rest day) on very little volume, or high-volume
     * without ever being flagged "today" at all. Omitting it (or passing [])
     * just leaves both flags false, so existing callers keep working.
     *
     * @param  array<string, 'perfect'|'frozen'>  $outcomeMap
     * @return list<array{date: string, count: int, level: int, isToday: bool, isFuture: bool, isStreakDay: bool, isFrozen: bool}>
     */
    public static function heatmap(User $user, array $counts, int $weeks = self::HEATMAP_WEEKS, array $outcomeMap = []): array
    {
        $today = $user->localToday();
        $goal = $user->dailyTaskGoal();
        $mondayThisWeek = $today->copy()->subDays($today->dayOfWeekIso - 1);
        $start = $mondayThisWeek->copy()->subWeeks($weeks - 1);

        $days = [];

        for ($i = 0; $i < $weeks * 7; $i++) {
            $date = $start->copy()->addDays($i);
            $count = $counts[$date->toDateString()] ?? 0;
            $outcome = $outcomeMap[$date->toDateString()] ?? null;

            $days[] = [
                'date' => $date->toDateString(),
                'count' => $count,
                'level' => self::levelFor($count, $goal),
                'isToday' => $date->isSameDay($today),
                'isFuture' => $date->greaterThan($today),
                'isStreakDay' => $outcome === StreakDayOutcome::OUTCOME_PERFECT,
                'isFrozen' => $outcome === StreakDayOutcome::OUTCOME_FROZEN,
            ];
        }

        return $days;
    }

    /**
     * 0–4, relative to the user's own daily goal rather than a fixed absolute
     * count — otherwise a low-goal user's heatmap reads as permanently "full"
     * and a high-achiever's as permanently "empty".
     */
    private static function levelFor(int $count, int $goal): int
    {
        if ($count <= 0) {
            return 0;
        }

        if ($count > $goal) {
            return 4;
        }

        if ($count >= $goal) {
            return 3;
        }

        return $count / max(1, $goal) >= 0.66 ? 2 : 1;
    }

    /**
     * 0 (no streak) through 4 (long streak) — drives the header badge's and
     * the progress page's color escalation. Deliberately capped at `forest`:
     * this app reserves `signal` for danger/urgency (armed delete, overdue,
     * active emergency mode), so a *positive* streak never routes through it.
     */
    public static function streakTier(int $streak): int
    {
        return match (true) {
            $streak <= 0 => 0,
            $streak <= 2 => 1,
            $streak <= 6 => 2,
            $streak <= 13 => 3,
            default => 4,
        };
    }

    /**
     * Whether completing $task just crossed a real milestone. Checked in
     * priority order — the rarer/more meaningful one wins if several are
     * true on the same completion, never more than one at once:
     *
     *   1. Streak record — today just became "perfect" (by any of the rules
     *      in the class docblock), AND the resulting currentStreak() just
     *      moved past the best streak ever run before today. Can only be
     *      "broken", never "set" out of nothing.
     *   2. Full clear — today became perfect specifically because the whole
     *      board (every active Inbox/To-Do/Task) just emptied out. The
     *      biggest volume-shaped win short of a streak record.
     *   3. Perfect day — today became perfect via its today-set or the
     *      goal-on-an-empty-day rule, without a new streak record.
     *   4. Record — today's count (given as $beforeCount, captured before
     *      the write) just moved past the all-time daily best.
     *   5. Goal — today's count just reached the configured daily goal.
     *
     * Every "today became perfect" branch also persists the outcome (see
     * recordOutcome()) — this is the one place that happens for a
     * browser-driven completion; App\Support\TaskMutator does the same for
     * the API/MCP path, since the streak's data integrity can't depend on
     * whether anyone was around to see a celebration.
     *
     * @return array{kind: 'streak-record'|'full-clear'|'perfect-day'|'record'|'goal', label: string}|null
     */
    public static function celebrationFor(User $user, Task $task, int $beforeCount): ?array
    {
        $today = $user->localToday();
        $todayKey = $today->toDateString();

        $allTodayStats = self::todayListStatsByDay($user);
        $todayStats = $allTodayStats[$todayKey] ?? ['total' => 0, 'done' => 0];

        $isFullClear = in_array($task->list, Task::BOARD_LISTS, true) && self::isBoardFullyClear($user);
        $afterCount = $beforeCount + 1;

        $reason = null;

        if ($isFullClear) {
            $reason = 'full_clear';
        } elseif ($todayStats['total'] > 0 && $todayStats['done'] === $todayStats['total']) {
            $reason = 'today_list';
        } elseif ($todayStats['total'] === 0 && $beforeCount < $user->dailyTaskGoal() && $afterCount >= $user->dailyTaskGoal()) {
            // A strict crossing, like the legacy 'goal' tier below — without
            // it, every completion after the goal was already reached on a
            // today-set-free day would keep re-firing "perfect" endlessly.
            $reason = 'goal_empty_day';
        }

        if ($reason !== null) {
            self::recordOutcome($user, $today, StreakDayOutcome::OUTCOME_PERFECT, $reason);

            $outcomeMap = self::dailyOutcomeMap($user, $allTodayStats);
            $streak = self::currentStreak($user, $outcomeMap);

            // "Prior" excludes today's own contribution, mirroring
            // bestDailyCount(..., excluding: today) below.
            $priorOutcomeMap = $outcomeMap;
            unset($priorOutcomeMap[$todayKey]);
            $priorBestStreak = self::bestStreak($priorOutcomeMap);

            if ($priorBestStreak > 0 && $streak > $priorBestStreak) {
                return ['kind' => 'streak-record', 'label' => "Neue Bestserie: {$streak} Tage"];
            }

            if ($reason === 'full_clear') {
                return ['kind' => 'full-clear', 'label' => 'Alles erledigt!'];
            }

            return ['kind' => 'perfect-day', 'label' => 'Perfekter Tag'];
        }

        $counts = self::completedCountsByDay($user);
        $priorBest = self::bestDailyCount($counts, excluding: $todayKey);
        $goal = $user->dailyTaskGoal();

        if ($priorBest > 0 && $beforeCount <= $priorBest && $afterCount > $priorBest) {
            return ['kind' => 'record', 'label' => "Neuer Bestwert: {$afterCount}"];
        }

        if ($beforeCount < $goal && $afterCount >= $goal) {
            return ['kind' => 'goal', 'label' => 'Tagesziel erreicht'];
        }

        return null;
    }
}
