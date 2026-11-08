<?php

namespace Tests\Unit\Planning;

use App\Planning\OneOffBlock;
use App\Planning\SlotCalculator;
use App\Planning\WeekPlan;
use PHPUnit\Framework\TestCase;

/**
 * A5 (R4, risk C2): block and session counts are right across the DST changes in
 * Europe/Zurich. Times are stored as local HH:MM; durations are real minutes.
 * 2026-10-25: 03:00 CEST becomes 02:00 CET (the night is one hour longer).
 * 2027-03-28: 02:00 CET becomes 03:00 CEST (the night is one hour shorter).
 */
class FreeSlotDstTest extends TestCase
{
    public function test_an_evening_block_keeps_its_local_time_on_the_october_change(): void
    {
        $plan = new WeekPlan(Fixtures::TZ, [], [], [
            new OneOffBlock('2026-10-24', '19:30', 120, Fixtures::DEEP_WORK),
            new OneOffBlock('2026-10-25', '19:30', 120, Fixtures::DEEP_WORK),
        ]);
        $calc = new SlotCalculator(Fixtures::categories());

        $saturday = $calc->slots($plan->blocksOn('2026-10-24'));
        $sunday = $calc->slots($plan->blocksOn('2026-10-25'));

        $this->assertSame('19:30', $saturday[0]->start->format('H:i'));
        $this->assertSame('19:30', $sunday[0]->start->format('H:i'));
        $this->assertSame('17:30', $saturday[0]->start->utc()->format('H:i'), 'CEST is UTC+2');
        $this->assertSame('18:30', $sunday[0]->start->utc()->format('H:i'), 'CET is UTC+1');
        $this->assertCount(4, $saturday);
        $this->assertCount(4, $sunday);
    }

    public function test_a_block_across_the_october_change_is_one_hour_longer(): void
    {
        // 01:30 CEST to 03:30 CET is three real hours.
        $plan = new WeekPlan(Fixtures::TZ, [], [], [new OneOffBlock('2026-10-25', '01:30', 120, Fixtures::WEEKEND)]);

        $blocks = $plan->blocksOn('2026-10-25');
        $this->assertSame(180, $blocks[0]->realMinutes());
        $this->assertSame('03:30', $blocks[0]->end->format('H:i'));
        $this->assertCount(5, (new SlotCalculator(Fixtures::categories()))->slots($blocks));
    }

    public function test_a_block_across_the_march_change_is_one_hour_shorter(): void
    {
        // 01:30 CET to 03:30 CEST is one real hour.
        $plan = new WeekPlan(Fixtures::TZ, [], [], [new OneOffBlock('2027-03-28', '01:30', 120, Fixtures::WEEKEND)]);

        $blocks = $plan->blocksOn('2027-03-28');
        $this->assertSame(60, $blocks[0]->realMinutes());
        $this->assertCount(2, (new SlotCalculator(Fixtures::categories()))->slots($blocks));
    }

    public function test_weekly_capacity_over_the_october_change_week(): void
    {
        // Mon 19 – Sun 25 October 2026: 5 weekdays x 4 sessions + Saturday 4 = 24.
        $plan = Fixtures::week();
        $calc = new SlotCalculator(Fixtures::categories());

        $total = 0;
        foreach (['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'] as $date) {
            $total += $calc->capacity($plan->blocksOn($date));
        }

        $this->assertSame(24, $total);
    }

    public function test_a_template_lands_on_the_right_weekday_in_the_change_week(): void
    {
        // Sunday 25 October has no template blocks; Monday 26 is a normal weekday.
        $plan = Fixtures::week();

        $this->assertSame([], $plan->blocksOn('2026-10-25'));
        $this->assertSame('19:30', $plan->blocksOn('2026-10-26')[1]->start->format('H:i'));
        $this->assertSame('18:30', $plan->blocksOn('2026-10-26')[1]->start->utc()->format('H:i'));
    }
}
