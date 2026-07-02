<?php

namespace Tests\Unit;

use App\Services\PomodoroCycle;
use Tests\TestCase;

class PomodoroCycleTest extends TestCase
{
    /** @return array{work:int,short_break:int,long_break:int,long_every:int} */
    private function rhythm(int $work = 25, int $short = 5, int $long = 15, int $every = 4): array
    {
        return ['work' => $work, 'short_break' => $short, 'long_break' => $long, 'long_every' => $every];
    }

    public function test_starts_in_the_first_work_phase_at_zero_elapsed(): void
    {
        $phase = PomodoroCycle::at(0, $this->rhythm());

        $this->assertSame('work', $phase['phase']);
        $this->assertSame(1, $phase['cycle']);
        $this->assertSame(0, $phase['phase_start_minute']);
        $this->assertSame(25, $phase['phase_end_minute']);
    }

    public function test_mid_work_phase_stays_in_the_same_cycle(): void
    {
        $phase = PomodoroCycle::at(10, $this->rhythm());

        $this->assertSame('work', $phase['phase']);
        $this->assertSame(1, $phase['cycle']);
    }

    public function test_the_exact_end_minute_of_work_already_belongs_to_the_break(): void
    {
        $phase = PomodoroCycle::at(25, $this->rhythm());

        $this->assertSame('short_break', $phase['phase']);
        $this->assertSame(1, $phase['cycle']);
        $this->assertSame(25, $phase['phase_start_minute']);
        $this->assertSame(30, $phase['phase_end_minute']);
    }

    public function test_second_work_cycle_starts_right_after_the_short_break(): void
    {
        $phase = PomodoroCycle::at(30, $this->rhythm());

        $this->assertSame('work', $phase['phase']);
        $this->assertSame(2, $phase['cycle']);
        $this->assertSame(30, $phase['phase_start_minute']);
        $this->assertSame(55, $phase['phase_end_minute']);
    }

    public function test_a_long_break_follows_the_fourth_work_cycle(): void
    {
        // 4 work + 3 short breaks = 4*25 + 3*5 = 115 minutes before the long break starts.
        $phase = PomodoroCycle::at(115, $this->rhythm());

        $this->assertSame('long_break', $phase['phase']);
        $this->assertSame(4, $phase['cycle']);
        $this->assertSame(115, $phase['phase_start_minute']);
        $this->assertSame(130, $phase['phase_end_minute']); // 15' long break
    }

    public function test_fifth_work_cycle_starts_after_the_long_break(): void
    {
        $phase = PomodoroCycle::at(130, $this->rhythm());

        $this->assertSame('work', $phase['phase']);
        $this->assertSame(5, $phase['cycle']);
        $this->assertSame(130, $phase['phase_start_minute']);
        $this->assertSame(155, $phase['phase_end_minute']);
    }

    public function test_respects_a_custom_rhythm(): void
    {
        $rhythm = $this->rhythm(work: 50, short: 10, long: 20, every: 2);

        $shortBreak = PomodoroCycle::at(50, $rhythm);
        $this->assertSame('short_break', $shortBreak['phase']);
        $this->assertSame(1, $shortBreak['cycle']);
        $this->assertSame(60, $shortBreak['phase_end_minute']);

        $longBreak = PomodoroCycle::at(110, $rhythm);
        $this->assertSame('long_break', $longBreak['phase']);
        $this->assertSame(2, $longBreak['cycle']);
        $this->assertSame(130, $longBreak['phase_end_minute']);
    }
}
