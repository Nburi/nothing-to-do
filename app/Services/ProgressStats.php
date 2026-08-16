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
     * Consecutive local days, ending today or yesterday, with ≥1 completed
     * task. A day not yet done today doesn't break the streak until tomorrow
     * starts without it — it's counted through yesterday instead, "at risk"
     * rather than already broken (see the streak-risk reminder).
     */
    public static function currentStreak(User $user, ?array $counts = null): int
    {
        $counts ??= self::completedCountsByDay($user);

        $cursor = ($counts[$user->localToday()->toDateString()] ?? 0) > 0
            ? $user->localToday()
            : $user->localToday()->subDay();

        $streak = 0;

        while (($counts[$cursor->toDateString()] ?? 0) > 0) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** The longest run of consecutive local days with ≥1 completed task, ever. */
    public static function bestStreak(array $counts): int
    {
        $dates = collect(array_keys(array_filter($counts, fn (int $c) => $c > 0)))
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
     * Whether *this* completion (the one that just moved today's count from
     * $beforeCount to $beforeCount+1) crosses a real milestone — the daily
     * goal, or an all-time daily record. Never both at once: a record is the
     * rarer, bigger achievement, so it wins if both are true on the same
     * completion. A record can only be "broken", never "set" out of nothing
     * — the very first tasks ever completed don't celebrate a record of 1.
     *
     * @return array{kind: 'record'|'goal', label: string}|null
     */
    public static function celebrationFor(User $user, int $beforeCount): ?array
    {
        $counts = self::completedCountsByDay($user);
        $today = $user->localToday()->toDateString();
        $afterCount = $beforeCount + 1;
        $priorBest = self::bestDailyCount($counts, excluding: $today);
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
