<?php

namespace Tests\Unit\Planning;

use App\Planning\DayLoad;
use App\Planning\LoadForecast;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\SlotCalculator;
use PHPUnit\Framework\TestCase;

/** A6 (R5, R10): capacity = 25 minute sessions in pomodoro blocks; load above capacity flags the day. */
class LoadForecastTest extends TestCase
{
    public function test_sizes_are_measured_in_work_minutes(): void
    {
        $this->assertSame(10, Size::Quick->workMinutes());
        $this->assertSame(25, Size::Short->workMinutes());
        $this->assertSame(75, Size::Long->workMinutes());
        $this->assertSame(100, Size::Block->workMinutes());
        $this->assertSame(150, Size::Block->workMinutes(estimatedSessions: 6));
        // A block is at least four sessions, whatever the old estimate said.
        $this->assertSame(100, Size::Block->workMinutes(estimatedSessions: 2));
        // A missing size counts as Short (G1).
        $this->assertSame(Size::Short, Size::fromNullable(null));
        $this->assertSame(Size::Long, Size::fromNullable('long'));
    }

    public function test_a_day_within_capacity_is_not_overloaded(): void
    {
        $day = $this->forecast('2026-11-09', [
            new PlannedTask(1, Size::Long),
            new PlannedTask(2, Size::Short),
        ]);

        $this->assertSame(4, $day->capacitySessions);
        $this->assertSame(100, $day->capacityMinutes);
        $this->assertSame(100, $day->loadMinutes);
        $this->assertFalse($day->overloaded);
    }

    public function test_load_above_capacity_flags_the_day(): void
    {
        $day = $this->forecast('2026-11-09', [
            new PlannedTask(1, Size::Long),
            new PlannedTask(2, Size::Short),
            new PlannedTask(3, Size::Quick),
        ]);

        $this->assertSame(110, $day->loadMinutes);
        $this->assertTrue($day->overloaded);
        $this->assertSame(10, $day->overByMinutes());
    }

    public function test_school_and_training_blocks_do_not_raise_capacity(): void
    {
        // Tuesday has 8h school and 1.5h training, but only the 2h Deep work block is work time.
        $day = $this->forecast('2026-11-10', [new PlannedTask(1, Size::Block, estimatedSessions: 5)]);

        $this->assertSame(4, $day->capacitySessions);
        $this->assertTrue($day->overloaded);
    }

    public function test_any_planned_work_on_a_day_without_pomodoro_blocks_is_overload(): void
    {
        $day = $this->forecast('2026-11-15', [new PlannedTask(1, Size::Quick)]);

        $this->assertSame(0, $day->capacityMinutes);
        $this->assertTrue($day->overloaded);
    }

    public function test_work_already_done_on_a_task_reduces_its_load(): void
    {
        $day = $this->forecast('2026-11-09', [new PlannedTask(1, Size::Block, estimatedSessions: 5, doneMinutes: 50)]);

        $this->assertSame(75, $day->loadMinutes);
        $this->assertFalse($day->overloaded);
    }

    /** @param  list<PlannedTask>  $tasks */
    private function forecast(string $date, array $tasks): DayLoad
    {
        $slots = (new SlotCalculator(Fixtures::categories()))->slots(Fixtures::week()->blocksOn($date));

        return (new LoadForecast)->day($date, $slots, $tasks);
    }
}
