<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\DayLoad;
use App\Planning\Rhythm;
use App\Services\ProgressStats;
use App\Services\WeekReview;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Refactor step 4.4, the quiet Sunday review (Niels' picks 2026-11-17: variant A "two halves").
 * Read-only. One week looking back (what moved) and the seven days after it looking ahead
 * (sessions of room against planned work, from the same forecast the crunch warning reads).
 *
 * Which week: on Sunday the week that ends today, on every other day the last full week, so
 * Monday's board line ("Deine letzte Woche") still shows the week that just ended.
 * The look ahead is the seven days after that Sunday; days already gone are marked as past.
 *
 * Reuses WeekReview for the per-day counts and the judgement against the user's own average
 * (the old /app/review page reads the same, so the two never disagree), and Capacity for the
 * forecast. It warns about nothing: an overloaded day is named calmly and links to the crunch
 * screen of step 4.2, which owns the warning.
 */
final class SundayReview
{
    /** Finished titles listed before "und N weitere"; the total is always exact. */
    public const DONE_LIMIT = 5;

    /** Carried-over and upcoming deadline rows listed before "und N weitere". */
    public const LIST_LIMIT = 6;

    public function __construct(
        private readonly Capacity $capacity = new Capacity,
        private readonly Rhythm $rhythm = new Rhythm,
    ) {}

    /** The Monday of the week the review looks back on, for the user's local date $today. */
    public static function reviewedMonday(CarbonImmutable $today): CarbonImmutable
    {
        $today = $today->startOfDay();
        $monday = $today->subDays($today->dayOfWeekIso - 1);

        return $today->dayOfWeekIso === 7 ? $monday : $monday->subWeek();
    }

    /** Sunday and Monday: the board shows a quiet line to the review. */
    public static function offeredOn(CarbonImmutable $today): bool
    {
        return in_array($today->dayOfWeekIso, [7, 1], true);
    }

    /**
     * @return array{
     *     today: string, monday: string, sunday: string, isSunday: bool,
     *     past: array{days: list<array{date: string, count: int}>, total: int, activeDays: int, average: int, kind: string,
     *         captured: int, inboxNow: int, resets: int, ideas: int,
     *         done: list<array{title: string, date: string, area: ?string, color: ?string}>, doneMore: int,
     *         carried: list<array{title: string, date: string, hard: bool}>, carriedMore: int},
     *     next: array{days: list<array{date: string, past: bool, paused: bool, capacity: int, load: int, over: int}>,
     *         capacity: int, load: int, hasBlocks: bool, tight: ?array{date: string, over: int},
     *         due: list<array{title: string, date: string, hard: bool}>, dueMore: int}
     * }
     */
    public function for(User $user, ?CarbonImmutable $now = null): array
    {
        $now = UserClock::now($user, $now);
        $today = CarbonImmutable::parse($now->toDateString());
        $monday = self::reviewedMonday($today);
        $sunday = $monday->addDays(6);

        return [
            'today' => $today->toDateString(),
            'monday' => $monday->toDateString(),
            'sunday' => $sunday->toDateString(),
            'isSunday' => $today->dayOfWeekIso === 7,
            'past' => $this->past($user, $monday, $sunday),
            'next' => $this->next($user, $now, $today, $sunday->addDay(), $sunday->addDays(7)),
        ];
    }

    private function past(User $user, CarbonImmutable $monday, CarbonImmutable $sunday): array
    {
        $counts = ProgressStats::completedCountsByDay($user);
        $days = [];
        foreach (range(0, 6) as $i) {
            $date = $monday->addDays($i)->toDateString();
            $days[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
        }
        $total = array_sum(array_column($days, 'count'));

        // The week under review is judged on its last day too (Sunday evening is the point of the review).
        ['kind' => $kind, 'average' => $average] = WeekReview::judge($counts, Carbon::parse($monday->toDateString()), $total, false);

        [$done, $ideas] = $this->finished($user, $monday, $sunday);
        [$carried, $carriedMore] = $this->dated($user, $monday, $sunday);

        return [
            'days' => $days,
            'total' => $total,
            'activeDays' => count(array_filter($days, fn (array $d) => $d['count'] > 0)),
            'average' => $average,
            'kind' => $kind,
            'captured' => $this->localCount(Task::query()->forUser($user), 'created_at', $user, $monday, $sunday),
            'inboxNow' => Task::query()->forUser($user)->active()->where('list', 'inbox')->count(),
            'resets' => Evening::query()->where('user_id', $user->id)
                ->whereDate('date', '>=', $monday->toDateString())->whereDate('date', '<=', $sunday->toDateString())
                ->whereNotNull('reset_started_at')->count(),
            'ideas' => $ideas,
            'done' => array_slice($done, 0, self::DONE_LIMIT),
            'doneMore' => max(0, count($done) - self::DONE_LIMIT),
            'carried' => $carried,
            'carriedMore' => $carriedMore,
        ];
    }

    /**
     * What was finished in the week, newest first, bucketed by the user's local day the way
     * ProgressStats does (the offset at that instant), plus how many of them were Later ideas.
     *
     * @return array{0: list<array{title: string, date: string, area: ?string, color: ?string}>, 1: int}
     */
    private function finished(User $user, CarbonImmutable $monday, CarbonImmutable $sunday): array
    {
        $done = [];
        $ideas = 0;

        Task::query()->forUser($user)
            ->where('is_completed', true)
            ->whereBetween('completed_at', [$monday->subDay(), $sunday->addDays(2)])
            ->with('area')
            ->orderByDesc('completed_at')->orderByDesc('id')
            ->get()
            ->each(function (Task $task) use ($user, $monday, $sunday, &$done, &$ideas) {
                $date = $this->localDate($user, $task->completed_at);
                if ($date < $monday->toDateString() || $date > $sunday->toDateString()) {
                    return;
                }
                $ideas += $task->list === Task::LATER ? 1 : 0;
                $done[] = ['title' => $task->title, 'date' => $date, 'area' => $task->area?->name, 'color' => $task->area?->color];
            });

        return [$done, $ideas];
    }

    /**
     * Open tasks with a hard or soft deadline inside [$from, $to], earliest first. A hard date wins
     * over a soft one on the same task (the hard date is the one that cannot move).
     *
     * @return array{0: list<array{title: string, date: string, hard: bool}>, 1: int}
     */
    private function dated(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Task::query()->forUser($user)->active()
            ->where(function ($q) use ($from, $to) {
                $q->where(fn ($d) => $d->whereDate('hard_deadline', '>=', $from->toDateString())->whereDate('hard_deadline', '<=', $to->toDateString()))
                    ->orWhere(fn ($d) => $d->whereNull('hard_deadline')
                        ->whereDate('soft_deadline', '>=', $from->toDateString())->whereDate('soft_deadline', '<=', $to->toDateString()));
            })
            ->get()
            ->map(fn (Task $t) => [
                'title' => $t->title,
                'date' => ($t->hardDeadline() ?? $t->softDeadline())->toDateString(),
                'hard' => $t->hardDeadline() !== null,
            ])
            ->sortBy([['date', 'asc'], ['hard', 'desc'], ['title', 'asc']])
            ->values();

        return [$rows->take(self::LIST_LIMIT)->all(), max(0, $rows->count() - self::LIST_LIMIT)];
    }

    private function next(User $user, CarbonImmutable $now, CarbonImmutable $today, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $forecast = $this->capacity->forecast($user, $now);
        $paused = SchedulePause::forUser($user)->forRange($from->toDateString(), $to->toDateString())->get()
            ->map(fn (SchedulePause $p) => $p->date->toDateString())->all();

        $days = [];
        $tight = null;
        foreach (range(0, 6) as $i) {
            $date = $from->addDays($i)->toDateString();
            $load = $date >= $today->toDateString() ? $forecast->day($date) : null;
            $day = [
                'date' => $date,
                'past' => $date < $today->toDateString(),
                'paused' => in_array($date, $paused, true),
                'capacity' => $load?->capacitySessions ?? 0,
                'load' => $load ? $this->sessions($load->loadMinutes) : 0,
                'over' => $load ? $this->over($load) : 0,
            ];
            $days[] = $day;
            if ($tight === null && $load?->overloaded) {
                $tight = ['date' => $date, 'over' => max(1, $day['over'])];
            }
        }

        [$due, $dueMore] = $this->dated($user, $from->max($today), $to);

        return [
            'days' => $days,
            'capacity' => array_sum(array_column($days, 'capacity')),
            'load' => array_sum(array_column($days, 'load')),
            'hasBlocks' => array_sum(array_column($days, 'capacity')) > 0,
            'tight' => $tight,
            'due' => $due,
            'dueMore' => $dueMore,
        ];
    }

    private function sessions(int $minutes): int
    {
        return (int) ceil($minutes / $this->rhythm->workMinutes);
    }

    private function over(DayLoad $day): int
    {
        return $this->sessions($day->overByMinutes());
    }

    /** The user's local date of a stored (UTC) moment, with the offset in force at that instant (as ProgressStats). */
    private function localDate(User $user, \DateTimeInterface $at): string
    {
        $utc = Carbon::instance($at)->utc();

        return $utc->addMinutes($user->utcOffsetMinutes($utc))->toDateString();
    }

    /** Rows whose UTC timestamp column falls on a local date inside the week. */
    private function localCount($query, string $column, User $user, CarbonImmutable $monday, CarbonImmutable $sunday): int
    {
        return $query->whereBetween($column, [$monday->subDay(), $sunday->addDays(2)])->pluck($column)
            ->filter(function ($at) use ($user, $monday, $sunday) {
                $date = $this->localDate($user, $at);

                return $date >= $monday->toDateString() && $date <= $sunday->toDateString();
            })->count();
    }
}
