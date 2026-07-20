<?php

namespace App\Services;

/**
 * Stateless Pomodoro phase math. A ScheduleEvent stores its current phase +
 * cycle explicitly (see ScheduleEvent::pomodoroPhaseNow()) so a phase can be
 * frozen — awaiting a manual continue — instead of always ticking forward;
 * this service only answers "how long is this phase" and "what comes after
 * it", given a rhythm. No database, no side effects.
 */
class PomodoroCycle
{
    public const WORK = 'work';

    public const SHORT_BREAK = 'short_break';

    public const LONG_BREAK = 'long_break';

    /** @param  array{work:int,short_break:int,long_break:int,long_every:int}  $rhythm */
    public static function durationMinutes(string $phase, array $rhythm): int
    {
        return match ($phase) {
            self::SHORT_BREAK => $rhythm['short_break'],
            self::LONG_BREAK => $rhythm['long_break'],
            default => $rhythm['work'],
        };
    }

    /**
     * The phase/cycle that follows the given one. A break follows work of the
     * same cycle number (long every Nth cycle); work follows a break with the
     * cycle number incremented.
     *
     * @param  array{work:int,short_break:int,long_break:int,long_every:int}  $rhythm
     * @return array{phase:string,cycle:int}
     */
    public static function next(string $phase, int $cycle, array $rhythm): array
    {
        if ($phase === self::WORK) {
            $isLong = $cycle % $rhythm['long_every'] === 0;

            return ['phase' => $isLong ? self::LONG_BREAK : self::SHORT_BREAK, 'cycle' => $cycle];
        }

        return ['phase' => self::WORK, 'cycle' => $cycle + 1];
    }
}
