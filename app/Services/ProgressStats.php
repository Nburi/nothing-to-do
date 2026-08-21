<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Turns the app's one source of truth for "work happened" — tasks.completed_at
 * — into the numbers the /app/progress page and the two celebration triggers
 * need. Every day-bucket uses the user's local CALENDAR day (localToday()),
 * never User::completedWindowStart() — that's a different, board-only concept
 * (which completed task *cards* still show), not "which day did this happen
 * on". Deliberately scoped to Task only: Agenda/CraftIdea completions don't
 * feed the streak, this is specifically "did you do your to-dos".
 */
class ProgressStats
{
    /** Heatmap span, in weeks — see heatmap(). */
    public const HEATMAP_WEEKS = 12;

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
     * Every local day that has ≥1 task ever flagged "today" FOR it (see
     * Task::todayDateFor()), mapped to {total, done} — the today_date
     * equivalent of completedCountsByDay(), reused by every "cleared the
     * day" stat below. One query. A day simply isn't a key here at all if no
     * task was ever flagged today for it — distinct from {total:0, done:0},
     * which can't occur (todayDateFor never stamps a date with nothing on it).
     *
     * @return array<string, array{total: int, done: int}>
     */
    public static function todayListStatsByDay(User $user): array
    {
        $stats = [];

        foreach (Task::query()->forUser($user)->whereNotNull('today_date')->get(['today_date', 'is_completed']) as $task) {
            $key = $task->today_date->toDateString();
            $stats[$key] ??= ['total' => 0, 'done' => 0];
            $stats[$key]['total']++;

            if ($task->is_completed) {
                $stats[$key]['done']++;
            }
        }

        return $stats;
    }

    /**
     * Days where every task flagged "today" got completed — the streak's
     * basis. A day with no today-list at all is simply absent here (not
     * success, not failure) — the streak logic below treats "absent" as a
     * break, same as it always treated "nothing completed" as a break.
     *
     * This is always a live read, not a nightly-frozen snapshot: finishing
     * an old, left-over today-task days later can retroactively turn its
     * original day into a success (and heal a streak gap) — there's no
     * "close the day" ritual in this app to freeze anything against, and
     * eventually finishing what you flagged honestly earns the day.
     *
     * @param  array<string, array{total: int, done: int}>  $todayStats
     * @return array<string, bool>
     */
    public static function dailySuccessMap(array $todayStats): array
    {
        $map = [];

        foreach ($todayStats as $date => $stats) {
            $map[$date] = $stats['total'] > 0 && $stats['done'] === $stats['total'];
        }

        return $map;
    }

    /**
     * Consecutive local days, ending today or yesterday, that were "perfect"
     * (every today-task cleared). A day not yet perfect today doesn't break
     * the streak until tomorrow starts without it — it's counted through
     * yesterday instead, "at risk" rather than already broken (see the
     * streak-risk reminder).
     */
    public static function currentStreak(User $user, ?array $successMap = null): int
    {
        $successMap ??= self::dailySuccessMap(self::todayListStatsByDay($user));

        $cursor = ($successMap[$user->localToday()->toDateString()] ?? false)
            ? $user->localToday()
            : $user->localToday()->subDay();

        $streak = 0;

        while ($successMap[$cursor->toDateString()] ?? false) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** The longest run of consecutive "perfect" local days, ever. */
    public static function bestStreak(array $successMap): int
    {
        $dates = collect(array_keys(array_filter($successMap)))
            ->map(fn (string $d) => Carbon::parse($d))
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $best = 1;
        $run = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            // Carbon 3 diffIn* returns a float — cast for an exact 1-day match.
            $gap = (int) $dates[$i - 1]->diffInDays($dates[$i]);
            $run = $gap === 1 ? $run + 1 : 1;
            $best = max($best, $run);
        }

        return $best;
    }

