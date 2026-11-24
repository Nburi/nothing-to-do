<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\PushNotifier;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Refactor step 3.3: the evening nudge at a fixed time per weekday (R2; risks F1, C2).
 *
 * The user sets one optional local time per ISO weekday (users.nudge_times). From that time
 * the reset is offered on the first visit of the evening, and one Web Push goes out that
 * opens /board?reset=1. The arrival trigger (iPhone Shortcut by location, spike 0.1,
 * POST /api/arrival) is this same nudge sent early: holdOnArrival() applies the same rules and
 * claim() uses the same evening mark, so whichever comes first sends the evening's only push.
 *
 * Rules for the push (R2):
 * - at most one per evening (evenings.nudge_sent_at, claimed atomically before sending);
 * - none on a weekday without a time, none on paused days, none once tonight's reset was
 *   started or closed, none while a focus session runs (it comes after, if still in time);
 * - a late scheduler tick still sends within CATCH_UP_MINUTES, never hours later;
 * - an arrival may send it from ARRIVAL_EARLY_MINUTES before the nudge time and at any time
 *   after, because it is a real signal and not a guess;
 * - no subscription: nothing happens. A failing push (or a broken VAPID config) is logged
 *   and not retried tonight.
 * - Nothing is counted and nothing reads nudge_sent_at beyond tonight (CONVENTIONS 11).
 *
 * Times are local "HH:MM" compared against UserClock, which uses the real IANA zone for
 * Zurich/London/Helsinki, so 18:00 stays 18:00 across both DST changes (C2).
 */
final class EveningNudge
{
    /** From when the reset is offered for a user who never set any nudge time (the 3.2 rule). */
    public const FALLBACK = '17:00';

    /** How late a missed scheduler tick may still send tonight's nudge. */
    public const CATCH_UP_MINUTES = 120;

    /**
     * How long before the nudge time an arrival may already send tonight's nudge. A lunch visit
     * or a trip past the house early in the day must not use up the evening's only nudge.
     */
    public const ARRIVAL_EARLY_MINUTES = 120;

    public const URL = '/board?reset=1';

    /** ISO weekdays, Monday first. */
    public const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * The user's valid nudge times, keyed by ISO weekday. Anything malformed is ignored.
     *
     * @return array<int, string>
     */
    public static function times(User $user): array
    {
        $times = [];

        foreach ((array) ($user->nudge_times ?? []) as $day => $time) {
            $day = (int) $day;
            if (in_array($day, self::WEEKDAYS, true) && is_string($time) && preg_match(self::TIME, $time)) {
                $times[$day] = $time;
            }
        }

        ksort($times);

        return $times;
    }

    /** True once the user has saved the card at least once (an all-empty map counts). */
    public static function configured(User $user): bool
    {
        return $user->nudge_times !== null;
    }

    /** Tonight's nudge time for the local date, or null when this weekday has none. */
    public function timeFor(User $user, CarbonImmutable $local): ?string
    {
        return self::times($user)[$local->dayOfWeekIso] ?? null;
    }

    /**
     * From when tonight's reset is offered on the first visit: the nudge time; the 3.2
     * fallback for users who never set anything; null (only the link or "Abschalten")
     * on a weekday the user deliberately left empty.
     */
    public function offerFrom(User $user, CarbonImmutable $local): ?string
    {
        if (! self::configured($user)) {
            return self::FALLBACK;
        }

        return $this->timeFor($user, $local);
    }

    /**
     * Saves the card. Empty weekdays are dropped. When today's new time has already passed,
     * tonight counts as nudged: the user is in the app right now and the board offers the
     * reset anyway; a push a minute later would only startle.
     *
     * @param  array<int|string, ?string>  $times
     */
    public function save(User $user, array $times, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $clean = [];

        foreach (self::WEEKDAYS as $day) {
            $time = trim((string) ($times[$day] ?? $times[(string) $day] ?? ''));
            if ($time !== '' && preg_match(self::TIME, $time)) {
                $clean[(string) $day] = $time;
            }
        }

        $user->forceFill(['nudge_times' => $clean])->save();

        $local = UserClock::now($user, $now);
        $today = $this->timeFor($user, $local);

        if ($today !== null && $local->format('H:i') >= $today) {
            $this->claim($user, $now);
        }
    }

    /** Whether tonight's nudge should go out now (no write). */
    public function isDue(User $user, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $local = UserClock::now($user, $now);
        $time = $this->timeFor($user, $local);

        if ($time === null) {
            return false;
        }

        $late = $this->minutes($local->format('H:i')) - $this->minutes($time);
        if ($late < 0 || $late >= self::CATCH_UP_MINUTES) {
            return false;
        }

        return $this->hold($user, $local, $now) === null;
    }

    /**
     * Why an arrival must not send tonight's nudge now, or null when it may (no write). The scheduler's
     * rules, except the clock: from ARRIVAL_EARLY_MINUTES before the nudge time, with no end.
     */
    public function holdOnArrival(User $user, ?CarbonImmutable $now = null): ?NudgeHold
    {
        $now ??= CarbonImmutable::now();
        $local = UserClock::now($user, $now);
        $time = $this->timeFor($user, $local);

        if ($time === null) {
            return NudgeHold::NoTime;
        }

        if ($this->minutes($local->format('H:i')) - $this->minutes($time) < -self::ARRIVAL_EARLY_MINUTES) {
            return NudgeHold::TooEarly;
        }

        return $this->hold($user, $local, $now);
    }

    /** What the scheduler and an arrival both check besides the clock. */
    private function hold(User $user, CarbonImmutable $local, CarbonImmutable $now): ?NudgeHold
    {
        $evening = Evening::for($user, $local->toDateString());

        if ($evening?->nudge_sent_at !== null) {
            return NudgeHold::AlreadySent;
        }

        if ($evening?->reset_started_at !== null || $evening?->reset_closed_at !== null) {
            return NudgeHold::ResetDone;
        }

        if (SchedulePause::forUser($user)->whereDate('date', $local->toDateString())->exists()) {
            return NudgeHold::Paused;
        }

        return app(FocusSession::class)->current($user, $now)->phase === Phase::Idle ? null : NudgeHold::InSession;
    }

    /** Claims tonight's nudge: true for exactly one caller per evening (an atomic update, `whereNull`). */
    public function claim(User $user, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $evening = Evening::forWrite($user, UserClock::now($user, $now)->toDateString());

        return Evening::query()->whereKey($evening->id)->whereNull('nudge_sent_at')->update(['nudge_sent_at' => $now]) === 1;
    }

    /**
     * Sends tonight's nudge when it is due. Returns true when a push went out. Two scheduler
     * runs at once cannot both send: the evening row is claimed before the push.
     */
    public function sendIfDue(User $user, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        if (! $user->pushSubscriptions()->exists() || ! $this->isDue($user, $now)) {
            return false;
        }

        if (! $this->claim($user, $now)) {
            return false;
        }

        try {
            // Resolved only here: a missing or broken VAPID config throws while building the
            // notifier, and that must cost this one nudge, not the scheduler run.
            app(PushNotifier::class)->notify($user, [
                'title' => __('shell.nudge.push.title'),
                'body' => __('shell.nudge.push.body'),
                'url' => self::URL,
            ]);
        } catch (\Throwable $e) {
            // The board still offers the reset from the nudge time; no retry storm tonight.
            Log::warning('Evening nudge could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
