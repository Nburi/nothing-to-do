<?php

namespace App\Planning;

/**
 * What the engines need to know about a task: size, deadlines, area and how
 * much work is already done. Deadlines are local dates "Y-m-d" and mean
 * "finished by the end of that day".
 */
final readonly class PlannedTask
{
    public function __construct(
        public int $id,
        public Size $size,
        public ?int $estimatedSessions = null,
        public ?string $hardDeadline = null,
        public ?string $softDeadline = null,
        public ?int $areaId = null,
        public int $doneMinutes = 0,
    ) {}

    public function remainingMinutes(): int
    {
        return max(0, $this->size->workMinutes($this->estimatedSessions) - $this->doneMinutes);
    }

    public function hasDeadline(): bool
    {
        return $this->hardDeadline !== null || $this->softDeadline !== null;
    }

    /** The deadline that binds first; on the same date the hard one. */
    public function bindingDeadline(): ?string
    {
        return match (true) {
            $this->hardDeadline === null => $this->softDeadline,
            $this->softDeadline === null => $this->hardDeadline,
            default => min($this->hardDeadline, $this->softDeadline),
        };
    }

    public function bindingIsHard(): bool
    {
        return $this->hardDeadline !== null && $this->hardDeadline === $this->bindingDeadline();
    }
}
