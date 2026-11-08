<?php

namespace Tests\Unit\Planning;

use App\Planning\Session\Phase;
use App\Planning\Session\SessionEngine;
use App\Planning\Session\SessionState;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A16 (R9): 25 / 5 cycle, 15 after every fourth session (counter resets daily),
 * an unfinished task carries over to the next session. The server owns the
 * state; clients only render remaining time from it.
 */
class PomodoroCycleTest extends TestCase
{
    private SessionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SessionEngine(Fixtures::TZ);
    }

    public function test_a_session_is_25_minutes_of_work_then_a_5_minute_break(): void
    {
        $s = $this->engine->start(SessionState::idle(), 42, $this->at('19:30'));

        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame(25 * 60, $this->engine->remainingSeconds($s, $this->at('19:30')));
        $this->assertSame(Phase::Work, $this->engine->at($s, $this->at('19:54'))->phase);

        $break = $this->engine->at($s, $this->at('19:55'));
        $this->assertSame(Phase::ShortBreak, $break->phase);
        $this->assertSame(1, $break->sessionsToday);
        $this->assertSame(5 * 60, $this->engine->remainingSeconds($break, $this->at('19:55')));
    }

    public function test_an_unfinished_task_carries_over_into_the_next_session(): void
    {
        $s = $this->engine->start(SessionState::idle(), 42, $this->at('19:30'));

        $ready = $this->engine->at($s, $this->at('20:00'));
        $this->assertSame(Phase::Ready, $ready->phase);
        $this->assertSame(42, $ready->taskId, 'same task, no new pick');

        $next = $this->engine->start($ready, null, $this->at('20:02'));
        $this->assertSame(Phase::Work, $next->phase);
        $this->assertSame(42, $next->taskId);
        $this->assertSame(25 * 60, $this->engine->remainingSeconds($next, $this->at('20:02')), 'a fresh 25 minutes');
    }

    public function test_every_fourth_break_is_15_minutes(): void
    {
        $s = SessionState::idle();
        $t = $this->at('19:00');
        $breaks = [];

        for ($i = 1; $i <= 5; $i++) {
            $s = $this->engine->start($s, 1, $t);
            $t = $t->addMinutes(25);
            $s = $this->engine->at($s, $t);
            $breaks[] = $s->phase;
            $t = $t->addSeconds($this->engine->remainingSeconds($s, $t));
            $s = $this->engine->at($s, $t);
        }

        $this->assertSame(
            [Phase::ShortBreak, Phase::ShortBreak, Phase::ShortBreak, Phase::LongBreak, Phase::ShortBreak],
            $breaks,
        );
    }

    public function test_the_long_break_counter_resets_each_local_day(): void
    {
        $s = SessionState::idle();
        $t = CarbonImmutable::parse('2026-11-09 21:00', Fixtures::TZ);
        for ($i = 1; $i <= 3; $i++) {
            $s = $this->engine->start($s, 1, $t);
            $t = $t->addMinutes(30);
            $s = $this->engine->at($s, $t);
        }
        $this->assertSame(3, $s->sessionsToday);

        // Next local day (Zurich), even though it is still 9 November in UTC at 00:30.
        $s = $this->engine->start($s, 1, CarbonImmutable::parse('2026-11-10 00:30', Fixtures::TZ));
        $s = $this->engine->at($s, CarbonImmutable::parse('2026-11-10 00:55', Fixtures::TZ));

        $this->assertSame(1, $s->sessionsToday);
        $this->assertSame(Phase::ShortBreak, $s->phase, 'session four of the evening was yesterday');
    }

    public function test_pause_freezes_the_remaining_time_and_resume_continues_it(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30'));
        $s = $this->engine->pause($s, $this->at('19:40'));

        $this->assertTrue($s->isPaused());
        $this->assertSame(15 * 60, $this->engine->remainingSeconds($s, $this->at('19:50')));
        $this->assertSame(Phase::Work, $this->engine->at($s, $this->at('20:30'))->phase, 'nothing advances while paused');

        $s = $this->engine->resume($s, $this->at('19:47'));
        $this->assertFalse($s->isPaused());
        $this->assertSame(Phase::Work, $this->engine->at($s, $this->at('20:01'))->phase);
        $this->assertSame(Phase::ShortBreak, $this->engine->at($s, $this->at('20:02'))->phase);
    }

    public function test_pause_and_resume_twice_are_harmless(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30'));
        $paused = $this->engine->pause($s, $this->at('19:40'));

        $this->assertEquals($paused, $this->engine->pause($paused, $this->at('19:45')));
        $resumed = $this->engine->resume($paused, $this->at('19:50'));
        $this->assertEquals($resumed, $this->engine->resume($resumed, $this->at('19:55')));
        $this->assertEquals($s, $this->engine->resume($s, $this->at('19:41')), 'resume without a pause changes nothing');
    }

    public function test_a_device_that_was_away_for_hours_sees_a_waiting_session_not_a_loop(): void
    {
        $s = $this->engine->start(SessionState::idle(), 42, $this->at('19:30'));

        $later = $this->engine->at($s, $this->at('23:59'));

        $this->assertSame(Phase::Ready, $later->phase);
        $this->assertSame(1, $later->sessionsToday, 'only the one session that really ran is counted');
        $this->assertSame(42, $later->taskId);
    }

    public function test_starting_during_a_break_ends_the_break_early(): void
    {
        $s = $this->engine->start(SessionState::idle(), 1, $this->at('19:30'));
        $s = $this->engine->at($s, $this->at('19:56'));
        $this->assertSame(Phase::ShortBreak, $s->phase);

        $s = $this->engine->start($s, null, $this->at('19:57'));

        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame(1, $s->taskId);
        $this->assertSame(1, $s->sessionsToday);
    }

    public function test_the_state_survives_a_round_trip_through_storage(): void
    {
        $s = $this->engine->start(SessionState::idle(), 42, $this->at('19:30'));
        $s = $this->engine->pause($s, $this->at('19:35'));

        $this->assertEquals($s, SessionState::fromArray(json_decode(json_encode($s->toArray()), true)));
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 '.$time, Fixtures::TZ);
    }
}
