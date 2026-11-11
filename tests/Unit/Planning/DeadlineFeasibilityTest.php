<?php

namespace Tests\Unit\Planning;

use App\Planning\DeadlineFeasibility;
use App\Planning\FeasibilityResult;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\SlotCalculator;
use App\Planning\WeekPlan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * R5, R10, risk C4: can every deadline still be met with the sessions that are left?
 * Fixture week from Monday 9 November 2026: 4 sessions on each weekday evening,
 * 4 on Saturday morning (area "Own projects" only), none on Sunday.
 */
class DeadlineFeasibilityTest extends TestCase
{
    public function test_work_that_fits_finishes_on_time(): void
    {
        $result = $this->check([
            new PlannedTask(1, Size::Long, hardDeadline: '2026-11-10'),
            new PlannedTask(2, Size::Short, softDeadline: '2026-11-09'),
        ]);

        $this->assertSame([], $result->lateTaskIds);
        $this->assertSame('2026-11-09', $result->finishesOn[2]);
        $this->assertSame('2026-11-09', $result->finishesOn[1]);
        $this->assertNull($result->firstLateDeadline);
    }

    public function test_a_task_bigger_than_any_block_is_spread_over_several_days(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Block, estimatedSessions: 7, hardDeadline: '2026-11-13')]);

        $this->assertSame('2026-11-10', $result->finishesOn[1]);
        $this->assertSame(['2026-11-09' => 100, '2026-11-10' => 75], $result->minutesByDate[1]);
    }

    public function test_quick_tasks_share_one_session(): void
    {
        $result = $this->check([
            new PlannedTask(1, Size::Quick, hardDeadline: '2026-11-09'),
            new PlannedTask(2, Size::Quick, hardDeadline: '2026-11-09'),
        ]);

        $this->assertSame([1 => ['2026-11-09' => 10], 2 => ['2026-11-09' => 10]], $result->minutesByDate);
        $this->assertSame(1, $result->sessionsUsedOn('2026-11-09'));
    }

    public function test_in_a_crunch_the_soft_deadline_slips_first(): void
    {
        // Mon–Wed hold 12 sessions. The hard task needs 10 by Wednesday, the soft one 4 by Tuesday.
        // Earliest-deadline-first would make the hard task late; soft work gives way instead.
        $result = $this->check([
            new PlannedTask(1, Size::Block, estimatedSessions: 10, hardDeadline: '2026-11-11'),
            new PlannedTask(2, Size::Block, estimatedSessions: 4, softDeadline: '2026-11-10'),
        ]);

        $this->assertSame([2], $result->lateTaskIds);
        $this->assertSame([2], $result->slippedSoftTaskIds);
        $this->assertSame('2026-11-11', $result->finishesOn[1]);
        $this->assertSame('2026-11-10', $result->firstLateDeadline);
        $this->assertSame(50, $result->missingMinutes[2]);
    }

    public function test_hard_work_that_cannot_fit_is_late_with_the_missing_minutes(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Block, estimatedSessions: 9, hardDeadline: '2026-11-10')]);

        $this->assertSame([1], $result->lateTaskIds);
        $this->assertSame([], $result->slippedSoftTaskIds);
        $this->assertSame(25, $result->missingMinutes[1]);
        $this->assertNull($result->finishesOn[1]);
    }

    public function test_tasks_only_use_sessions_of_categories_linked_to_their_area(): void
    {
        // Saturday's block only takes "Own projects". A school task due Sunday cannot use it.
        $result = $this->check([
            new PlannedTask(1, Size::Short, hardDeadline: '2026-11-15', areaId: Fixtures::AREA_SCHOOL),
            new PlannedTask(2, Size::Short, hardDeadline: '2026-11-15', areaId: Fixtures::AREA_OWN),
        ], now: '2026-11-14 08:00');

        $this->assertSame([1], $result->lateTaskIds);
        $this->assertSame('2026-11-14', $result->finishesOn[2]);
    }

    public function test_an_area_no_category_is_linked_to_never_fits(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Quick, hardDeadline: '2026-11-13', areaId: Fixtures::AREA_SPORT)]);

        $this->assertSame([1], $result->lateTaskIds);
    }

    public function test_a_task_without_an_area_fits_any_pomodoro_session(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Short, hardDeadline: '2026-11-15')], now: '2026-11-14 08:00');

        $this->assertSame('2026-11-14', $result->finishesOn[1]);
    }

    public function test_sessions_that_already_started_are_not_counted(): void
    {
        // At 20:10 on Monday only the 20:30 and 21:00 sessions are left.
        $result = $this->check([new PlannedTask(1, Size::Long, hardDeadline: '2026-11-09')], now: '2026-11-09 20:10');

        $this->assertSame([1], $result->lateTaskIds);
        $this->assertSame(25, $result->missingMinutes[1]);
    }

    public function test_a_deadline_already_in_the_past_is_late(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Quick, softDeadline: '2026-11-06')]);

        $this->assertSame([1], $result->lateTaskIds);
        $this->assertSame('2026-11-06', $result->firstLateDeadline);
    }

    public function test_undated_tasks_are_ignored(): void
    {
        $result = $this->check([new PlannedTask(1, Size::Block, estimatedSessions: 40)]);

        $this->assertSame([], $result->lateTaskIds);
        $this->assertSame([], $result->finishesOn);
    }

    /** @param  list<PlannedTask>  $tasks */
    public function test_too_much_hard_work_never_makes_an_earlier_hard_deadline_late(): void
    {
        // Monday 20:10: 2 sessions left tonight, 4 on Tuesday, 4 on Wednesday. Found by EveningFlowTest (step 1.8):
        // planning hard work as late as possible used to push the Tuesday task out as well.
        $result = $this->check([
            new PlannedTask(1, Size::Long, hardDeadline: '2026-11-10'),
            new PlannedTask(2, Size::Block, estimatedSessions: 12, hardDeadline: '2026-11-11'),
        ], now: '2026-11-09 20:10');

        $this->assertSame([2], $result->lateTaskIds, 'only the task that really cannot fit');
        $this->assertSame('2026-11-10', $result->finishesOn[1]);
    }

    public function test_soft_work_still_gives_way_when_that_keeps_every_hard_deadline(): void
    {
        $result = $this->check([
            new PlannedTask(1, Size::Block, estimatedSessions: 10, hardDeadline: '2026-11-11'),
            new PlannedTask(2, Size::Block, estimatedSessions: 4, softDeadline: '2026-11-10'),
            new PlannedTask(3, Size::Short, hardDeadline: '2026-11-09'),
        ]);

        $this->assertSame([2], $result->lateTaskIds);
        $this->assertSame([2], $result->slippedSoftTaskIds);
    }

    private function check(array $tasks, string $now = '2026-11-09 08:00', ?WeekPlan $plan = null): FeasibilityResult
    {
        $plan ??= Fixtures::week();
        $calc = new SlotCalculator(Fixtures::categories());
        $slotsByDate = [];
        for ($d = CarbonImmutable::parse('2026-11-09'); $d->lte(CarbonImmutable::parse('2026-11-22')); $d = $d->addDay()) {
            $slotsByDate[$d->toDateString()] = $calc->slots($plan->blocksOn($d->toDateString()));
        }

        return (new DeadlineFeasibility(Fixtures::categories()))
            ->check($tasks, $slotsByDate, CarbonImmutable::parse($now, Fixtures::TZ));
    }
}