    /** Lifetime count of "perfect" days — a different axis from raw completion volume. */
    public static function perfectDaysCount(array $successMap): int
    {
        return count(array_filter($successMap));
    }

    /**
     * Of the days you set a today-list at all, what share did you fully
     * clear — a consistency measure, not a volume one. Null (not 0) when
     * you've never set a today-list, since 0% would misleadingly read as
     * "you always fail" rather than "not applicable yet".
     */
    public static function perfectDayRate(array $successMap): ?int
    {
        if ($successMap === []) {
            return null;
        }

        return (int) round(100 * count(array_filter($successMap)) / count($successMap));
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
     * @return list<array{date: string, count: int, level: int, isToday: bool, isFuture: bool}>
     */
    public static function heatmap(User $user, array $counts, int $weeks = self::HEATMAP_WEEKS): array
    {
        $today = $user->localToday();
        $goal = $user->dailyTaskGoal();
        $mondayThisWeek = $today->copy()->subDays($today->dayOfWeekIso - 1);
        $start = $mondayThisWeek->copy()->subWeeks($weeks - 1);

        $days = [];

        for ($i = 0; $i < $weeks * 7; $i++) {
            $date = $start->copy()->addDays($i);
            $count = $counts[$date->toDateString()] ?? 0;

            $days[] = [
                'date' => $date->toDateString(),
                'count' => $count,
                'level' => self::levelFor($count, $goal),
                'isToday' => $date->isSameDay($today),
                'isFuture' => $date->greaterThan($today),
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
     *   1. Streak record — $task was part of today's today-list, completing
     *      it just brought today's open today-tasks to zero (today is now a
     *      "perfect" day), AND the resulting currentStreak() just moved past
     *      the best streak ever run before today. Can only be "broken", never
     *      "set" out of nothing — day one of a first-ever streak doesn't
     *      celebrate a "record" of 1.
     *   2. Perfect day — same "today's open today-tasks just hit zero" check,
     *      without a new streak record. Checked against the live DB state,
     *      which by the time this runs already reflects $task's own
     *      completion (called post-update).
     *   3. Record — today's count (given as $beforeCount, captured before
     *      the write) just moved past the all-time daily best. A record can
     *      only be "broken", never "set" out of nothing — the very first
     *      tasks ever completed don't celebrate a record of 1.
     *   4. Goal — today's count just reached the configured daily goal.
     *
     * @return array{kind: 'streak-record'|'perfect-day'|'record'|'goal', label: string}|null
     */
    public static function celebrationFor(User $user, Task $task, int $beforeCount): ?array
    {
        $today = $user->localToday();

        if ($task->today_date?->toDateString() === $today->toDateString()) {
            // whereDate(), not where(): a bare 'date' cast still stores full
            // datetime precision (a zeroed time-of-day), so an exact-string
            // where() against a plain 'Y-m-d' value would silently match
            // nothing — whereDate() normalizes the column for comparison.
            $stillOpen = Task::query()
                ->forUser($user)
                ->whereDate('today_date', $today->toDateString())
                ->where('is_completed', false)
                ->count();

            if ($stillOpen === 0) {
                $successMap = self::dailySuccessMap(self::todayListStatsByDay($user));
                $streak = self::currentStreak($user, $successMap);

                // "Prior" excludes today's own contribution, mirroring
                // bestDailyCount(..., excluding: today) below.
                unset($successMap[$today->toDateString()]);
                $priorBestStreak = self::bestStreak($successMap);

                if ($priorBestStreak > 0 && $streak > $priorBestStreak) {
                    return ['kind' => 'streak-record', 'label' => "Neue Bestserie: {$streak} Tage"];
                }

                return ['kind' => 'perfect-day', 'label' => 'Perfekter Tag'];
            }
        }

        $counts = self::completedCountsByDay($user);
        $afterCount = $beforeCount + 1;
        $priorBest = self::bestDailyCount($counts, excluding: $today->toDateString());
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
