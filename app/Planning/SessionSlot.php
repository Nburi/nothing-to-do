<?php

namespace App\Planning;

use Carbon\CarbonImmutable;

/** One 25 minute work session that fits into a pomodoro block. */
final readonly class SessionSlot
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $categoryId,
        public int $numberOfDay,
    ) {}
}
