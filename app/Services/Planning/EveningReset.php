<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\ResetStage;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Refactor step 3.2: the ten minute reset that starts the evening, and the battery
 * question at its end (R2, G4, CONVENTIONS 11; risk F2).
 *
 * - The state lives on tonight's Evening row, so a reload or a second device shows the
 *   same screen (like the session, step 1.7).
 * - Offered once per evening: from DUE_FROM local time, or earlier when the nudge link
 *   (/board?reset=1) asks for it. Not on paused days, not during a running session, never
 *   again once it was done or skipped. Starting it by hand ("Abschalten") always works.
 * - Ten minutes at most. Skipping or ending early just closes it: nothing is counted, no
 *   streak, no message. A reset that ran out is closed by time alone, so reads never write.
 * - At its end the battery is asked once, unless tonight's battery was already answered
 *   (the chip counts). "Später" counts as Okay; the chip changes it later.
 */
final class EveningReset
{
    public const MINUTES = 10;

    /**
     * Local time from which the reset is offered on the first visit of the evening. Step 3.3
     * replaces this with the nudge time per weekday; the nudge itself links to /board?reset=1.
     */
    public const DUE_FROM = '17:00';

    public function stage(User $user, bool $requested = false, bool $inSession = false, ?CarbonImmutable $now = null): ResetStage
    {
        $now ??= CarbonImmutable::now();
        $local = UserClock::now($user, $now);
        $evening = Evening::for($user, $local->toDateString());

        if ($this->isRunning($evening, $now)) {
            return ResetStage::Running;
        }

        if ($this->isClosed($evening, $now)) {
            // The end of the reset asks the battery once (G4).
            return $evening->batteryAsked() ? ResetStage::Hidden : ResetStage::Battery;
        }

        if ($inSession || $this->isPaused($user, $local->toDateString())) {
            return ResetStage::Hidden;
        }

        $due = $requested || $local->format('H:i') >= self::DUE_FROM;

        return $due ? ResetStage::Offer : ResetStage::Hidden;
    }

    /** Seconds left in a running reset, else the full ten minutes. */
    public function remainingSeconds(User $user, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $evening = Evening::for($user, UserClock::now($user, $now)->toDateString());

        if (! $this->isRunning($evening, $now)) {
            return self::MINUTES * 60;
        }

        $end = $evening->reset_started_at->addMinutes(self::MINUTES);

        return max(0, (int) $now->diffInSeconds($end, false));
    }

    /** "Los", or "Abschalten" by hand: the ten minutes start now (again, if it ran before). */
    public function begin(User $user, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $this->evening($user, $now)->update(['reset_started_at' => $now, 'reset_closed_at' => null]);
    }

    /** "Überspringen" or ending early: silent, nothing is counted. */
    public function close(User $user, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $evening = $this->evening($user, $now);

        if ($this->isClosed($evening, $now)) {
            return; // the other device was faster, or the time ran out: keep the first close
        }

        $evening->update(['reset_closed_at' => $now]);
    }

    /** The question at the end of the reset. Null is "Später", which counts as Okay. */
    public function answerBattery(User $user, ?Battery $battery, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        if ($battery !== null) {
            app(Suggestions::class)->setBattery($user, $battery, $now);

            return;
        }

        // "Später" leaves the level alone: unset is Okay, a level set earlier stays.
        $this->evening($user, $now)->update(['battery_asked_at' => $now]);
    }

    private function evening(User $user, CarbonImmutable $now): Evening
    {
        return Evening::forWrite($user, UserClock::now($user, $now)->toDateString());
    }

    private function isRunning(?Evening $evening, CarbonImmutable $now): bool
    {
        return $evening?->reset_started_at !== null
            && $evening->reset_closed_at === null
            && $now->lessThan($evening->reset_started_at->addMinutes(self::MINUTES));
    }

    private function isClosed(?Evening $evening, CarbonImmutable $now): bool
    {
        if ($evening === null) {
            return false;
        }

        return $evening->reset_closed_at !== null
            || ($evening->reset_started_at !== null && ! $this->isRunning($evening, $now));
    }

    private function isPaused(User $user, string $date): bool
    {
        return SchedulePause::forUser($user)->whereDate('date', $date)->exists();
    }
}
