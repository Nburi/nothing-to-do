<?php

namespace Tests\Unit\Planning;

use App\Planning\Battery;
use App\Planning\Category;
use App\Planning\DeadlineFeasibility;
use App\Planning\Pick\Pick;
use App\Planning\Pick\PickCandidate;
use App\Planning\Pick\PickEngine;
use App\Planning\Pick\Reason;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\SlotCalculator;
use App\Planning\StartBy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A1 (R1, R5, R10; risks F3, F4): the pick engine. Monday 9 November 2026 in
 * the fixture week (4 sessions every weekday evening, Saturday morning for
 * "Own projects" only).
 */
class PickEngineTest extends TestCase
{
    private const TODAY = '2026-11-09';

    /** The artifact's evening: a math sheet due Thursday, two quick to-dos, a Short without date. */
    private function evening(): array
    {
        return [
            new PickCandidate(new PlannedTask(1, Size::Long, hardDeadline: '2026-11-12', softDeadline: '2026-11-11', areaId: Fixtures::AREA_SCHOOL), position: 0),
            new PickCandidate(new PlannedTask(2, Size::Quick, areaId: Fixtures::AREA_SPORT), position: 1),
            new PickCandidate(new PlannedTask(3, Size::Quick, hardDeadline: '2026-11-12', areaId: Fixtures::AREA_SCHOOL), position: 2),
            new PickCandidate(new PlannedTask(4, Size::Short), position: 3),
        ];
    }

    /** Task 1 has to start today; task 3 can wait until Thursday. */
    private function startBy(): StartBy
    {
        return new StartBy([1 => self::TODAY, 3 => '2026-11-12'], []);
    }

    public function test_a1_low_battery_warms_up_with_two_quick_ones_then_shrinks_the_deadline_task(): void
    {
        $picks = $this->rank($this->evening(), Battery::Low);

        $this->assertSame([3, 2, 1, 4], $this->ids($picks));
        $this->assertSame([Reason::WarmUp, Reason::WarmUp], [$picks[0]->reason, $picks[1]->reason]);
        $this->assertSame(Reason::Shrunk, $picks[2]->reason);
        $this->assertTrue($picks[2]->shrunk);
        $this->assertSame(25, $picks[2]->minutes, 'one session tonight, the rest later');
    }

    public function test_a1_okay_battery_returns_the_deadline_task_first(): void
    {
        $picks = $this->rank($this->evening(), Battery::Okay);

        $this->assertSame(1, $picks[0]->taskId);
        $this->assertSame(Reason::StartsToday, $picks[0]->reason);
        $this->assertFalse($picks[0]->shrunk);
        $this->assertSame(75, $picks[0]->minutes);
        $this->assertSame('2026-11-11', $picks[0]->deadline, 'the soft date binds first');
        $this->assertSame([1, 3, 2, 4], $this->ids($picks));
    }

    public function test_a_skipped_battery_is_okay(): void
    {
        $this->assertSame(Battery::Okay, Battery::fromNullable(null));
        $this->assertSame(Battery::Okay, Battery::fromNullable('tired'));
    }

    public function test_good_battery_puts_the_biggest_deadline_work_first(): void
    {
        $candidates = [
            new PickCandidate(new PlannedTask(1, Size::Short, hardDeadline: '2026-11-13'), position: 0),
            new PickCandidate(new PlannedTask(2, Size::Block, estimatedSessions: 4, hardDeadline: '2026-11-20'), position: 1),
            new PickCandidate(new PlannedTask(3, Size::Quick), position: 2),
        ];
        $startBy = new StartBy([1 => '2026-11-13', 2 => '2026-11-16'], []);

        $picks = $this->rank($candidates, Battery::Good, startBy: $startBy);

        $this->assertSame([2, 1, 3], $this->ids($picks));
        $this->assertSame(Reason::BiggestFirst, $picks[0]->reason);
    }

