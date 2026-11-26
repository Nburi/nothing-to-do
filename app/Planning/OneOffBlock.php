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
        // Step 4.1 (editor only): the schedule_events row, and the template of an occurrence that
        // was moved into another category this week only (the reader turns it into a one-off).
        public ?int $eventId = null,
        public ?int $templateId = null,
        public ?string $tint = null, // palette key of a Termin, null = Standard (editor only)
    ) {}
}
