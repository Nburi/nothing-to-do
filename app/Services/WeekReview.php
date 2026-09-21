<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The read side of the Wochenrückblick: what one local calendar week (Monday
 * to Sunday) looked like — how much got done per day, how that compares with
 * the weeks before it, what exactly was finished, and what is still open from
 * that week. Stateless like ProgressStats, which it leans on for the one
 * thing both pages must agree on: the per-day completion counts (so the bars
 * here can never disagree with the heatmap on the Fortschritt page).
 */
class WeekReview
{
    /** How many earlier weeks the "vs. sonst" comparison averages over. */
    public const COMPARE_WEEKS = 4;

    /** Completed titles listed per week; the total count is always exact. */
    public const TITLE_LIMIT = 40;

    private const DAY_LABELS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    /** The Monday of the user's local week, moved by $offset whole weeks (negative = past). */
    public static function weekStart(User $user, int $offset = 0): Carbon
    {
        $today = $user->localToday()->startOfDay();

        return $today->copy()->subDays($today->dayOfWeekIso - 1)->addWeeks($offset);
    }

    /**
     * @return array{
     *     start: Carbon, end: Carbon, isCurrent: bool, isFuture: bool,
     *     days: list<array{date: string, label: string, count: int, isToday: bool, isFuture: bool}>,
     *     total: int, activeDays: int, bestDay: ?array{label: string, count: int},
     *     average: ?int, verdict: string, completed: list<array{label: string, titles: list<string>, more: int}>,
     *     open: list<array{id: int, title: string, dateLabel: string}>, openCount: int
     * }
     */
    public static function for(User $user, Carbon $monday, ?array $counts = null): array
    {
        $counts ??= ProgressStats::completedCountsByDay($user);
        $today = $user->localToday()->startOfDay();
        $monday = $monday->copy()->startOfDay();
        $sunday = $monday->copy()->addDays(6);

        $days = [];
        $total = 0;
        $activeDays = 0;
        $best = null;

        foreach (range(0, 6) as $i) {
            $date = $monday->copy()->addDays($i);
            $count = $counts[$date->toDateString()] ?? 0;

            $days[] = [
                'date' => $date->toDateString(),
                'label' => self::DAY_LABELS[$i],
                'count' => $count,
                'isToday' => $date->isSameDay($today),
                'isFuture' => $date->greaterThan($today),
            ];

            $total += $count;
            $activeDays += $count > 0 ? 1 : 0;

            if ($count > 0 && ($best === null || $count > $best['count'])) {
                $best = ['label' => $date->locale('de')->isoFormat('dddd'), 'count' => $count];
            }
        }

        $isCurrent = $monday->lessThanOrEqualTo($today) && $sunday->greaterThanOrEqualTo($today);
        $isFuture = $monday->greaterThan($today);

        [$average, $verdict] = self::verdict($counts, $monday, $total, $isCurrent, $isFuture);

        return [
            'start' => $monday,
            'end' => $sunday,
            'isCurrent' => $isCurrent,
            'isFuture' => $isFuture,
            'days' => $days,
            'total' => $total,
            'activeDays' => $activeDays,
            'bestDay' => $best,
            'average' => $average,
            'verdict' => $verdict,
            'completed' => $isFuture ? [] : self::completed($user, $monday, $sunday),
            ...self::open($user, $monday, $sunday, $isFuture),
        ];
    }

