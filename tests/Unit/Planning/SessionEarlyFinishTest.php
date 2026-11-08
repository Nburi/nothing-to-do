<?php

namespace Tests\Unit\Planning;

use App\Planning\Session\Phase;
use App\Planning\Session\SessionEngine;
use App\Planning\Session\SessionState;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A17 (R9): done with at least 5 minutes left starts the next task inside the
 * same session; with less than 5 minutes left the break starts at once and the
 * next session is a fresh 25 minutes. Done twice is harmless (risk C14).
 */
class SessionEarlyFinishTest extends TestCase
{
    private SessionEngine $engine;

    private SessionState $running;

    protected function setUp(): void
    {
        $this->engine = new SessionEngine(Fixtures::TZ);
        $this->running = $this->engine->start(SessionState::idle(), 1, $this->at('19:30:00'));
    }

    public function test_done_with_more_than_five_minutes_left_starts_the_next_task_in_the_same_session(): void
    {
        $s = $this->engine->completeTask($this->running, 1, 2, $this->at('19:48:00'));

        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame(2, $s->taskId);
        $this->assertSame([1], $s->doneTaskIds);
        $this->assertSame(7 * 60, $this->engine->remainingSeconds($s, $this->at('19:48:00')), 'the session keeps its end time');
        $this->assertSame(0, $s->sessionsToday);
    }

    public function test_done_with_exactly_five_minutes_left_still_continues_in_the_session(): void
    {
        $s = $this->engine->completeTask($this->running, 1, 2, $this->at('19:50:00'));

        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame(2, $s->taskId);
    }

    public function test_done_with_less_than_five_minutes_left_starts_the_break_at_once(): void
    {
        $s = $this->engine->completeTask($this->running, 1, 2, $this->at('19:50:01'));

        $this->assertSame(Phase::ShortBreak, $s->phase);
        $this->assertSame(1, $s->sessionsToday);
        $this->assertSame(5 * 60, $this->engine->remainingSeconds($s, $this->at('19:50:01')));
        $this->assertSame(2, $s->taskId, 'the next task waits for the next session');

        $ready = $this->engine->at($s, $this->at('19:55:01'));
        $this->assertSame(Phase::Ready, $ready->phase);
        $next = $this->engine->start($ready, null, $this->at('19:56:00'));
        $this->assertSame(25 * 60, $this->engine->remainingSeconds($next, $this->at('19:56:00')), 'a fresh 25 minutes');
        $this->assertSame(2, $next->taskId);
    }

    public function test_the_early_break_is_long_when_it_is_the_fourth(): void
    {
        $s = SessionState::idle();
        $t = $this->at('15:00:00');
        for ($i = 1; $i <= 3; $i++) {
            $s = $this->engine->start($s, 1, $t);
            $t = $t->addMinutes(30);
            $s = $this->engine->at($s, $t);
        }
        $s = $this->engine->start($s, 1, $t);

        $s = $this->engine->completeTask($s, 1, null, $t->addMinutes(22));

        $this->assertSame(Phase::LongBreak, $s->phase);
        $this->assertSame(4, $s->sessionsToday);
    }

    public function test_done_twice_is_harmless(): void
    {
        $once = $this->engine->completeTask($this->running, 1, 2, $this->at('19:48:00'));
        $twice = $this->engine->completeTask($once, 1, 3, $this->at('19:48:01'));

        $this->assertEquals($once, $twice);
    }

    public function test_done_for_a_task_that_is_not_running_changes_nothing(): void
    {
        $this->assertEquals($this->running, $this->engine->completeTask($this->running, 99, 2, $this->at('19:40:00')));
        $idle = SessionState::idle();
        $this->assertEquals($idle, $this->engine->completeTask($idle, 1, 2, $this->at('19:40:00')));
    }

    public function test_done_with_nothing_left_to_do_ends_the_session(): void
    {
        $s = $this->engine->completeTask($this->running, 1, null, $this->at('19:40:00'));

        $this->assertSame(Phase::Idle, $s->phase);
        $this->assertNull($s->taskId);
        $this->assertSame([1], $s->doneTaskIds);
    }

    public function test_done_while_paused_uses_the_frozen_remaining_time(): void
    {
        $s = $this->engine->pause($this->running, $this->at('19:40:00'));

        // Paused with 15 minutes left; marking done an hour later still counts as 15 left.
        $s = $this->engine->completeTask($s, 1, 2, $this->at('20:40:00'));

        $this->assertSame(Phase::Work, $s->phase);
        $this->assertSame(2, $s->taskId);
        $this->assertTrue($s->isPaused());
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 '.$time, Fixtures::TZ);
    }
}
