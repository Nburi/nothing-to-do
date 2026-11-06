<?php

namespace App\Planning;

/** Capacity and load of one day (REQUIREMENTS § 4). */
final readonly class DayLoad
{
    public function __construct(
        public string $date,
        public int $capacitySessions,
        public int $capacityMinutes,
        public int $loadMinutes,
        public bool $overloaded,
    ) {}

    public function overByMinutes(): int
    {
        return max(0, $this->loadMinutes - $this->capacityMinutes);
    }
}
