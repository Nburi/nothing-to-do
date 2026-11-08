<?php

namespace App\Planning;

use Carbon\CarbonImmutable;

/**
 * A resolved block on a concrete date, with real instants in the user's
 * timezone. `end` keeps the wall-clock end the user sees ("19:30–21:30"), so
 * on a DST day the real length differs from the stored duration.
 */
final readonly class Block
{
    public function __construct(
        public string $date,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ?int $categoryId,
        public ?int $templateId = null,
        public ?string $title = null,
    ) {}

    /** Real elapsed minutes, which is what sessions and capacity are measured in. */
    public function realMinutes(): int
    {
        return max(0, intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60));
    }
}
