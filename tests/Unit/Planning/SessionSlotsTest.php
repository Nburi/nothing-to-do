<?php

namespace Tests\Unit\Planning;

use App\Planning\Category;
use App\Planning\OneOffBlock;
use App\Planning\OneOffChange;
use App\Planning\SessionSlot;
use App\Planning\SlotCalculator;
use App\Planning\WeekPlan;
use PHPUnit\Framework\TestCase;

/** R9, R10, risk C4: where 25 minute sessions fit, at the edges. */
class SessionSlotsTest extends TestCase
{
    public function test_a_two_hour_pomodoro_block_holds_four_sessions_with_short_breaks(): void
    {
        $slots = $this->slots(Fixtures::week(), '2026-11-09');

        $this->assertSame(['19:30-19:55', '20:00-20:25', '20:30-20:55', '21:00-21:25'], $this->times($slots));
        $this->assertSame([Fixtures::DEEP_WORK], array_values(array_unique(array_map(fn (SessionSlot $s) => $s->categoryId, $slots))));
    }

    public function test_blocks_of_categories_without_the_pomodoro_switch_add_no_capacity(): void
    {
        // Tuesday: School 8h and Training 1.5h are fixed blocks; only Deep work counts.
        $this->assertCount(4, $this->slots(Fixtures::week(), '2026-11-10'));
        $this->assertSame([], $this->slots(Fixtures::week(), '2026-11-15'));
    }

    public function test_every_fourth_break_is_long(): void
    {
        $plan = new WeekPlan(Fixtures::TZ, [], [], [new OneOffBlock('2026-11-15', '10:00', 180, Fixtures::WEEKEND)]);

        // 4 sessions, then 15 min instead of 5 before the fifth.
        $this->assertSame(
            ['10:00-10:25', '10:30-10:55', '11:00-11:25', '11:30-11:55', '12:10-12:35'],
            $this->times($this->slots($plan, '2026-11-15')),
        );
    }

    public function test_the_long_break_counter_runs_across_blocks_of_the_same_day(): void
    {
        $plan = new WeekPlan(Fixtures::TZ, [], [], [
            new OneOffBlock('2026-11-15', '08:00', 120, Fixtures::WEEKEND),
            // Starts right when block one ends, but the long break after session four
            // pushes the next session to 10:10, which no longer fits before 10:30.
            new OneOffBlock('2026-11-15', '10:00', 30, Fixtures::DEEP_WORK),
        ]);

        $this->assertCount(4, $this->slots($plan, '2026-11-15'));
    }

    public function test_a_block_shorter_than_one_session_has_no_capacity(): void
    {
        $plan = new WeekPlan(Fixtures::TZ, [], [], [new OneOffBlock('2026-11-15', '10:00', 24, Fixtures::WEEKEND)]);

        $this->assertSame([], $this->slots($plan, '2026-11-15'));
    }

    public function test_a_fixed_appointment_inside_a_pomodoro_block_cuts_it(): void
    {
        $plan = Fixtures::week()->withOneOff(new OneOffBlock('2026-11-09', '20:00', 30, null, 'Zahnarzt'));

        $this->assertSame(['19:30-19:55', '20:30-20:55', '21:00-21:25'], $this->times($this->slots($plan, '2026-11-09')));
    }

    public function test_overlapping_pomodoro_blocks_are_never_counted_twice(): void
    {
        $plan = Fixtures::week()->withOneOff(new OneOffBlock('2026-11-09', '20:30', 90, Fixtures::WEEKEND));

        $slots = $this->slots($plan, '2026-11-09');

        // 19:30–22:00 is one stretch of work time: 4 sessions, the long break, then too late for a fifth.
        $this->assertSame(['19:30-19:55', '20:00-20:25', '20:30-20:55', '21:00-21:25'], $this->times($slots));
        for ($i = 1; $i < count($slots); $i++) {
            $this->assertTrue($slots[$i]->start >= $slots[$i - 1]->end, 'slots must not overlap');
        }
        // The overlap belongs to the block that started first.
        $this->assertSame(Fixtures::DEEP_WORK, $slots[2]->categoryId);
    }

    public function test_a_cancelled_pomodoro_block_has_no_capacity(): void
    {
        $plan = Fixtures::week()->withChange(new OneOffChange(103, '2026-11-09', cancelled: true));

        $this->assertSame([], $this->slots($plan, '2026-11-09'));
    }

    public function test_an_unknown_category_counts_as_a_fixed_block(): void
    {
        $plan = new WeekPlan(Fixtures::TZ, [], [], [new OneOffBlock('2026-11-15', '10:00', 120, 999)]);

        // E.g. a category deleted while a one-off block still points at it: never invent work time.
        $this->assertSame([], $this->slots($plan, '2026-11-15'));
    }

    /** @return list<SessionSlot> */
    private function slots(WeekPlan $plan, string $date): array
    {
        return (new SlotCalculator(Fixtures::categories()))->slots($plan->blocksOn($date));
    }

    /** @param  list<SessionSlot>  $slots */
    private function times(array $slots): array
    {
        return array_map(fn (SessionSlot $s) => $s->start->format('H:i').'-'.$s->end->format('H:i'), $slots);
    }
}
