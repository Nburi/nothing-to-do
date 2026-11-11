<?php

namespace App\Services;

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;
use App\Planning\Rhythm;
use App\Planning\Session\Phase;
use App\Planning\Session\SessionEngine;
use App\Planning\Session\SessionState;
use App\Services\Planning\Suggestions;
use App\Support\TaskMutator;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Refactor step 1.7: the session engine wired to storage (R1, R9; risks F14,
 * C14). The server owns the state; every device asks this service and renders
 * what it gets back, so two devices can never disagree about the timer.
 *
 * Every write runs in a transaction that locks the user's row, so a laptop and
 * a phone tapping Done at the same moment are applied one after the other —
 * and because SessionEngine::completeTask() ignores a task that is no longer
 * running, the second tap changes nothing (Done is idempotent).
 *
 * Task ids are resolved through the user's own active tasks; a foreign,
 * deleted or completed id is a ModelNotFoundException, like everywhere else.
 *
 * The rhythm is the refactor's fixed 25 / 5 / 15 (REQUIREMENTS § 3), not the
 * old per-user Pomodoro settings, which belong to the old screens.
 */
final class FocusSession
{
    public function __construct(
        private Rhythm $rhythm = new Rhythm,
        private Suggestions $suggestions = new Suggestions,
    ) {}

    /** The current state with elapsed time applied. Read-only: nothing is written. */
    public function current(User $user, ?CarbonImmutable $now = null): SessionState
    {
        $now ??= CarbonImmutable::now();
        $row = PomodoroSession::query()->where('user_id', $user->id)->first();

        return $this->engine($user, $now)->at($row?->sessionState() ?? SessionState::idle(), $now);
    }

    public function remainingSeconds(User $user, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return $this->engine($user, $now)->remainingSeconds($this->current($user, $now), $now);
    }

    /** Start work. Without a task id the carried-over task continues (after a break). */
    public function start(User $user, ?int $taskId = null, ?CarbonImmutable $now = null): SessionState
    {
        $task = $taskId !== null ? $this->activeTask($user, $taskId) : null;

        return $this->mutate($user, $now, fn (SessionEngine $e, SessionState $s, CarbonImmutable $t) => $e->start($s, $task?->id, $t));
    }

    /**
     * Mark the running task done: the task itself is completed through
     * TaskMutator (same side effects as every other completion path), and the
     * engine decides between "next task in this session", "break now" and
     * "session over". A task that is not the running one changes nothing —
     * neither the session nor the task.
     */
    public function complete(User $user, int $taskId, ?int $nextTaskId = null, ?CarbonImmutable $now = null): SessionState
    {
        $next = $nextTaskId !== null ? $this->activeTask($user, $nextTaskId) : null;

        return $this->mutate($user, $now, function (SessionEngine $e, SessionState $s, CarbonImmutable $t) use ($user, $taskId, $next) {
            $before = $e->at($s, $t);
            $after = $e->completeTask($before, $taskId, $next?->id, $t);

            $accepted = $before->phase === Phase::Work && $before->taskId === $taskId;
            if ($accepted) {
                $task = Task::query()->where('user_id', $user->id)->whereKey($taskId)->first();
                if ($task !== null && ! $task->is_completed) {
                    TaskMutator::applyUpdate($task, $user, ['is_completed' => true]);
                }
            }

            return $after;
        });
    }

    /**
     * Done from the session view (A15): like complete(), with the next task
     * chosen by the pick engine. With at least 5 minutes left it starts inside
     * this session; otherwise it waits for the next one; with nothing left to
     * do the session ends and the board shows the next suggestion.
     */
    public function done(User $user, int $taskId, ?CarbonImmutable $now = null): SessionState
    {
        $next = $this->suggestions->nextInSession($user, $taskId, $now);

        return $this->complete($user, $taskId, $next, $now);
    }

    public function pause(User $user, ?CarbonImmutable $now = null): SessionState
    {
        return $this->mutate($user, $now, fn (SessionEngine $e, SessionState $s, CarbonImmutable $t) => $e->pause($s, $t));
    }

    public function resume(User $user, ?CarbonImmutable $now = null): SessionState
    {
        return $this->mutate($user, $now, fn (SessionEngine $e, SessionState $s, CarbonImmutable $t) => $e->resume($s, $t));
    }

    /** Leave the session without marking anything done. */
    public function stop(User $user, ?CarbonImmutable $now = null): SessionState
    {
        return $this->mutate($user, $now, fn (SessionEngine $e, SessionState $s, CarbonImmutable $t) => $e->stop($s, $t));
    }

    /** @param  Closure(SessionEngine, SessionState, CarbonImmutable): SessionState  $change */
    private function mutate(User $user, ?CarbonImmutable $now, Closure $change): SessionState
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($user, $now, $change) {
            $row = PomodoroSession::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? PomodoroSession::query()->forceCreate(['user_id' => $user->id, 'state' => SessionState::idle()->toArray()]);

            $state = $change($this->engine($user, $now), $row->sessionState(), $now);

            $row->update(['state' => $state->toArray()]);

            return $state;
        });
    }

    /**
     * The engine needs a timezone only to decide which local day "today" is
     * (the long-break counter resets daily, E3/E8). Users store a UTC offset,
     * not an IANA zone, so the offset in force right now is passed as a fixed
     * offset — exact for that one question, DST included.
     */
    private function engine(User $user, CarbonImmutable $now): SessionEngine
    {
        $minutes = $user->utcOffsetMinutes(Carbon::instance($now));
        $zone = sprintf('%s%02d:%02d', $minutes < 0 ? '-' : '+', intdiv(abs($minutes), 60), abs($minutes) % 60);

        return new SessionEngine($zone, $this->rhythm);
    }

    private function activeTask(User $user, int $id): Task
    {
        return Task::query()->where('user_id', $user->id)->active()->findOrFail($id);
    }
}