    /**
     * One honest sentence about the week, weighed against the weeks before it
     * — never against an arbitrary target the user did not set. A week that is
     * still running is not judged as "quiet": Wednesday's total is not a full
     * week's, so the comparison is only made once the week is over.
     *
     * @return array{0: ?int, 1: string}  [average of the earlier weeks, sentence]
     */
    private static function verdict(array $counts, Carbon $monday, int $total, bool $isCurrent, bool $isFuture): array
    {
        if ($isFuture) {
            return [null, 'Diese Woche liegt noch vor dir.'];
        }

        $weekTotals = [];
        foreach ($counts as $date => $n) {
            $weekKey = Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString();
            $weekTotals[$weekKey] = ($weekTotals[$weekKey] ?? 0) + $n;
        }

        $earlier = [];
        foreach (range(1, self::COMPARE_WEEKS) as $back) {
            $earlier[] = $weekTotals[$monday->copy()->subWeeks($back)->toDateString()] ?? 0;
        }
        $average = (int) round(array_sum($earlier) / count($earlier));

        $bestBefore = 0;
        foreach ($weekTotals as $weekKey => $n) {
            if ($weekKey < $monday->toDateString()) {
                $bestBefore = max($bestBefore, $n);
            }
        }

        if ($total === 0) {
            return [$average, $isCurrent ? 'Diese Woche ist noch nichts erledigt — es ist noch Zeit.' : 'In dieser Woche wurde nichts erledigt.'];
        }

        if ($isCurrent) {
            return [$average, $average > 0
                ? "Bisher {$total} erledigt — dein Schnitt der letzten Wochen liegt bei {$average}."
                : "Bisher {$total} erledigt. Die Woche läuft noch."];
        }

        if ($bestBefore > 0 && $total > $bestBefore) {
            return [$average, 'Deine stärkste Woche bisher.'];
        }

        if ($average === 0) {
            return [$average, 'Deine erste Woche mit erledigten Aufgaben.'];
        }

        return [$average, match (true) {
            $total >= $average * 1.15 => "Über deinem Schnitt — sonst waren es etwa {$average}.",
            $total <= $average * 0.85 => "Ruhiger als sonst — üblich sind etwa {$average}.",
            default => "Ziemlich genau dein Schnitt (etwa {$average}).",
        }];
    }

    /**
     * What was actually finished, grouped by local day. completed_at is UTC, so
     * the query window is widened by a day on each side and each row is then
     * bucketed by the user's offset *at that instant* — the same rule
     * ProgressStats::completedCountsByDay() follows.
     *
     * @return list<array{label: string, titles: list<string>, more: int}>
     */
    private static function completed(User $user, Carbon $monday, Carbon $sunday): array
    {
        $byDay = [];
        $listed = 0;

        Task::query()
            ->forUser($user)
            ->where('is_completed', true)
            ->whereBetween('completed_at', [$monday->copy()->subDay(), $sunday->copy()->addDays(2)])
            ->orderBy('completed_at')
            ->get(['title', 'completed_at'])
            ->each(function (Task $task) use ($user, $monday, $sunday, &$byDay, &$listed) {
                $local = $task->completed_at->copy()->addMinutes($user->utcOffsetMinutes($task->completed_at));

                if ($local->lt($monday) || $local->gte($sunday->copy()->addDay())) {
                    return;
                }

                $key = $local->toDateString();
                $byDay[$key] ??= ['date' => $local->copy()->startOfDay(), 'titles' => [], 'more' => 0];

                if ($listed < self::TITLE_LIMIT) {
                    $byDay[$key]['titles'][] = $task->title;
                    $listed++;
                } else {
                    $byDay[$key]['more']++;
                }
            });

        ksort($byDay);

        return collect($byDay)->map(fn (array $d) => [
            'label' => $d['date']->locale('de')->isoFormat('dddd, D. MMMM'),
            'titles' => $d['titles'],
            'more' => $d['more'],
        ])->values()->all();
    }

    /**
     * Board tasks that were due (either kind of date) inside the week and are
     * still open — what "slipped". Only meaningful for a week that has begun.
     *
     * @return array{open: list<array{id: int, title: string, dateLabel: string}>, openCount: int}
     */
    private static function open(User $user, Carbon $monday, Carbon $sunday, bool $isFuture): array
    {
        if ($isFuture) {
            return ['open' => [], 'openCount' => 0];
        }

        $from = $monday->toDateString();
        $to = $sunday->toDateString();

        $query = Task::query()
            ->forUser($user)
            ->active()
            ->onBoard()
            ->where(function ($q) use ($from, $to) {
                $q->where(fn ($d) => $d->whereDate('deadline', '>=', $from)->whereDate('deadline', '<=', $to))
                    ->orWhere(fn ($d) => $d->whereNull('deadline')->whereDate('due_date', '>=', $from)->whereDate('due_date', '<=', $to));
            });

        $count = (clone $query)->count();

        $open = $query->limit(6)->get()
            ->sortBy(fn (Task $t) => $t->effectiveDate()?->toDateString())
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'dateLabel' => $t->effectiveDate()->format('d.m.'),
            ])->values()->all();

        return ['open' => $open, 'openCount' => $count];
    }
}
