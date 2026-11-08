<?php

namespace App\Planning\Session;

use App\Planning\Rhythm;
use Carbon\CarbonImmutable;

/**
 * Pomodoro session rules (REQUIREMENTS § 3) as a pure state machine. Every
 * method takes the stored state and "now" and returns the new state; nothing
 * here reads a clock or a database, so every device that asks with the same
 * state and time gets the same answer.
 *
 * Work ends → break (every fourth of the local day is long) → Ready with the
 * same task (carry-over) → start() begins a fresh 25 minutes. A device that
 * was away for hours sees Ready, never a run of phantom sessions.
 */
final readonly class SessionEngine
{
    public function __construct(
        private string $timezone,
        private Rhythm $rhythm = new Rhythm,
    ) {}

    /**
     * Start (or continue) work. Without a task id the carried-over task is used.
     * Starting during a break ends the break early. A running session is left alone.
     */
    public function start(SessionState $state, ?int $taskId, CarbonImmutable $now): SessionState
    {
        $state = $this->at($state, $now);
        $task = $taskId ?? $state->taskId;

        if ($state->phase === Phase::Work || $task === null) {
            return $state;
        }

        $today = $now->setTimezone($this->timezone)->toDateString();
        $sameDay = $state->counterDate === $today;

        return new SessionState(
            Phase::Work,
            $task,
            $now->getTimestamp(),
            null,
            0,
            $sameDay ? $state->sessionsToday : 0,
            $today,
            $sameDay ? $state->doneTaskIds : [],
        );
    }

    /** Apply the time that passed: finished phases move on, up to Ready. */
    public function at(SessionState $state, CarbonImmutable $now): SessionState
    {
        $t = $now->getTimestamp();

        while ($state->phase->isTimed() && ! $state->isPaused()) {
            $endsAt = $this->endsAt($state);
            if ($t < $endsAt) {
                break;
            }

            if ($state->phase === Phase::Work) {
                $state = $this->breakAfterSession($state, $endsAt, $state->taskId);
            } else {
                $state = $state->with(phase: Phase::Ready, phaseStartedAt: $endsAt, pausedSeconds: 0);
            }
        }

        return $state;
    }

    /**
     * Mark the running task done. At least 5 minutes left and a next task:
     * it starts inside the same session. Less than 5 minutes left: the break
     * starts now and the next task waits for the next session. Nothing left to
     * do: the session ends. Done for a task that is not running is a no-op, so
     * a double tap or a second device cannot do harm.
     */
    public function completeTask(SessionState $state, int $taskId, ?int $nextTaskId, CarbonImmutable $now): SessionState
    {
        $state = $this->at($state, $now);

        if ($state->phase !== Phase::Work || $state->taskId !== $taskId) {
            return $state;
        }

        $done = [...$state->doneTaskIds, $taskId];
        $remaining = $this->remainingSeconds($state, $now);

        if ($remaining < $this->rhythm->nextTaskThresholdMinutes * 60) {
            return $this->breakAfterSession($state->with(doneTaskIds: $done), $now->getTimestamp(), $nextTaskId);
        }

        if ($nextTaskId === null) {
            return $state->with(phase: Phase::Idle, taskId: null, phaseStartedAt: null, pausedAt: null, pausedSeconds: 0, doneTaskIds: $done);
        }

        return $state->with(taskId: $nextTaskId, doneTaskIds: $done);
    }

    /**
     * Leave the session without marking anything done ("back to the board").
     * Like E6, a session that is stopped early does not count toward the
     * fourth-session long break; the day's counter and done list are kept.
     */
    public function stop(SessionState $state, CarbonImmutable $now): SessionState
    {
        $state = $this->at($state, $now);

        if ($state->phase === Phase::Idle) {
            return $state;
        }

        return $state->with(phase: Phase::Idle, taskId: null, phaseStartedAt: null, pausedAt: null, pausedSeconds: 0);
    }

    public function pause(SessionState $state, CarbonImmutable $now): SessionState
    {
        $state = $this->at($state, $now);

        if (! $state->phase->isTimed() || $state->isPaused()) {
            return $state;
        }

        return $state->with(pausedAt: $now->getTimestamp());
    }

    public function resume(SessionState $state, CarbonImmutable $now): SessionState
    {
        if (! $state->isPaused()) {
            return $state;
        }

        $paused = max(0, $now->getTimestamp() - $state->pausedAt);

        return $this->at($state->with(pausedAt: null, pausedSeconds: $state->pausedSeconds + $paused), $now);
    }

    public function remainingSeconds(SessionState $state, CarbonImmutable $now): int
    {
        $state = $this->at($state, $now);

        if (! $state->phase->isTimed()) {
            return 0;
        }

        return max(0, $this->endsAt($state) - ($state->pausedAt ?? $now->getTimestamp()));
    }

    private function endsAt(SessionState $state): int
    {
        $minutes = match ($state->phase) {
            Phase::Work => $this->rhythm->workMinutes,
            Phase::ShortBreak => $this->rhythm->shortBreakMinutes,
            Phase::LongBreak => $this->rhythm->longBreakMinutes,
            default => 0,
        };

        return $state->phaseStartedAt + $minutes * 60 + $state->pausedSeconds;
    }

    private function breakAfterSession(SessionState $state, int $startsAt, ?int $taskId): SessionState
    {
        $number = $state->sessionsToday + 1;

        return $state->with(
            phase: $this->rhythm->isLongBreakAfter($number) ? Phase::LongBreak : Phase::ShortBreak,
            taskId: $taskId,
            phaseStartedAt: $startsAt,
            pausedAt: null,
            pausedSeconds: 0,
            sessionsToday: $number,
        );
    }
}
