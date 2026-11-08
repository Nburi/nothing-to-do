<?php

namespace App\Planning;

/**
 * A repeating block of the week plan, same shape as the old EventTemplate:
 * ISO weekdays (1 = Monday … 7 = Sunday), a local start time "HH:MM" and a
 * duration in wall-clock minutes.
 */
final readonly class Template
{
    /** @param  list<int>  $weekdays */
    public function __construct(
        public int $id,
        public array $weekdays,
        public string $start,
        public int $durationMinutes,
        public ?int $categoryId = null,
        public ?string $title = null,
    ) {}

    public function occursOnWeekday(int $isoWeekday): bool
    {
        return in_array($isoWeekday, $this->weekdays, true);
    }
}
