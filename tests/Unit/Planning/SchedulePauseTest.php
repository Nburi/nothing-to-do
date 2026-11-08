<?php

namespace Tests\Unit\Planning;

use App\Planning\OneOffBlock;
use App\Planning\SlotCalculator;
use PHPUnit\Framework\TestCase;

/** A4 (R4): a holiday suppresses template blocks; one day inside can be switched back on. */
class SchedulePauseTest extends TestCase
{
    public function test_a_paused_range_suppresses_every_template_block_inside_it(): void
    {
        $plan = Fixtures::week()->withPausedRange('2026-11-09', '2026-11-15');

        foreach (['2026-11-09', '2026-11-11', '2026-11-14', '2026-11-15'] as $date) {
            $this->assertSame([], $plan->blocksOn($date), $date);
        }
        // Outside the range the week is normal again.
        $this->assertCount(2, $plan->blocksOn('2026-11-16'));
    }

    public function test_one_day_inside_a_pause_can_be_switched_back_on(): void
    {
        $plan = Fixtures::week()
            ->withPausedRange('2026-11-09', '2026-11-15')
            ->withoutPause('2026-11-11');

        $this->assertCount(2, $plan->blocksOn('2026-11-11'));
        $this->assertSame([], $plan->blocksOn('2026-11-10'));
        $this->assertSame([], $plan->blocksOn('2026-11-12'));
    }

    public function test_one_off_appointments_still_show_on_a_paused_day(): void
    {
        // Same rule as the old app (ScheduleEvent::scopeVisible): a pause only hides the template blocks.
        $plan = Fixtures::week()
            ->withPausedRange('2026-11-09', '2026-11-09')
            ->withOneOff(new OneOffBlock('2026-11-09', '17:00', 30, null, 'Zahnarzt'));

        $this->assertCount(1, $plan->blocksOn('2026-11-09'));
        $this->assertSame('Zahnarzt', $plan->blocksOn('2026-11-09')[0]->title);
    }

    public function test_a_paused_day_has_no_capacity(): void
    {
        $plan = Fixtures::week()->withPausedRange('2026-11-09', '2026-11-09');
        $calculator = new SlotCalculator(Fixtures::categories());

        $this->assertSame(0, $calculator->capacity($plan->blocksOn('2026-11-09')));
        $this->assertSame(4, $calculator->capacity($plan->blocksOn('2026-11-10')));
    }
}
