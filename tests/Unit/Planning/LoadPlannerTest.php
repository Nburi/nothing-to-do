<?php

namespace Tests\Unit\Planning;

use App\Planning\Crunch\Forecast;
use App\Planning\Crunch\Level;
use App\Planning\Crunch\LoadPlanner;
use App\Planning\DayLoad;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\SlotCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Step 1.4 (A6 continued, R5, R10, risk C4): load forecast per day in the
 * fixture week from Monday 9 November 2026: 4 sessions every weekday
 * evening, 4 on Saturday morning (Own projects only), none on Sunday.
 */
class LoadPlannerTest extends TestCase
{
    public function test_capacity_is_the_sessions_in_pomodoro_blocks_per_day(): void
    {
        $forecast = $this->forecast([]);

        $this->assertSame([4, 4, 4, 4, 4, 4, 0], array_map(fn (DayLoad $d) => $d->capacitySessions, array_slice($forecast->days, 0, 7)));
        $this->assertSame(100, $forecast->days[0]->capacityMinutes);
        $this->assertFalse($forecast->hasCrunch());
        $this->assertNull($forecast->slackMinutes, 'no deadlines, no tightest point');
    }

    public function test_today_counts_only_the_sessions_that_have_not_started(): void
    {
        $forecast = $this->forecast([], now: '2026-11-09 20:10');

        $this->assertSame(2, $forecast->day('2026-11-09')->capacitySessions);
    }

    public function test_deadline_work_is_load_on_the_days_it_is_planned(): void
    {
        $forecast = $this->forecast([
            new PlannedTask(1, Size::Long, hardDeadline: '2026-11-10'),
            new PlannedTask(2, Size::Short), // no deadline: not load
        ]);

        $this->assertSame(75, $forecast->day('2026-11-09')->loadMinutes);
        $this->assertSame(0, $forecast->day('2026-11-10')->loadMinutes);
        $this->assertSame([], $forecast->overloadedDays());
    }

    public function test_work_that_does_not_fit_overloads_its_deadline_day(): void
    {
        $forecast = $this->forecast([new PlannedTask(1, Size::Block, estimatedSessions: 10, hardDeadline: '2026-11-10')]);

        $tuesday = $forecast->day('2026-11-10');
        $this->assertTrue($tuesday->overloaded);
        $this->assertSame(150, $tuesday->loadMinutes);
        $this->assertSame(50, $tuesday->overByMinutes());
        $this->assertFalse($forecast->day('2026-11-09')->overloaded, 'Monday is full, not over');
        $this->assertSame('2026-11-10', $forecast->crunchDate());
        $this->assertSame(Level::Hard, $forecast->level());
        $this->assertSame(50, $forecast->missingMinutes(Level::Hard));
        $this->assertSame(-50, $forecast->slackMinutes);
    }

    public function test_a_soft_only_crunch_is_soft_and_offered_to_slip(): void
    {
        $forecast = $this->forecast([new PlannedTask(1, Size::Block, estimatedSessions: 6, softDeadline: '2026-11-09')]);

        $this->assertSame(Level::Soft, $forecast->level());
        $this->assertSame([1], $forecast->slipFirst());
        $this->assertSame(0, $forecast->missingMinutes(Level::Hard));
    }

    public function test_soft_deadlines_give_way_to_hard_ones_first(): void
    {
        // Mon–Wed hold 12 sessions: the hard task needs 10 by Wednesday, the soft one 4 by Tuesday.
        $forecast = $this->forecast([
            new PlannedTask(1, Size::Block, estimatedSessions: 10, hardDeadline: '2026-11-11'),
            new PlannedTask(2, Size::Block, estimatedSessions: 4, softDeadline: '2026-11-10'),
        ]);

        $this->assertSame([], $forecast->hardLateTaskIds);
        $this->assertSame(Level::Soft, $forecast->level());
        $this->assertSame([2], $forecast->slipFirst());
    }

    public function test_an_overdue_deadline_lands_on_today(): void
    {
        $forecast = $this->forecast([new PlannedTask(1, Size::Short, hardDeadline: '2026-11-05')]);

        $this->assertSame(25, $forecast->day('2026-11-09')->loadMinutes);
        $this->assertTrue($forecast->day('2026-11-09')->overloaded);
        $this->assertSame(Level::Hard, $forecast->level());
    }

    public function test_slack_is_the_room_at_the_tightest_deadline(): void
    {
        $forecast = $this->forecast([
            new PlannedTask(1, Size::Short, hardDeadline: '2026-11-09'),
            new PlannedTask(2, Size::Block, estimatedSessions: 7, hardDeadline: '2026-11-10'),
        ]);

        // Monday: 100 − 25 = 75 room. By Tuesday: 200 − 200 = 0 room. The tightest point wins.
        $this->assertSame(0, $forecast->slackMinutes);
        $this->assertFalse($forecast->hasCrunch());
    }

    public function test_work_in_an_area_no_block_takes_is_late(): void
    {
        $forecast = $this->forecast([new PlannedTask(1, Size::Short, hardDeadline: '2026-11-13', areaId: Fixtures::AREA_SPORT)]);

        $this->assertSame(Level::Hard, $forecast->level());
        $this->assertTrue($forecast->day('2026-11-13')->overloaded);
    }

    private function forecast(array $tasks, string $now = '2026-11-09 08:00'): Forecast
    {
        $plan = Fixtures::week();
        $calc = new SlotCalculator(Fixtures::categories());
        $slots = [];
        for ($d = CarbonImmutable::parse('2026-11-09'); $d->lte(CarbonImmutable::parse('2026-11-22')); $d = $d->addDay()) {
            $slots[$d->toDateString()] = $calc->slots($plan->blocksOn($d->toDateString()));
        }

        return (new LoadPlanner(Fixtures::categories()))->forecast($tasks, $slots, CarbonImmutable::parse($now, Fixtures::TZ));
    }
}
