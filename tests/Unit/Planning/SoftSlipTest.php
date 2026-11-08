<?php

namespace Tests\Unit\Planning;

use App\Planning\Crunch\SlipAction;
use App\Planning\Crunch\SoftSlip;
use PHPUnit\Framework\TestCase;

/** A7 (R5, G2, risk F5): first miss rolls over, second miss moves the item to Later. */
class SoftSlipTest extends TestCase
{
    private const TODAY = '2026-11-09';

    public function test_a_soft_deadline_today_or_later_is_not_missed(): void
    {
        $this->assertSame(SlipAction::None, SoftSlip::decide(self::TODAY, 0, false, self::TODAY)->action);
        $this->assertSame(SlipAction::None, SoftSlip::decide('2026-11-20', 1, false, self::TODAY)->action);
        $this->assertSame(SlipAction::None, SoftSlip::decide(null, 0, false, self::TODAY)->action);
    }

    public function test_the_first_miss_rolls_over_a_week(): void
    {
        $decision = SoftSlip::decide('2026-11-08', 0, false, self::TODAY);

        $this->assertSame(SlipAction::RollOver, $decision->action);
        $this->assertSame('2026-11-15', $decision->newSoftDeadline);
    }

    public function test_an_old_miss_rolls_over_to_tomorrow_at_the_earliest(): void
    {
        $decision = SoftSlip::decide('2026-09-01', 0, false, self::TODAY);

        $this->assertSame('2026-11-10', $decision->newSoftDeadline);
    }

    public function test_the_second_miss_moves_the_item_to_later(): void
    {
        $this->assertSame(SlipAction::ToLater, SoftSlip::decide('2026-11-08', 1, false, self::TODAY)->action);
    }

    public function test_a_task_with_a_hard_deadline_only_loses_its_soft_date(): void
    {
        $this->assertSame(SlipAction::DropSoftDate, SoftSlip::decide('2026-11-08', 1, true, self::TODAY)->action);
        $this->assertSame(SlipAction::RollOver, SoftSlip::decide('2026-11-08', 0, true, self::TODAY)->action, 'the first miss still rolls over');
    }
}
