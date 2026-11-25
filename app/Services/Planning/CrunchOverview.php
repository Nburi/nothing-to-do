<?php

namespace App\Services\Planning;

use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Block;
use App\Planning\DayLoad;
use App\Planning\DeadlineFeasibility;
use App\Planning\PlannedTask;
use App\Planning\Rhythm;
use App\Planning\SlotCalculator;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Refactor step 4.2: what the board and the crunch screen show (R5; risks F5, F7).
 * Builds the plan once, records the warning state (the board counts as "seen",
 * see CrunchWatch::record) and returns a CrunchView:
 *
 * - The window: today until the latest deadline that does not fit.
 * - The items: every open task with a deadline inside the window, because they
 *   all compete for the same sessions. Soft ones first (the ones that already
 *   gave way to hard work lead), then hard ones by deadline (R5: soft deadlines
 *   are suggested to slip first; hard ones never change by themselves). A hard item that cannot
 *   be met carries the first date its deadline can move to so that it fits, from the planning
 *   engine (DeadlineFeasibility::fitDates): a proposal the user confirms, never a decision.
 * - The days of the window with capacity and load, for the bars.
 * - A suggestion for "Mehr Zeit": a one-off block before the last deadline, on
 *   the first day that has room at 19:00, else right before or after one of its
 *   blocks (07:00–22:30), as long as the category's usual block, in a pomodoro
 *   category that takes the work.
 */
final class CrunchOverview
{
    /** Day bars shown at most; longer windows skip the empty days. */
    public const MAX_DAYS = 10;

    /** "Mehr Zeit" looks this many days ahead (it is "this week only"). */
    public const MORE_TIME_DAYS = 7;

    private const DEFAULT_BLOCK_MINUTES = 60;

    private const EARLIEST_START = '07:00';

    private const LATEST_END = '22:30';

    private const PREFERRED_START = '19:00';

    public function __construct(
        private readonly Capacity $capacity = new Capacity,
        private readonly CrunchWatch $watch = new CrunchWatch,
        private readonly WeekPlanReader $reader = new WeekPlanReader,
    ) {}

    /**
     * @param  bool  $withFitDates  The crunch screen asks for the proposed dates, the board does not: they cost one
     *                              engine run per late hard task and candidate day, and the board renders often.
     */
    public function for(User $user, ?CarbonImmutable $now = null, bool $seen = false, bool $withFitDates = false, ?PlanningContext $context = null): CrunchView
    {
        $now = UserClock::now($user, $now);
        $context ??= PlanningContext::for($user, $now, $this->reader);
        $forecast = $this->capacity->forecastFor($context);
        $this->watch->record($user, $forecast, $now, $seen);

        $level = $forecast->level();
        $sessionMinutes = (new Rhythm)->workMinutes;
        $missing = $level === null ? 0 : $forecast->missingMinutes();

        if ($level === null) {
            return new CrunchView(null, null, null, null, 0, 0, $forecast->slackMinutes, [], [], null, []);
        }

        $today = $context->today;
        $late = [...$forecast->hardLateTaskIds, ...$forecast->softLateTaskIds];
        $planned = [];
        foreach ($context->plannedTasks() as $p) {
            $planned[$p->id] = $p;
        }
        $windowEnd = max($today, max(array_map(fn (int $id) => $planned[$id]->bindingDeadline() ?? $today, $late)));
        $fitDates = $withFitDates && $forecast->hardLateTaskIds !== []
            ? (new DeadlineFeasibility($context->categories))->fitDates($context->plannedTasks(), $context->slots, $context->now)
            : [];

        return new CrunchView(
            $level,
            $forecast->crunchDate(),
            $forecast->crunchDate() === null ? null : DayLabel::for($forecast->crunchDate(), $now),
            $windowEnd,
            $missing,
            (int) ceil($missing / $sessionMinutes),
            $forecast->slackMinutes,
            $this->days($forecast->days, $windowEnd, $now),
            $this->items($context, $planned, $forecast->slipFirst(), $fitDates, $windowEnd, $now),
            $this->moreTime($context, $planned, $late, $windowEnd, $now),
            EventCategory::forUser($user)->where('pomodoro_enabled', true)->ordered()->get(['id', 'name'])->all(),
        );
    }

    /**
     * @param  list<DayLoad>  $days
     * @return list<array{date: string, label: string, sessions: int, capacity: int, load: int, overloaded: bool}>
     */
    private function days(array $days, string $windowEnd, CarbonImmutable $now): array
    {
        $inWindow = array_values(array_filter($days, fn (DayLoad $d) => $d->date <= $windowEnd));
        if (count($inWindow) > self::MAX_DAYS) {
            $last = end($inWindow);
            $busy = array_values(array_filter($inWindow, fn (DayLoad $d) => ($d->capacitySessions > 0 || $d->loadMinutes > 0) && $d !== $last));
            $inWindow = [...array_slice($busy, 0, self::MAX_DAYS - 1), $last];
        }

        return array_map(fn (DayLoad $d) => [
            'date' => $d->date,
            'label' => CarbonImmutable::parse($d->date, $now->getTimezone())->locale(app()->getLocale())->isoFormat('dd'),
            'sessions' => $d->capacitySessions,
            'capacity' => $d->capacityMinutes,
            'load' => $d->loadMinutes,
            'overloaded' => $d->overloaded,
        ], $inWindow);
    }