    public function test_work_that_has_to_start_today_beats_the_battery(): void
    {
        $candidates = [
            new PickCandidate(new PlannedTask(1, Size::Block, estimatedSessions: 6, hardDeadline: '2026-11-30'), position: 0),
            new PickCandidate(new PlannedTask(2, Size::Short, hardDeadline: '2026-11-10'), position: 1),
        ];
        $startBy = new StartBy([1 => '2026-11-20', 2 => self::TODAY], []);

        $this->assertSame([2, 1], $this->ids($this->rank($candidates, Battery::Good, startBy: $startBy)));
    }

    public function test_late_work_comes_first_and_is_never_shrunk(): void
    {
        $candidates = [
            new PickCandidate(new PlannedTask(1, Size::Quick), position: 0),
            new PickCandidate(new PlannedTask(2, Size::Long, hardDeadline: '2026-11-09'), position: 1),
        ];
        $startBy = new StartBy([2 => self::TODAY], [2]);

        $okay = $this->rank($candidates, Battery::Okay, startBy: $startBy);
        $this->assertSame([2, 1], $this->ids($okay));
        $this->assertSame(Reason::Late, $okay[0]->reason);

        $low = $this->rank($candidates, Battery::Low, startBy: $startBy);
        $this->assertSame([1, 2], $this->ids($low), 'the warm-up still goes first');
        $this->assertSame(Reason::Late, $low[1]->reason);
        $this->assertFalse($low[1]->shrunk);
        $this->assertSame(75, $low[1]->minutes);
    }

    public function test_low_battery_without_quick_tasks_starts_small(): void
    {
        $candidates = [
            new PickCandidate(new PlannedTask(1, Size::Long), position: 0),
            new PickCandidate(new PlannedTask(2, Size::Short), position: 1),
        ];

        $picks = $this->rank($candidates, Battery::Low, startBy: new StartBy([], []));

        $this->assertSame([2, 1], $this->ids($picks));
        $this->assertSame(Reason::NoDeadline, $picks[0]->reason);
        $this->assertSame(Reason::Shrunk, $picks[1]->reason);
    }

    public function test_a_shrunk_task_with_less_than_a_session_left_is_not_marked_shrunk(): void
    {
        $candidates = [new PickCandidate(new PlannedTask(1, Size::Long, doneMinutes: 60), position: 0)];

        $pick = $this->rank($candidates, Battery::Low, startBy: new StartBy([], []))[0];

        $this->assertSame(15, $pick->minutes);
        $this->assertFalse($pick->shrunk);
    }

    public function test_the_block_category_filters_by_area(): void
    {
        $categories = Fixtures::categories();

        $deepWork = $this->rank($this->evening(), Battery::Okay, $categories[Fixtures::DEEP_WORK]);
        $this->assertSame([1, 3, 4], $this->ids($deepWork), 'Sport is not linked to Deep work');

        $weekend = $this->rank($this->evening(), Battery::Okay, $categories[Fixtures::WEEKEND]);
        $this->assertSame([4], $this->ids($weekend), 'only the task without area fits Own projects');

        $unlinked = new Category(99, pomodoro: true);
        $this->assertSame([4], $this->ids($this->rank($this->evening(), Battery::Okay, $unlinked)), 'E5');
    }

    public function test_excluded_tasks_are_skipped_and_the_next_one_moves_up(): void
    {
        $picks = $this->rank($this->evening(), Battery::Okay, exclude: [1]);

        $this->assertSame([3, 2, 4], $this->ids($picks));
    }

    public function test_tasks_without_deadline_keep_the_board_order(): void
    {
        $candidates = [
            new PickCandidate(new PlannedTask(5, Size::Short), position: 2),
            new PickCandidate(new PlannedTask(6, Size::Short), position: 0),
            new PickCandidate(new PlannedTask(7, Size::Short), position: 1),
        ];

        $this->assertSame([6, 7, 5], $this->ids($this->rank($candidates, Battery::Okay, startBy: new StartBy([], []))));
    }

    public function test_finished_work_is_not_suggested(): void
    {
        $candidates = [new PickCandidate(new PlannedTask(1, Size::Short, doneMinutes: 25), position: 0)];

        $this->assertSame([], $this->rank($candidates, Battery::Okay, startBy: new StartBy([], [])));
    }

    public function test_nothing_to_pick_is_an_empty_list(): void
    {
        $this->assertSame([], $this->rank([], Battery::Good));
    }

