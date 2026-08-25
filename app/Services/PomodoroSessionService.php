<?php

namespace App\Services;

use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists Pomodoro phase transitions and sends the matching push
 * notification, gated by the user's notify_pomo_start/notify_break_start
 * preference. Consolidates logic that used to be duplicated between
 * TaskBoard (Livewire) and ScheduleEventController (Sanctum API) — both now
 * call through here, so a Shortcuts-driven transition notifies exactly like
 * a tab-open one.
 */
class PomodoroSessionService
{
    public function __construct(private readonly PushNotifier $pushNotifier) {}

    /**
     * Start the Pomodoro focus timer on a category block. Always manual,
     * regardless of the autostart setting — that only governs transitions
     * *after* this first session. Deliberately does not push a notification:
     * unlike every other phase change, this one is a direct result of the
     * user's own just-now tap, so telling them "your focus session begins"
     * a moment later is pure noise, not new information.
     *
     * Reconciles the WorkPlanner plan first (a no-op when the feature is
     * off) so the block's linked tasks — what the focus card is about to
     * show — reflect anything that changed since the plan was last touched,
     * not whatever happened to be linked the last time someone opened the
     * Planer page.
     */
    public function start(ScheduleEvent $event, User $user): void
    {
        WorkPlanner::reconcile($user);

        $event->update([
            'pomodoro_phase' => PomodoroCycle::WORK,
            'pomodoro_cycle' => 1,
            'pomodoro_started_at' => now(),
            'pomodoro_linked_notified' => false,
        ]);
    }

    /** Fully ends the session — a fresh Start is needed to begin again. */
    public function stop(ScheduleEvent $event): void
    {
        $event->update([
            'pomodoro_started_at' => null,
            'pomodoro_phase' => null,
            'pomodoro_cycle' => 1,
            'pomodoro_linked_notified' => false,
        ]);
    }

    /**
     * Manually advance to the next queued phase (a continue after a freeze,
     * or an explicit "next" action). Persists, but — like start() — never
     * notifies: this is always a direct result of the user's own tap
     * (they've already seen the "session ended" push sent when the phase
     * froze, see handleTick()), so notifying again here would just repeat
     * something they already know and just acted on.
     *
     * Reconciles the WorkPlanner plan first, same reasoning as start() — a
     * manual continue is a real "the user is looking at this block right
     * now" moment, so it's one of the points the plan is kept current at.
     *
     * @param  array{work:int,short_break:int,long_break:int,long_every:int}  $rhythm
     * @return array{phase:string,cycle:int}
     */
    public function transition(ScheduleEvent $event, User $user, array $rhythm): array
    {
        WorkPlanner::reconcile($user);

        $next = PomodoroCycle::next($event->pomodoro_phase, $event->pomodoro_cycle, $rhythm);

        $event->update([
            'pomodoro_phase' => $next['phase'],
            'pomodoro_cycle' => $next['cycle'],
            'pomodoro_started_at' => now(),
        ]);

        return $next;
    }

    /**
     * Skip the current or upcoming break entirely and jump straight into the
     * next work session. Works whether the break is actively running or
     * still frozen awaiting its own manual start. Returns false if there is
     * nothing to skip (no session, or the current/next phase isn't a break).
     * Never notifies, for the same reason transition() doesn't — it's a
     * direct result of the user's own tap.
     */
    public function skipBreak(ScheduleEvent $event, User $user, array $rhythm): bool
    {
        if ($event->pomodoro_phase === null) {
            return false;
        }

        WorkPlanner::reconcile($user);

        $phase = $event->pomodoro_phase;
        $cycle = $event->pomodoro_cycle;

        // Frozen: the "current" phase for skip purposes is whatever a continue would start.
        if ($event->pomodoro_started_at === null) {
            $next = PomodoroCycle::next($phase, $cycle, $rhythm);
            $phase = $next['phase'];
            $cycle = $next['cycle'];
        }

        if (! in_array($phase, [PomodoroCycle::SHORT_BREAK, PomodoroCycle::LONG_BREAK], true)) {
            return false;
        }

        $next = PomodoroCycle::next($phase, $cycle, $rhythm);
        $event->update([
            'pomodoro_phase' => $next['phase'],
            'pomodoro_cycle' => $next['cycle'],
            'pomodoro_started_at' => now(),
        ]);

        return true;
    }

