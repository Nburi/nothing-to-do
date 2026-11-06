<?php

namespace Tests\Unit\Planning;

use App\Planning\DeadlineFeasibility;
use App\Planning\PlannedTask;
use App\Planning\Size;
use PHPUnit\Framework\TestCase;

/** A2 (R5): hard beats soft; equal dates fall back to the smaller size. No priority field. */
class StartByOrderTest extends TestCase
{
    public function test_the_earlier_deadline_comes_first_whatever_its_kind(): void
    {
        $order = $this->order([
            new PlannedTask(1, Size::Short, hardDeadline: '2026-11-13'),
            new PlannedTask(2, Size::Short, softDeadline: '2026-11-11'),
        ]);

        $this->assertSame([2, 1], $order);
    }

    public function test_on_the_same_date_a_hard_deadline_beats_a_soft_one(): void
    {
        $order = $this->order([
            new PlannedTask(1, Size::Quick, softDeadline: '2026-11-12'),
            new PlannedTask(2, Size::Long, hardDeadline: '2026-11-12'),
        ]);

        $this->assertSame([2, 1], $order);
    }

    public function test_on_the_same_date_and_kind_the_smaller_size_comes_first(): void
    {
        $order = $this->order([
            new PlannedTask(1, Size::Long, hardDeadline: '2026-11-12'),
            new PlannedTask(2, Size::Quick, hardDeadline: '2026-11-12'),
            new PlannedTask(3, Size::Short, hardDeadline: '2026-11-12'),
        ]);

        $this->assertSame([2, 3, 1], $order);
    }

    public function test_a_task_with_both_deadlines_is_bound_by_the_earlier_one(): void
    {
        $task = new PlannedTask(1, Size::Short, hardDeadline: '2026-11-20', softDeadline: '2026-11-12');

        $this->assertSame('2026-11-12', $task->bindingDeadline());
        $this->assertFalse($task->bindingIsHard());

        $same = new PlannedTask(2, Size::Short, hardDeadline: '2026-11-12', softDeadline: '2026-11-12');
        $this->assertTrue($same->bindingIsHard());
    }

    public function test_tasks_without_a_deadline_are_not_part_of_the_order(): void
    {
        $order = $this->order([
            new PlannedTask(1, Size::Quick),
            new PlannedTask(2, Size::Short, softDeadline: '2026-11-30'),
        ]);

        $this->assertSame([2], $order);
    }

    public function test_the_order_is_stable_for_identical_tasks(): void
    {
        $order = $this->order([
            new PlannedTask(7, Size::Short, hardDeadline: '2026-11-12'),
            new PlannedTask(3, Size::Short, hardDeadline: '2026-11-12'),
        ]);

        $this->assertSame([3, 7], $order, 'ties end on the id so the order never flickers');
    }

    /** @param  list<PlannedTask>  $tasks */
    private function order(array $tasks): array
    {
        return array_map(fn (PlannedTask $t) => $t->id, DeadlineFeasibility::order($tasks));
    }
}