    public function test_the_order_is_stable(): void
    {
        $reversed = array_reverse($this->evening());

        $this->assertSame($this->ids($this->rank($this->evening(), Battery::Okay)), $this->ids($this->rank($reversed, Battery::Okay)));
    }

    // ── start-by dates (DeadlineFeasibility::startBy) ────────────────────

    public function test_start_by_plans_deadline_work_as_late_as_possible(): void
    {
        $startBy = $this->startByFor([new PlannedTask(1, Size::Long, hardDeadline: '2026-11-11')]);

        $this->assertSame('2026-11-11', $startBy->of(1), 'three sessions fit on Wednesday evening');
        $this->assertSame([], $startBy->lateTaskIds);
    }

    public function test_start_by_moves_earlier_when_deadlines_share_the_evenings(): void
    {
        $startBy = $this->startByFor([
            new PlannedTask(1, Size::Long, hardDeadline: '2026-11-11'),
            new PlannedTask(2, Size::Block, estimatedSessions: 4, hardDeadline: '2026-11-11'),
        ]);

        // Seven sessions before Wednesday night: Wednesday holds four, Tuesday the other three.
        $this->assertSame('2026-11-10', $startBy->of(1));
        $this->assertSame('2026-11-11', $startBy->of(2));
    }

    public function test_work_that_cannot_fit_is_late_and_starts_today(): void
    {
        $startBy = $this->startByFor([new PlannedTask(1, Size::Block, estimatedSessions: 10, hardDeadline: '2026-11-10')]);

        $this->assertSame([1], $startBy->lateTaskIds);
        $this->assertSame(self::TODAY, $startBy->of(1));
    }

    public function test_start_by_respects_area_links(): void
    {
        // Sport is linked to no pomodoro category: no session can take it.
        $startBy = $this->startByFor([new PlannedTask(1, Size::Short, hardDeadline: '2026-11-13', areaId: Fixtures::AREA_SPORT)]);

        $this->assertSame([1], $startBy->lateTaskIds);
    }

    public function test_tasks_without_deadline_have_no_start_by(): void
    {
        $this->assertNull($this->startByFor([new PlannedTask(1, Size::Short)])->of(1));
    }

    public function test_the_engine_and_start_by_together_pick_the_artifact_evening(): void
    {
        $tasks = array_map(fn (PickCandidate $c) => $c->task, $this->evening());
        $startBy = $this->startByFor($tasks, now: '2026-11-11 19:00');

        $picks = (new PickEngine)->rank($this->evening(), Battery::Okay, Fixtures::categories()[Fixtures::DEEP_WORK], $startBy, '2026-11-11');

        $this->assertSame(1, $picks[0]->taskId);
        $this->assertSame(Reason::StartsToday, $picks[0]->reason, 'soft Wednesday: it has to happen tonight');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * @param  list<PickCandidate>  $candidates
     * @return list<Pick>
     */
    private function rank(array $candidates, Battery $battery, ?Category $category = null, ?StartBy $startBy = null, array $exclude = []): array
    {
        return (new PickEngine)->rank($candidates, $battery, $category, $startBy ?? $this->startBy(), self::TODAY, $exclude);
    }

    /** @param  list<Pick>  $picks */
    private function ids(array $picks): array
    {
        return array_map(fn (Pick $p) => $p->taskId, $picks);
    }

    /** @param  list<PlannedTask>  $tasks */
    private function startByFor(array $tasks, string $now = '2026-11-09 08:00'): StartBy
    {
        $plan = Fixtures::week();
        $calc = new SlotCalculator(Fixtures::categories());
        $slotsByDate = [];
        for ($d = CarbonImmutable::parse('2026-11-09'); $d->lte(CarbonImmutable::parse('2026-11-22')); $d = $d->addDay()) {
            $slotsByDate[$d->toDateString()] = $calc->slots($plan->blocksOn($d->toDateString()));
        }

        return (new DeadlineFeasibility(Fixtures::categories()))->startBy($tasks, $slotsByDate, CarbonImmutable::parse($now, Fixtures::TZ));
    }
}
