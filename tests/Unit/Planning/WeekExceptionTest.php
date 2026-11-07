<?php

namespace Tests\Unit\Planning;

use App\Planning\Block;
use App\Planning\OneOffBlock;
use App\Planning\OneOffChange;
use PHPUnit\Framework\TestCase;

/** A3 (R4): a one-off change touches one date only; the template is untouched. */
class WeekExceptionTest extends TestCase
{
    public function test_a_template_repeats_on_its_weekdays_only(): void
    {
        $plan = Fixtures::week();

        $monday = $this->titles($plan->blocksOn('2026-11-09'));
        $saturday = $this->titles($plan->blocksOn('2026-11-14'));
        $sunday = $plan->blocksOn('2026-11-15');

        $this->assertSame(['101 08:00-16:00', '103 19:30-21:30'], $monday);
        $this->assertSame(['104 10:00-12:00'], $saturday);
        $this->assertSame([], $sunday);
    }

    public function test_moving_one_occurrence_changes_that_date_only(): void
    {
        $plan = Fixtures::week()->withChange(
            new OneOffChange(103, '2026-11-10', start: '20:00', durationMinutes: 60),
        );

        $this->assertSame(['101 08:00-16:00', '102 18:00-19:30', '103 20:00-21:00'], $this->titles($plan->blocksOn('2026-11-10')));
        // The same template a week later and the day after are untouched.
        $this->assertSame(['101 08:00-16:00', '102 18:00-19:30', '103 19:30-21:30'], $this->titles($plan->blocksOn('2026-11-17')));
        $this->assertSame(['101 08:00-16:00', '103 19:30-21:30'], $this->titles($plan->blocksOn('2026-11-11')));
        $this->assertSame('19:30', $plan->template(103)->start);
    }

    public function test_cancelling_one_occurrence_removes_only_that_date(): void
    {
        $plan = Fixtures::week()->withChange(new OneOffChange(103, '2026-11-11', cancelled: true));

        $this->assertSame(['101 08:00-16:00'], $this->titles($plan->blocksOn('2026-11-11')));
        $this->assertSame(['101 08:00-16:00', '103 19:30-21:30'], $this->titles($plan->blocksOn('2026-11-18')));
    }

    public function test_a_later_change_for_the_same_date_replaces_the_earlier_one(): void
    {
        $plan = Fixtures::week()
            ->withChange(new OneOffChange(103, '2026-11-10', start: '20:00'))
            ->withChange(new OneOffChange(103, '2026-11-10', start: '20:30'));

        $this->assertContains('103 20:30-22:30', $this->titles($plan->blocksOn('2026-11-10')));
        $this->assertNotContains('103 20:00-22:00', $this->titles($plan->blocksOn('2026-11-10')));
    }

    public function test_one_off_blocks_appear_on_their_date_and_are_sorted_by_start(): void
    {
        $plan = Fixtures::week()->withOneOff(new OneOffBlock('2026-11-09', '17:00', 30, null, 'Zahnarzt'));

        $this->assertSame(
            ['101 08:00-16:00', 'Zahnarzt 17:00-17:30', '103 19:30-21:30'],
            $this->titles($plan->blocksOn('2026-11-09')),
        );
        $this->assertNotContains('Zahnarzt 17:00-17:30', $this->titles($plan->blocksOn('2026-11-16')));
    }

    /** @param  list<Block>  $blocks */
    private function titles(array $blocks): array
    {
        return array_map(
            fn (Block $b) => ($b->title ?? (string) $b->templateId).' '.$b->start->format('H:i').'-'.$b->end->format('H:i'),
            $blocks,
        );
    }
}
