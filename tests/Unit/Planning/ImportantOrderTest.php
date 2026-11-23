<?php

namespace Tests\Unit\Planning;

use App\Planning\Battery;
use App\Planning\Pick\Pick;
use App\Planning\Pick\PickCandidate;
use App\Planning\Pick\PickEngine;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\StartBy;
use PHPUnit\Framework\TestCase;

/**
 * A61 (R5, step 4.6): what comes next is decided by deadline tiers first (hard before soft), then
 * by „Wichtig", then by the user's board order. Monday 9 November 2026; `position` is the place
 * on the board (lower = higher up).
 *
 * Tests marked "guard" describe a rule the old order already kept; they stay to catch an
 * implementation that lets „Wichtig" jump over a deadline.
 */
class ImportantOrderTest extends TestCase
{
    private const TODAY = '2026-11-09';

    // ── the three levels ─────────────────────────────────────────────────

    public function test_wichtig_comes_before_the_board_order(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0),
            $this->candidate(2, position: 1, important: true),
            $this->candidate(3, position: 2),
        ]);

        $this->assertSame([2, 1, 3], $this->ids($picks));
    }

    public function test_guard_a_deadline_comes_before_wichtig(): void
    {
        // The dated task is far away and plain, the undated one is marked and sits on top of the board.
        $picks = $this->rank([
            $this->candidate(1, position: 0, important: true),
            $this->candidate(2, position: 1, soft: '2026-12-24'),
        ]);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_guard_hard_comes_before_soft_even_when_the_soft_one_is_marked(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, soft: '2026-11-20', important: true),
            $this->candidate(2, position: 1, hard: '2026-11-20'),
        ]);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_guard_an_earlier_deadline_comes_before_a_marked_task_with_a_later_one(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, hard: '2026-11-20', important: true),
            $this->candidate(2, position: 1, hard: '2026-11-12'),
        ]);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_guard_work_that_is_late_or_has_to_start_today_comes_before_wichtig(): void
    {
        $startBy = new StartBy([1 => self::TODAY, 2 => self::TODAY], [1]);
        $picks = $this->rank([
            $this->candidate(3, position: 0, important: true),
            $this->candidate(2, position: 1, hard: '2026-11-10'),
            $this->candidate(1, position: 2, size: Size::Long, hard: '2026-11-09'),
        ], startBy: $startBy);

        $this->assertSame([1, 2, 3], $this->ids($picks), 'late first, then has-to-start-today, then the marked task');
    }

    // ── ties ─────────────────────────────────────────────────────────────

    public function test_wichtig_decides_between_the_same_deadline(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, hard: '2026-11-20'),
            $this->candidate(2, position: 1, hard: '2026-11-20', important: true),
        ]);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_two_marked_tasks_keep_the_board_order(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 3, important: true),
            $this->candidate(2, position: 1, important: true),
            $this->candidate(3, position: 2),
        ]);

        $this->assertSame([2, 1, 3], $this->ids($picks));
    }

    public function test_guard_two_marked_tasks_with_the_same_place_end_on_the_id(): void
    {
        $picks = $this->rank([
            $this->candidate(9, position: 0, important: true),
            $this->candidate(4, position: 0, important: true),
        ]);

        $this->assertSame([4, 9], $this->ids($picks));
    }

    public function test_the_order_does_not_depend_on_the_order_the_tasks_come_in(): void
    {
        $tasks = [
            $this->candidate(1, position: 0),
            $this->candidate(2, position: 1, important: true),
            $this->candidate(3, position: 2, hard: '2026-11-12'),
            $this->candidate(4, position: 3, important: true, soft: '2026-11-12'),
        ];

        $this->assertSame($this->ids($this->rank($tasks)), $this->ids($this->rank(array_reverse($tasks))));
        $this->assertSame([3, 4, 2, 1], $this->ids($this->rank($tasks)), 'hard 12th, soft 12th (marked), marked undated, plain undated');
    }

    // ── the battery does not override the marker ─────────────────────────

    public function test_a_marked_big_task_comes_before_a_small_plain_one_on_an_okay_evening(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, size: Size::Quick),
            $this->candidate(2, position: 1, size: Size::Long, important: true),
        ]);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_a_marked_small_task_comes_before_a_big_plain_one_on_a_good_evening(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, size: Size::Long),
            $this->candidate(2, position: 1, size: Size::Short, important: true),
        ], Battery::Good);

        $this->assertSame([2, 1], $this->ids($picks));
    }

    public function test_on_a_low_evening_a_marked_big_task_goes_first_shrunk_to_one_session(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, size: Size::Long, important: true),
            $this->candidate(2, position: 1, size: Size::Short),
        ], Battery::Low);

        $this->assertSame([1, 2], $this->ids($picks));
        $this->assertTrue($picks[0]->shrunk, 'tired: one session tonight, the rest comes later');
        $this->assertSame(25, $picks[0]->minutes);
    }

    public function test_the_warm_up_takes_the_marked_quick_tasks_first(): void
    {
        $picks = $this->rank([
            $this->candidate(1, position: 0, size: Size::Quick),
            $this->candidate(2, position: 1, size: Size::Quick, important: true),
            $this->candidate(3, position: 2, size: Size::Quick, important: true),
        ], Battery::Low);

        $this->assertSame([2, 3, 1], $this->ids($picks));
        $this->assertSame(['warm_up', 'warm_up', 'quick_one'], array_map(fn (Pick $p) => $p->reason->value, $picks));
    }

    // ── why ──────────────────────────────────────────────────────────────

    public function test_a_marked_task_without_a_date_says_it_was_marked(): void
    {
        $picks = $this->rank([$this->candidate(1, position: 0, size: Size::Quick, important: true)]);

        $this->assertSame('important', $picks[0]->reason->value, 'not "quick_one": the marker is the better reason');
    }

    public function test_a_marked_task_with_a_deadline_keeps_the_deadline_as_its_reason(): void
    {
        $picks = $this->rank([$this->candidate(1, position: 0, soft: '2026-11-20', important: true)]);

        $this->assertSame('next_deadline', $picks[0]->reason->value);
        $this->assertSame('2026-11-20', $picks[0]->deadline);
    }

    public function test_a_plain_task_keeps_its_old_reason(): void
    {
        $picks = $this->rank([$this->candidate(1, position: 0)]);

        $this->assertSame('no_deadline', $picks[0]->reason->value);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function candidate(int $id, int $position, Size $size = Size::Short, ?string $hard = null, ?string $soft = null, bool $important = false): PickCandidate
    {
        return new PickCandidate(
            new PlannedTask($id, $size, hardDeadline: $hard, softDeadline: $soft, important: $important),
            $position,
        );
    }

    /**
     * @param  list<PickCandidate>  $candidates
     * @return list<Pick>
     */
    private function rank(array $candidates, Battery $battery = Battery::Okay, ?StartBy $startBy = null): array
    {
        return (new PickEngine)->rank($candidates, $battery, null, $startBy ?? new StartBy([], []), self::TODAY);
    }

    /** @param  list<Pick>  $picks */
    private function ids(array $picks): array
    {
        return array_map(fn (Pick $p) => $p->taskId, $picks);
    }
}