    /**
     * @param  array<int, PlannedTask>  $planned
     * @param  list<int>  $slipFirst
     * @param  array<int, ?string>  $fitDates  late hard task id => the first date its deadline can move to so that it fits
     * @return list<CrunchItem>
     */
    private function items(PlanningContext $context, array $planned, array $slipFirst, array $fitDates, string $windowEnd, CarbonImmutable $now): array
    {
        $today = $context->today;
        $rank = array_flip($slipFirst);
        $items = [];

        foreach ($planned as $id => $p) {
            $deadline = $p->bindingDeadline();
            if ($deadline === null || $p->remainingMinutes() <= 0 || max($today, $deadline) > $windowEnd) {
                continue;
            }
            /** @var Task $task */
            $task = $context->tasks[$id];
            $hard = $p->bindingIsHard();
            $items[] = new CrunchItem(
                $task,
                $hard,
                $deadline,
                DayLabel::for($deadline, $now),
                $p->remainingMinutes(),
                CrunchDecisions::smaller($task),
                $hard ? null : CrunchDecisions::softMoveTarget($task, $today),
                // Every soft task in the window is the first thing to let go (R5), not only the ones the
                // engine already moved aside: the engine keeps soft work planned when moving it would not
                // save every hard task, yet letting it go still frees sessions for the hard work.
                ! $hard,
                array_key_exists($id, $fitDates),
                $fitDates[$id] ?? null,
            );
        }

        usort($items, function (CrunchItem $a, CrunchItem $b) use ($rank) {
            return [$a->hard, $rank[$a->task->id] ?? PHP_INT_MAX, $a->deadline, $a->task->id]
                <=> [$b->hard, $rank[$b->task->id] ?? PHP_INT_MAX, $b->deadline, $b->task->id];
        });

        return $items;
    }

    /**
     * @param  array<int, PlannedTask>  $planned
     * @param  list<int>  $late
     * @return ?array{date: string, label: string, start: string, end: string, categoryId: int, sessions: int}
     */
    private function moreTime(PlanningContext $context, array $planned, array $late, string $windowEnd, CarbonImmutable $now): ?array
    {
        $pomodoro = array_filter($context->categories, fn ($c) => $c->pomodoro);
        if ($pomodoro === []) {
            return null;
        }

        // Hard work first: a block that only soft work can use does not solve a hard crunch.
        usort($late, fn (int $a, int $b) => [! $planned[$a]->bindingIsHard(), $planned[$a]->bindingDeadline()] <=> [! $planned[$b]->bindingIsHard(), $planned[$b]->bindingDeadline()]);
        $area = $planned[$late[0]]->areaId;
        $category = current(array_filter($pomodoro, fn ($c) => $c->accepts($area))) ?: current($pomodoro);

        $length = $this->usualLength($context, $category->id);
        $calculator = new SlotCalculator($context->categories);
        $tz = $now->getTimezone();
        $lastDay = min($windowEnd, $now->addDays(self::MORE_TIME_DAYS - 1)->toDateString());

        for ($day = CarbonImmutable::parse($context->today, $tz); $day->toDateString() <= $lastDay; $day = $day->addDay()) {
            $date = $day->toDateString();
            $blocks = $context->blocks[$date] ?? [];
            // 19:00 first; else right before or right after a block of that day, earliest first.
            $around = [];
            foreach ($blocks as $b) {
                $around[] = $b->start->subMinutes($length)->format('H:i');
                $around[] = $b->end->format('H:i');
            }
            sort($around);
            $starts = array_unique([self::PREFERRED_START, ...$around]);

            foreach ($starts as $start) {
                $from = CarbonImmutable::parse("{$date} {$start}", $tz);
                $to = $from->addMinutes($length);
                if ($start < self::EARLIEST_START || $from->lessThan($now->addMinutes(10)) || $to->format('H:i') > self::LATEST_END || ! $to->isSameDay($from)) {
                    continue;
                }
                $overlaps = array_filter($blocks, fn (Block $b) => $b->start->lessThan($to) && $from->lessThan($b->end));
                if ($overlaps !== []) {
                    continue;
                }
                $sessions = count($calculator->slots([new Block($date, $from, $to, $category->id)]));
                if ($sessions === 0) {
                    continue;
                }

                return [
                    'date' => $date,
                    'label' => DayLabel::for($date, $now),
                    'start' => $from->format('H:i'),
                    'end' => $to->format('H:i'),
                    'categoryId' => $category->id,
                    'sessions' => $sessions,
                ];
            }
        }

        return null;
    }

    /** The most common length of the category's blocks in the plan, else an hour. */
    private function usualLength(PlanningContext $context, int $categoryId): int
    {
        $lengths = [];
        foreach ($context->blocks as $blocks) {
            foreach ($blocks as $block) {
                if ($block->categoryId === $categoryId) {
                    $m = (int) round(($block->end->getTimestamp() - $block->start->getTimestamp()) / 60);
                    $lengths[$m] = ($lengths[$m] ?? 0) + 1;
                }
            }
        }
        if ($lengths === []) {
            return self::DEFAULT_BLOCK_MINUTES;
        }
        arsort($lengths);

        return max(25, min(120, (int) array_key_first($lengths)));
    }
}
