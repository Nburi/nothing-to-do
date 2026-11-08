<?php

namespace App\Planning;

/**
 * A block on one date that does not come from a template (a "Termin" in the
 * old app). Without a category it is a fixed appointment.
 */
final readonly class OneOffBlock
{
    public function __construct(
        public string $date,
        public string $start,
        public int $durationMinutes,
        public ?int $categoryId = null,
        public ?string $title = null,
    ) {}
}
