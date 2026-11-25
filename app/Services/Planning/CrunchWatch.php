<?php

namespace App\Services\Planning;

use App\Models\CrunchAlert;
use App\Models\Task;
use App\Models\User;
use App\Planning\Crunch\AlertDecision;
use App\Planning\Crunch\AlertState;
use App\Planning\Crunch\EarlyWarning;
use App\Planning\Crunch\Forecast;
use App\Planning\Crunch\Level;
use App\Planning\Rhythm;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\PushNotifier;
use App\Support\Locales;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refactor step 1.4: the early warning with its stored state (hysteresis and
 * "at most one warning a day" need memory, see EarlyWarning). record() is the
 * one write path; check() runs it from the hourly planning command, the board
 * runs it with the forecast it already has (CrunchOverview).
 *
 * Step 4.2: the push (Niels' pick 2026-11-17, "sofort, aber nicht nachts").
 * - A decision that says `notify` (hard only, once a local day) marks a push
 *   as pending. Seen on the board first: nothing is pending, the user knows.
 * - pushIfDue() sends a pending push between 08:00 and 21:00 local, never into
 *   a running session (it comes after), at most once a local day. A crunch
 *   that cleared or went soft drops the pending push.
 * - The notifier is built only when a push goes out, inside a try: a server
 *   without VAPID keys or a failing push service costs this push (logged, not
 *   retried today), never the scheduler run.
 */
final class CrunchWatch
{
    public const URL = '/board?crunch=1';

    /** The push window, local wall-clock hours: from 08:00, until 21:00. */
    public const PUSH_FROM_HOUR = 8;

    public const PUSH_UNTIL_HOUR = 21;

    public function __construct(
        private readonly Capacity $capacity = new Capacity,
        private readonly EarlyWarning $warning = new EarlyWarning,
    ) {}

    public function state(User $user): AlertState
    {
        return CrunchAlert::query()->where('user_id', $user->id)->first()?->toState() ?? AlertState::none();
    }

    public function check(User $user, ?CarbonImmutable $now = null): AlertDecision
    {
        $now = UserClock::now($user, $now);

        return $this->record($user, $this->capacity->forecast($user, $now), $now);
    }

    /**
     * Stores the warning state for a forecast. `$seen`: the user is looking at the warning
     * right now (the board in board mode), so a new warning needs no push and a pending one
     * is dropped.
     */
    public function record(User $user, Forecast $forecast, CarbonImmutable $now, bool $seen = false): AlertDecision
    {
        $now = UserClock::now($user, $now);

        return DB::transaction(function () use ($user, $now, $forecast, $seen) {
            $row = CrunchAlert::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $decision = $this->warning->evaluate($forecast, $row?->toState() ?? AlertState::none(), $now->toDateString());
            $state = $decision->state;

            if ($row === null && ! $state->active) {
                return $decision; // nothing to remember yet
            }

            $row ??= new CrunchAlert(['active' => false]);
            $pending = $row->push_pending_since;
            if ($seen || ! $state->active || $state->level !== Level::Hard) {
                $pending = null;
            } elseif ($decision->notify) {
                $pending ??= $now;
            }

            $row->user_id = $user->id;
            $row->fill([
                'active' => $state->active,
                'level' => $state->level,
                'crunch_date' => $state->crunchDate,
                'missing_minutes' => $state->missingMinutes,
                'raised_on' => $state->raisedOn,
                'cleared_at' => $decision->cleared ? $now : ($state->active ? null : $row->cleared_at),
                'push_pending_since' => $pending,
            ])->save();

            return $decision;
        });
    }

    /** Sends a pending crunch push when it is due. Returns true when one went out. */
    public function pushIfDue(User $user, ?CarbonImmutable $now = null): bool
    {
        $local = UserClock::now($user, $now);
        $row = CrunchAlert::query()->where('user_id', $user->id)->first();

        if ($row?->push_pending_since === null) {
            return false;
        }
        if (! $row->active || $row->level !== Level::Hard) {
            $row->update(['push_pending_since' => null]);

            return false;
        }
        if (! $user->pushSubscriptions()->exists()) {
            $row->update(['push_pending_since' => null]); // nothing to send to; the board still shows it

            return false;
        }
        if ($local->hour < self::PUSH_FROM_HOUR || $local->hour >= self::PUSH_UNTIL_HOUR) {
            return false; // never at night; waits for the morning
        }
        if ($row->pushed_at !== null && UserClock::now($user, CarbonImmutable::instance($row->pushed_at))->toDateString() === $local->toDateString()) {
            $row->update(['push_pending_since' => null]); // at most one a day

            return false;
        }
        if (app(FocusSession::class)->current($user, $local)->phase !== Phase::Idle) {
            return false; // never into a session; comes after
        }

        // Two overlapping scheduler runs cannot both send: the pending flag is claimed first.
        $claimed = CrunchAlert::query()->whereKey($row->id)->whereNotNull('push_pending_since')
            ->update(['push_pending_since' => null, 'pushed_at' => $local->utc()]);
        if ($claimed !== 1) {
            return false;
        }

        try {
            $payload = Locales::within($user, fn () => $this->payload($user, $this->capacity->forecast($user, $local), $local));
            if ($payload === null) {
                return false; // solved in the meantime
            }
            // Resolved only here: a missing or broken VAPID config throws while building the notifier.
            app(PushNotifier::class)->notify($user, $payload);
        } catch (\Throwable $e) {
            Log::warning('Crunch warning could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    /** @return ?array{title: string, body: string, url: string} */
    private function payload(User $user, Forecast $forecast, CarbonImmutable $now): ?array
    {
        if ($forecast->level() !== Level::Hard) {
            return null;
        }

        $task = Task::forUser($user)->whereIn('id', $forecast->hardLateTaskIds)->get()
            ->sortBy(fn (Task $t) => [$t->hardDeadline()?->toDateString() ?? '9999-12-31', $t->id])->first();
        $date = $forecast->crunchDate() ?? $now->toDateString();
        $days = (int) CarbonImmutable::parse($now->toDateString(), $now->getTimezone())->diffInDays(CarbonImmutable::parse($date, $now->getTimezone()), false);
        $title = match (true) {
            $days <= 0 => __('shell.crunch.push.title_today'),
            $days === 1 => __('shell.crunch.push.title_tomorrow'),
            default => __('shell.crunch.push.title_on', ['day' => DayLabel::for($date, $now)]),
        };
        $sessions = (int) ceil($forecast->missingMinutes(Level::Hard) / (new Rhythm)->workMinutes);

        return [
            'title' => $title,
            'body' => trans_choice('shell.crunch.push.body', $sessions, ['count' => $sessions, 'task' => $task?->title ?? '']),
            'url' => self::URL,
        ];
    }
}
