<?php

namespace App\Planning\Crunch;

/** The stored state of a user's early warning (table crunch_alerts). */
final readonly class AlertState
{
    public function __construct(
        public bool $active = false,
        public ?Level $level = null,
        public ?string $crunchDate = null,
        public int $missingMinutes = 0,
        public ?string $raisedOn = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
