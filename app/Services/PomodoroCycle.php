<?php

namespace App\Services;

/**
 * Stateless Pomodoro cycle math: given the minutes elapsed since a category's
 * focus timer was started, resolve which phase (work / short break / long
 * break) is active now and its minute boundaries. No database, no side
 * effects — ScheduleEvent::pomodoroPhaseNow() is the only caller.
 */
class PomodoroCycle
{
    public const WORK = 'work';

    public const SHORT_BREAK = 'short_break';

    public const LONG_BREAK = 'long_break';

    /**
     * @param  array{work:int,short_break:int,long_break:int,long_every:int}  $rhythm
     * @return array{phase:string,cycle:int,phase_start_minute:int,phase_end_minute:int}
     */
    public static function at(int $elapsedMinutes, array $rhythm): array
    {
        $elapsedMinutes = max(0, $elapsedMinutes);
        $cursor = 0;
        $cycle = 1;

        while (true) {
            $workEnd = $cursor + $rhythm['work'];

            if ($elapsedMinutes < $workEnd) {
                return [
                    'phase' => self::WORK,
                    'cycle' => $cycle,
                    'phase_start_minute' => $cursor,
                    'phase_end_minute' => $workEnd,
                ];
            }

            $cursor = $workEnd;
            // The break after a work cycle is long when that cycle's number is
            // divisible by long_every (i.e. after every Nth session).
            $isLong = $cycle % $rhythm['long_every'] === 0;
            $breakEnd = $cursor + ($isLong ? $rhythm['long_break'] : $rhythm['short_break']);

            if ($elapsedMinutes < $breakEnd) {
                return [
                    'phase' => $isLong ? self::LONG_BREAK : self::SHORT_BREAK,
                    'cycle' => $cycle,
                    'phase_start_minute' => $cursor,
                    'phase_end_minute' => $breakEnd,
                ];
            }

            $cursor = $breakEnd;
            $cycle++;
        }
    }
}
