<?php

namespace App\Planning;

use Carbon\CarbonImmutable;

/**
 * A stretch of free time on one local day (REQUIREMENTS § 4 "Free time", step 3.4): outside every
 * block of the week plan, fixed or pomodoro. Instants are in the user's timezone.
 */
final readonly class FreeSlot
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        /** No block at all today: the whole day is free (the bolder suggestion, R6). */
        public bool $wholeDay = false,
        /** The slot runs to the end of the day. */
        public bool $untilMidnight = false,
    ) {}

    public function contains(CarbonImmutable $at): bool
    {
        return $this->from->getTimestamp() <= $at->getTimestamp() && $at->getTimestamp() < $this->until->getTimestamp();
    }

    /** Minutes left in the slot from $at (its whole length when $at is before it). */
    public function minutesLeft(CarbonImmutable $at): int
    {
        $start = max($at->getTimestamp(), $this->from->getTimestamp());

        return max(0, intdiv($this->until->getTimestamp() - $start, 60));
    }

    public function overlaps(?CarbonImmutable $from, ?CarbonImmutable $until): bool
    {
        if ($from === null || $until === null) {
            return false;
        }

        return $from->getTimestamp() < $this->until->getTimestamp() && $until->getTimestamp() > $this->from->getTimestamp();
    }
}
