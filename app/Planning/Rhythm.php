<?php

namespace App\Planning;

/**
 * The pomodoro rules of the refactor (REQUIREMENTS § 3): 25 minutes of work,
 * 5 minute breaks, every fourth break 15 minutes, and the 5 minute threshold
 * that decides between "next task inside this session" and "break now".
 */
final readonly class Rhythm
{
    public function __construct(
        public int $workMinutes = 25,
        public int $shortBreakMinutes = 5,
        public int $longBreakMinutes = 15,
        public int $longBreakEvery = 4,
        public int $nextTaskThresholdMinutes = 5,
    ) {}

    /** Length of the break that follows the n-th session of a day (1-based). */
    public function breakMinutesAfter(int $sessionNumber): int
    {
        return $this->isLongBreakAfter($sessionNumber) ? $this->longBreakMinutes : $this->shortBreakMinutes;
    }

    public function isLongBreakAfter(int $sessionNumber): bool
    {
        return $sessionNumber > 0 && $sessionNumber % $this->longBreakEvery === 0;
    }
}
