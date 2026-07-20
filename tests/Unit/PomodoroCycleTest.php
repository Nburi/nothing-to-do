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

    public function test_duration_minutes_reads_the_matching_rhythm_field(): void
    {
        $rhythm = $this->rhythm(work: 25, short: 5, long: 15, every: 4);

        $this->assertSame(25, PomodoroCycle::durationMinutes(PomodoroCycle::WORK, $rhythm));
        $this->assertSame(5, PomodoroCycle::durationMinutes(PomodoroCycle::SHORT_BREAK, $rhythm));
        $this->assertSame(15, PomodoroCycle::durationMinutes(PomodoroCycle::LONG_BREAK, $rhythm));
    }

    public function test_work_is_followed_by_a_short_break_on_ordinary_cycles(): void
    {
        $next = PomodoroCycle::next(PomodoroCycle::WORK, 1, $this->rhythm());

        $this->assertSame(PomodoroCycle::SHORT_BREAK, $next['phase']);
        $this->assertSame(1, $next['cycle']); // the break belongs to the cycle that just finished
    }

    public function test_work_is_followed_by_a_long_break_every_nth_cycle(): void
    {
        $next = PomodoroCycle::next(PomodoroCycle::WORK, 4, $this->rhythm(every: 4));

        $this->assertSame(PomodoroCycle::LONG_BREAK, $next['phase']);
        $this->assertSame(4, $next['cycle']);
    }

    public function test_a_short_break_is_followed_by_the_next_work_cycle(): void
    {
        $next = PomodoroCycle::next(PomodoroCycle::SHORT_BREAK, 1, $this->rhythm());

        $this->assertSame(PomodoroCycle::WORK, $next['phase']);
        $this->assertSame(2, $next['cycle']);
    }

    public function test_a_long_break_is_followed_by_the_next_work_cycle(): void
    {
        $next = PomodoroCycle::next(PomodoroCycle::LONG_BREAK, 4, $this->rhythm(every: 4));

        $this->assertSame(PomodoroCycle::WORK, $next['phase']);
        $this->assertSame(5, $next['cycle']);
    }

    public function test_respects_a_custom_rhythm(): void
    {
        $rhythm = $this->rhythm(work: 50, short: 10, long: 20, every: 2);

        $this->assertSame(50, PomodoroCycle::durationMinutes(PomodoroCycle::WORK, $rhythm));

        $afterCycle1 = PomodoroCycle::next(PomodoroCycle::WORK, 1, $rhythm);
        $this->assertSame(PomodoroCycle::SHORT_BREAK, $afterCycle1['phase']);

        $afterCycle2 = PomodoroCycle::next(PomodoroCycle::WORK, 2, $rhythm);
        $this->assertSame(PomodoroCycle::LONG_BREAK, $afterCycle2['phase']);
    }
}