    /**
     * Advance a session whose current phase has actually elapsed — called
     * both by the client's local countdown (handlePhaseComplete, re-checked
     * server-side before acting) and, critically, by the per-minute
     * scheduled command that keeps ticking sessions forward even with no
     * tab open. Unlike a single transition(), this cascades through however
     * many phases have fully elapsed (carrying the real elapsed time
     * forward), mirroring ScheduleEvent::pomodoroPhaseNow()'s read-side
     * self-heal loop — required so a session left running unattended for
     * multiple phases (e.g. across a cron gap) doesn't have its durations
     * silently compressed by a naive single-step advance.
     *
     * The read-compute-write is wrapped in a row lock: the client's own local
     * timer and the per-minute cron can both call this for the same event
     * around the same instant, and without a lock a stale read from one
     * caller could clobber the other's write (double-advancing the phase and
     * double-sending the notification, or reviving a session that a
     * concurrent stop() just ended). The push send itself happens after the
     * lock is released, so a slow outbound HTTP call never holds the row.
     */
    public function handleTick(ScheduleEvent $event, User $user, ?Carbon $now = null): void
    {
        $now ??= now();
        $phaseToNotify = null;
        $awaitingNotify = null;

        DB::transaction(function () use ($event, $user, $now, &$phaseToNotify, &$awaitingNotify) {
            $locked = ScheduleEvent::whereKey($event->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->pomodoro_phase === null || $locked->pomodoro_started_at === null) {
                return;
            }

            $rhythm = $user->pomodoro();
            $phase = $locked->pomodoro_phase;
            $cycle = $locked->pomodoro_cycle;
            $startedAt = $locked->pomodoro_started_at;
            $durationSeconds = PomodoroCycle::durationMinutes($phase, $rhythm) * 60;
            $elapsedSeconds = max(0, (int) $startedAt->diffInSeconds($now, false));

            if ($elapsedSeconds < $durationSeconds) {
                return; // not actually finished yet — ignore a premature/stray call
            }

            if (! $user->pomodoro_autostart) {
                $locked->update(['pomodoro_started_at' => null]);

                // This freeze *is* the real end of the phase — the only moment that
                // isn't a direct result of the user's own tap, so it's the one place
                // that should notify. What comes next (awaiting a manual continue)
                // is what the notification names.
                $awaitingNotify = PomodoroCycle::next($phase, $cycle, $rhythm)['phase'];

                return;
            }

            while ($elapsedSeconds >= $durationSeconds) {
                $elapsedSeconds -= $durationSeconds;
                $startedAt = $startedAt->copy()->addSeconds($durationSeconds);
                $next = PomodoroCycle::next($phase, $cycle, $rhythm);
                $phase = $next['phase'];
                $cycle = $next['cycle'];
                $durationSeconds = PomodoroCycle::durationMinutes($phase, $rhythm) * 60;
            }

            $locked->update([
                'pomodoro_phase' => $phase,
                'pomodoro_cycle' => $cycle,
                'pomodoro_started_at' => $startedAt,
            ]);

            $phaseToNotify = $phase;
        });

        if ($phaseToNotify !== null) {
            // A real advance happened (possibly cascading through several
            // phases) — reconcile once afterwards, outside the row lock, same
            // as the other transition points. Deliberately not called for the
            // "freeze awaiting a continue" branch below: nothing about which
            // block is showing what has changed there, only the countdown state.
            WorkPlanner::reconcile($user);
            $this->notifyPhaseStart($user, $phaseToNotify);
        } elseif ($awaitingNotify !== null) {
            $this->notifyPhaseEnded($user, $awaitingNotify);
        }
    }

    /** Sends a push notification for a phase that just auto-started (autostart cascade), gated by the user's per-type preference. */
    private function notifyPhaseStart(User $user, string $phase): void
    {
        $enabled = $phase === PomodoroCycle::WORK ? $user->notify_pomo_start : $user->notify_break_start;

        if (! $enabled) {
            return;
        }

        [$title, $body] = match ($phase) {
            PomodoroCycle::WORK => ['Pomodoro', 'Neue Fokus-Session beginnt'],
            PomodoroCycle::LONG_BREAK => ['Lange Pause', 'Zeit für eine längere Pause'],
            default => ['Kurze Pause', 'Zeit für eine kurze Pause'],
        };

        $this->pushNotifier->notify($user, ['title' => $title, 'body' => $body, 'url' => '/app/schedule']);
    }

    /**
     * Sends a push notification the moment a phase actually finishes and freezes
     * awaiting a manual continue (autostart off) — the genuine "it's over" moment,
     * naming what's due next. Gated by the same per-type preference as
     * notifyPhaseStart(): notify_pomo_start gates "a focus session is due"
     * (next = work), notify_break_start gates "a break is due" (next = break).
     */
    private function notifyPhaseEnded(User $user, string $nextPhase): void
    {
        $enabled = $nextPhase === PomodoroCycle::WORK ? $user->notify_pomo_start : $user->notify_break_start;

        if (! $enabled) {
            return;
        }

        [$title, $body] = match ($nextPhase) {
            PomodoroCycle::WORK => ['Pause vorbei', 'Zeit für die nächste Fokus-Session'],
            PomodoroCycle::LONG_BREAK => ['Fokus-Session beendet', 'Zeit für eine längere Pause'],
            default => ['Fokus-Session beendet', 'Zeit für eine kurze Pause'],
        };

        $this->pushNotifier->notify($user, ['title' => $title, 'body' => $body, 'url' => '/app/schedule']);
    }
}
