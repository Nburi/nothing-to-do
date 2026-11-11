<?php

namespace Tests\Unit\Planning;

use App\Planning\Session\Phase;
use App\Planning\Session\SessionEngine;
use App\Planning\Session\SessionState;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Leaving a session without Done (step 1.7). Like E6, an early stop does not
 * count toward the fourth-session long break.
 */
class SessionStopTest extends TestCase
{
    private SessionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SessionEngine(Fixtures::TZ);
    }

    public function test_stopping_a_running_session_returns_to_idle_without_counting_it(): void
    {
        $running = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));

        $s = $this->engine->stop($running, $this->at('19:40:00'));

        $this->assertSame(Phase::Idle, $s->phase);
        $this->assertNull($s->taskId);
        $this->assertSame(0, $s->sessionsToday);
        $this->assertSame('2026-11-09', $s->counterDate);
    }

    public function test_stopping_keeps_the_days_counter_for_the_next_session(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:00:00'));
        $s = $this->engine->at($s, $this->at('19:26:00')); // one session done, in the break
        $s = $this->engine->stop($s, $this->at('19:27:00'));

        $this->assertSame(1, $s->sessionsToday);
        $again = $this->engine->start($s, 2, $this->at('20:00:00'));
        $this->assertSame(1, $again->sessionsToday, 'same day: the counter carries on');
    }

    public function test_stopping_a_paused_session_clears_the_pause(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));
        $s = $this->engine->pause($s, $this->at('19:35:00'));

        $s = $this->engine->stop($s, $this->at('19:50:00'));

        $this->assertFalse($s->isPaused());
        $this->assertSame(0, $s->pausedSeconds);
    }

    public function test_stopping_when_idle_changes_nothing(): void
    {
        $idle = SessionState::idle();

        $this->assertEquals($idle, $this->engine->stop($idle, $this->at('19:30:00')));
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 '.$time, Fixtures::TZ);
    }
}
