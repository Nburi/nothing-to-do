<?php

namespace Tests\Unit\Planning;

use App\Planning\OneOffBlock;
use App\Planning\OneOffChange;
use App\Planning\SlotCalculator;
use PHPUnit\Framework\TestCase;

/** B6 (R4): a paused day shows the blocks it would have had as ghosts, and a ghost never counts as work time. */
class PauseGhostTest extends TestCase
{
    /** @return list<string> "HH:MM–HH:MM template" of the ghosts */
    private function ghosts($plan, string $date): array
    {
        return array_map(fn ($b) => $b->start->format('H:i').'–'.$b->end->format('H:i').' #'.$b->templateId, $plan->ghostsOn($date));
    }

    public function test_a_paused_day_has_the_blocks_it_would_have_had_as_ghosts(): void
    {
        $plan = Fixtures::week()->withPausedRange('2026-11-10', '2026-11-10'); // Tuesday

        $this->assertSame(['08:00–16:00 #101', '18:00–19:30 #102', '19:30–21:30 #103'], $this->ghosts($plan, '2026-11-10'));
    }

    public function test_a_day_that_is_not_paused_has_no_ghosts(): void
    {
        $this->assertSame([], Fixtures::week()->ghostsOn('2026-11-10'));
        $this->assertSame([], Fixtures::week()->withPausedRange('2026-11-10', '2026-11-10')->withoutPause('2026-11-10')->ghostsOn('2026-11-10'));
    }

    public function test_ghosts_never_reach_the_blocks_or_the_capacity(): void
    {
        $plan = Fixtures::week()->withPausedRange('2026-11-10', '2026-11-10');
        $calculator = new SlotCalculator(Fixtures::categories());

        $this->assertNotSame([], $plan->ghostsOn('2026-11-10'));
        $this->assertSame([], $plan->blocksOn('2026-11-10'));
        $this->assertSame([], $calculator->slots($plan->blocksOn('2026-11-10')), 'a paused day produces no sessions');
    }

    public function test_a_one_week_change_shows_in_the_ghost_and_a_cancelled_block_leaves_none(): void
    {
        $plan = Fixtures::week()
            ->withChange(new OneOffChange(103, '2026-11-10', '20:00', 60))
            ->withChange(new OneOffChange(102, '2026-11-10', cancelled: true))
            ->withPausedRange('2026-11-10', '2026-11-10');

        $this->assertSame(['08:00–16:00 #101', '20:00–21:00 #103'], $this->ghosts($plan, '2026-11-10'));
    }

    public function test_a_ghost_brought_back_is_a_real_block_and_no_longer_a_ghost(): void
    {
        $plan = Fixtures::week()
            ->withChange(new OneOffChange(103, '2026-11-10', pauseException: true))
            ->withPausedRange('2026-11-10', '2026-11-10');

        $this->assertSame(['08:00–16:00 #101', '18:00–19:30 #102'], $this->ghosts($plan, '2026-11-10'));
        $blocks = $plan->blocksOn('2026-11-10');
        $this->assertCount(1, $blocks);
        $this->assertSame(103, $blocks[0]->templateId);
        $this->assertTrue($blocks[0]->changed, 'an exception to the pause is a this-week-only difference');
    }

    public function test_switching_a_day_back_on_shows_a_brought_back_block_once(): void
    {
        $plan = Fixtures::week()
            ->withChange(new OneOffChange(103, '2026-11-10', pauseException: true))
            ->withPausedRange('2026-11-10', '2026-11-10')
            ->withoutPause('2026-11-10');

        $this->assertSame([], $plan->ghostsOn('2026-11-10'));
        $this->assertCount(3, $plan->blocksOn('2026-11-10'));
    }

    public function test_one_off_appointments_are_blocks_not_ghosts(): void
    {
        $plan = Fixtures::week()
            ->withPausedRange('2026-11-09', '2026-11-09')
            ->withOneOff(new OneOffBlock('2026-11-09', '17:00', 30, null, 'Zahnarzt'));

        $this->assertCount(1, $plan->blocksOn('2026-11-09'));
        $this->assertSame(['08:00–16:00 #101', '19:30–21:30 #103'], $this->ghosts($plan, '2026-11-09'));
    }
}
