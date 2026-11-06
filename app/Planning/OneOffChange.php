<?php

namespace App\Planning;

/**
 * "This week only": changes one occurrence of a template on one date and
 * leaves the template itself untouched. In the old app this is an edited or
 * cancelled materialised ScheduleEvent row; null fields keep the template's value.
 */
final readonly class OneOffChange
{
    public function __construct(
        public int $templateId,
        public string $date,
        public ?string $start = null,
        public ?int $durationMinutes = null,
        public bool $cancelled = false,
    ) {}

    public function key(): string
    {
        return $this->templateId.'|'.$this->date;
    }
}
