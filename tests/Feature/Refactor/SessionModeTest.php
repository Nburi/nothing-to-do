<?php

namespace Tests\Feature\Refactor;

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refactor step 1.7 / test plan A15 (R1, R9; risk C14): a started session is
 * stored on the server, a reload or the other device sees the same state, Done
 * completes the task and is harmless the second time.
 *
 * Not covered yet: "Done returns the board with the next suggestion" — the
 * suggestion is the pick engine (step 1.3); here the caller passes the next task.
 */
class SessionModeTest extends TestCase
{
    use RefreshDatabase;

    private FocusSession $sessions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessions = new FocusSession;
        // Switzerland in November: UTC+1.
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    /** Local Zurich wall time on Monday 9 November 2026. */
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 '.$time, 'Europe/Zurich');
    }

    public function test_a_user_without_a_session_is_idle_and_nothing_is_stored(): void
    {
        $this->assertSame(Phase::Idle, $this->sessions->current($this->user)->phase);
        $this->assertDatabaseCount('pomodoro_sessions', 0);
    }

    public function test_a_started_session_is_stored_and_every_device_reads_the_same_timer(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        // "The other device": a fresh service and a freshly loaded user.
        $other = new FocusSession;
        $state = $other->current($this->user->fresh(), $this->at('19:40:00'));
        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($task->id, $state->taskId);
        $this->assertSame(15 * 60, $other->remainingSeconds($this->user->fresh(), $this->at('19:40:00')));
        $this->assertDatabaseCount('pomodoro_sessions', 1);
    }

    public function test_reading_applies_elapsed_time_without_writing(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));
        $stored = PomodoroSession::sole()->state;

        $later = $this->sessions->current($this->user, $this->at('19:56:00'));

        $this->assertSame(Phase::ShortBreak, $later->phase);
        $this->assertSame($stored, PomodoroSession::sole()->fresh()->state, 'a read never writes');
    }

    public function test_done_completes_the_task_and_moves_on_to_the_next_inside_the_session(): void
    {
        [$first, $second] = Task::factory()->for($this->user)->count(2)->create();
        $this->sessions->start($this->user, $first->id, $this->at('19:30:00'));

        $state = $this->sessions->complete($this->user, $first->id, $second->id, $this->at('19:40:00'));

        $this->assertTrue($first->fresh()->is_completed);
        $this->assertNotNull($first->fresh()->completed_at);
        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($second->id, $state->taskId);
    }

    public function test_done_with_little_time_left_starts_the_break(): void
    {
        [$first, $second] = Task::factory()->for($this->user)->count(2)->create();
        $this->sessions->start($this->user, $first->id, $this->at('19:30:00'));

        $state = $this->sessions->complete($this->user, $first->id, $second->id, $this->at('19:52:00'));

        $this->assertSame(Phase::ShortBreak, $state->phase);
        $this->assertSame($second->id, $state->taskId, 'the next task waits for the next session');
    }

    public function test_done_twice_is_harmless(): void
    {
        [$first, $second] = Task::factory()->for($this->user)->count(2)->create();
        $this->sessions->start($this->user, $first->id, $this->at('19:30:00'));
        $this->sessions->complete($this->user, $first->id, $second->id, $this->at('19:40:00'));

        // The phone taps Done for the same task a second later.
        $state = $this->sessions->complete($this->user, $first->id, null, $this->at('19:40:01'));

        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($second->id, $state->taskId);
        $this->assertFalse($second->fresh()->is_completed, 'the running task is not touched');
    }

    public function test_done_for_a_task_that_is_not_running_does_not_complete_it(): void
    {
        [$running, $other] = Task::factory()->for($this->user)->count(2)->create();
        $this->sessions->start($this->user, $running->id, $this->at('19:30:00'));

        $this->sessions->complete($this->user, $other->id, null, $this->at('19:40:00'));

        $this->assertFalse($other->fresh()->is_completed);
        $this->assertSame($running->id, $this->sessions->current($this->user, $this->at('19:40:00'))->taskId);
    }

    public function test_done_with_nothing_next_ends_the_session(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        $state = $this->sessions->complete($this->user, $task->id, null, $this->at('19:40:00'));

        $this->assertSame(Phase::Idle, $state->phase);
        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_pause_and_resume_are_stored(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        $this->sessions->pause($this->user, $this->at('19:40:00'));
        $this->assertSame(15 * 60, $this->sessions->remainingSeconds($this->user, $this->at('20:30:00')), 'paused: the clock stands still');

        $this->sessions->resume($this->user, $this->at('20:30:00'));
        $this->assertSame(15 * 60, $this->sessions->remainingSeconds($this->user, $this->at('20:30:00')));
        $this->assertSame(5 * 60, $this->sessions->remainingSeconds($this->user, $this->at('20:40:00')));
    }

    public function test_stop_returns_to_the_board_and_leaves_the_task_open(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        $state = $this->sessions->stop($this->user, $this->at('19:40:00'));

        $this->assertSame(Phase::Idle, $state->phase);
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_after_a_break_start_without_a_task_continues_the_carried_over_one(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));
        $this->assertSame(Phase::Ready, $this->sessions->current($this->user, $this->at('20:01:00'))->phase);

        $state = $this->sessions->start($this->user, null, $this->at('20:02:00'));

        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($task->id, $state->taskId);
        $this->assertSame(1, $state->sessionsToday);
    }

    public function test_a_foreign_task_cannot_be_started(): void
    {
        $foreign = Task::factory()->for(User::factory())->create();

        $this->expectException(ModelNotFoundException::class);
        $this->sessions->start($this->user, $foreign->id, $this->at('19:30:00'));
    }

    public function test_a_foreign_task_cannot_be_passed_as_the_next_one(): void
    {
        $mine = Task::factory()->for($this->user)->create();
        $foreign = Task::factory()->for(User::factory())->create();
        $this->sessions->start($this->user, $mine->id, $this->at('19:30:00'));

        try {
            $this->sessions->complete($this->user, $mine->id, $foreign->id, $this->at('19:40:00'));
            $this->fail('a foreign next task must be rejected');
        } catch (ModelNotFoundException) {
            $this->assertFalse($mine->fresh()->is_completed, 'nothing was applied');
        }
    }

    public function test_a_completed_task_cannot_be_started(): void
    {
        $done = Task::factory()->for($this->user)->create(['is_completed' => true, 'completed_at' => now()]);

        $this->expectException(ModelNotFoundException::class);
        $this->sessions->start($this->user, $done->id, $this->at('19:30:00'));
    }

    public function test_sessions_of_two_users_are_independent(): void
    {
        $other = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        $this->assertSame(Phase::Idle, $this->sessions->current($other, $this->at('19:40:00'))->phase);
    }

    public function test_the_long_break_counter_resets_at_local_midnight_not_utc(): void
    {
        $task = Task::factory()->for($this->user)->create();
        // 23:30 local = 22:30 UTC: still Monday locally.
        $this->sessions->start($this->user, $task->id, $this->at('23:30:00'));
        $this->assertSame('2026-11-09', $this->sessions->current($this->user, $this->at('23:31:00'))->counterDate);

        // 00:30 local Tuesday = 23:30 UTC Monday: a new local day.
        $tuesday = CarbonImmutable::parse('2026-11-10 00:30:00', 'Europe/Zurich');
        $this->sessions->stop($this->user, $tuesday);
        $state = $this->sessions->start($this->user, $task->id, $tuesday);

        $this->assertSame('2026-11-10', $state->counterDate);
        $this->assertSame(0, $state->sessionsToday);
    }

    public function test_deleting_the_user_deletes_the_session(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $this->sessions->start($this->user, $task->id, $this->at('19:30:00'));

        $this->user->delete();

        $this->assertDatabaseCount('pomodoro_sessions', 0);
    }
}
