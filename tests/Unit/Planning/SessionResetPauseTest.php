<?php

namespace Tests\Unit\Planning;

use App\Planning\Session\Phase;
use App\Planning\Session\SessionEngine;
use App\Planning\Session\SessionState;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Step d-reset-pause (A140): the evening reset, started by hand during running work, holds the pomodoro.
 * The pause carries its own end (pauseEndsAt), so a reset that ran out, was closed early or was never
 * looked at again all give the same time back, and a read never has to write.
 */
class SessionResetPauseTest extends TestCase
{
    private SessionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SessionEngine(Fixtures::TZ);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 '.$time, Fixtures::TZ);
    }

    /** Work started 19:30; the reset starts 19:40 and runs until 19:50. */
    private function held(): SessionState
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));

        return $this->engine->pauseForReset($s, $this->at('19:40:00'), $this->at('19:50:00'));
    }

    public function test_running_work_is_held_for_the_reset(): void
    {
        $s = $this->held();

        $this->assertTrue($s->isPaused());
        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame($this->at('19:50:00')->getTimestamp(), $s->pauseEndsAt);
        $this->assertSame(15 * 60, $this->engine->remainingSeconds($s, $this->at('19:45:00')), 'the clock stands still');
    }

    public function test_time_up_gives_back_exactly_the_time_the_reset_took_without_any_write(): void
    {
        $s = $this->held();

        // 19:30 + 25 min + 10 min held = 20:05; asked at 19:52 (reset over since 19:50): 13 minutes left.
        $this->assertSame(13 * 60, $this->engine->remainingSeconds($s, $this->at('19:52:00')));
        $read = $this->engine->at($s, $this->at('19:52:00'));
        $this->assertFalse($read->isPaused());
        $this->assertNull($read->pauseEndsAt);
        $this->assertSame(600, $read->pausedSeconds);
        $this->assertSame(Phase::Work, $read->phase);
    }

    public function test_a_page_opened_hours_later_sees_the_work_over_not_a_pause_for_ever(): void
    {
        $read = $this->engine->at($this->held(), $this->at('23:30:00'));

        $this->assertSame(Phase::Ready, $read->phase, 'the 25 minutes ran out after the reset; the carried task waits');
        $this->assertSame(1, $read->taskId);
        $this->assertSame(1, $read->sessionsToday);
        $this->assertNull($read->pauseEndsAt);
    }

    public function test_ending_the_reset_early_resumes_at_that_moment(): void
    {
        $s = $this->engine->endReset($this->held(), $this->at('19:44:00'));

        $this->assertFalse($s->isPaused());
        $this->assertNull($s->pauseEndsAt);
        $this->assertSame(4 * 60, $s->pausedSeconds);
        $this->assertSame(15 * 60, $this->engine->remainingSeconds($s, $this->at('19:44:00')), 'the 4 minutes of the reset did not cost work time');
    }

    public function test_stopping_the_session_during_the_reset_clears_everything(): void
    {
        $s = $this->engine->stop($this->held(), $this->at('19:45:00'));

        $this->assertSame(Phase::Idle, $s->phase);
        $this->assertNull($s->pausedAt);
        $this->assertNull($s->pauseEndsAt);
        $this->assertSame(0, $s->pausedSeconds);
        $this->assertSame(Phase::Idle, $this->engine->at($s, $this->at('21:00:00'))->phase);
    }

    public function test_a_pause_the_user_made_stays_theirs(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));
        $s = $this->engine->pause($s, $this->at('19:35:00'));

        $held = $this->engine->pauseForReset($s, $this->at('19:40:00'), $this->at('19:50:00'));
        $this->assertNull($held->pauseEndsAt);
        $this->assertSame($this->at('19:35:00')->getTimestamp(), $held->pausedAt);

        $this->assertTrue($this->engine->at($held, $this->at('20:30:00'))->isPaused(), 'time up does not resume it');
        $this->assertTrue($this->engine->endReset($held, $this->at('19:45:00'))->isPaused(), 'ending the reset does not either');
    }

    public function test_a_break_runs_on_through_the_reset(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));
        $s = $this->engine->at($s, $this->at('19:56:00')); // short break since 19:55

        $held = $this->engine->pauseForReset($s, $this->at('19:56:00'), $this->at('20:06:00'));

        $this->assertSame(Phase::ShortBreak, $held->phase);
        $this->assertFalse($held->isPaused());
        $this->assertSame(Phase::Ready, $this->engine->at($held, $this->at('20:07:00'))->phase);
    }

    public function test_idle_and_ready_have_nothing_to_hold(): void
    {
        $idle = SessionState::idle();
        $this->assertEquals($idle, $this->engine->pauseForReset($idle, $this->at('19:40:00'), $this->at('19:50:00')));

        $ready = $this->engine->at($this->engine->start($idle, 1, $this->at('19:00:00')), $this->at('19:40:00'));
        $this->assertSame(Phase::Ready, $this->engine->pauseForReset($ready, $this->at('19:40:00'), $this->at('19:50:00'))->phase);
        $this->assertFalse($this->engine->pauseForReset($ready, $this->at('19:40:00'), $this->at('19:50:00'))->isPaused());
    }

    public function test_the_second_reset_holds_again_after_the_first_gave_the_time_back(): void
    {
        $s = $this->engine->endReset($this->held(), $this->at('19:42:00')); // 2 min held
        $s = $this->engine->pauseForReset($s, $this->at('19:43:00'), $this->at('19:53:00'));

        $this->assertTrue($s->isPaused());
        $this->assertSame(120, $s->pausedSeconds);
        $this->assertSame(14 * 60, $this->engine->remainingSeconds($s, $this->at('19:50:00')));
    }

    public function test_a_second_device_resuming_by_hand_ends_the_hold(): void
    {
        $s = $this->engine->resume($this->held(), $this->at('19:43:00'));

        $this->assertFalse($s->isPaused());
        $this->assertNull($s->pauseEndsAt);
        $this->assertSame(180, $s->pausedSeconds);
    }

    public function test_the_stored_state_round_trips_with_and_without_the_hold(): void
    {
        $held = $this->held();
        $this->assertEquals($held, SessionState::fromArray($held->toArray()));

        $old = ['phase' => 'work', 'taskId' => 1, 'phaseStartedAt' => 1, 'pausedAt' => null, 'pausedSeconds' => 0, 'sessionsToday' => 0, 'counterDate' => null, 'doneTaskIds' => []];
        $this->assertNull(SessionState::fromArray($old)->pauseEndsAt, 'state saved before this step still loads');
    }
}
