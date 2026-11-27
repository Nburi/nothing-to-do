<?php

namespace App\Planning;

/**
 * What the engines need to know about a task: size, deadlines, area, how
 * much work is already done and whether the user marked it „Wichtig" (R5,
 * `tasks.is_important`). Deadlines are local dates "Y-m-d" and mean
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
        public bool $important = false,
        public ?int $projectId = null,
        public ?int $groupId = null,
    ) {}

    /** The same task with another hard deadline, for what-if checks. Nothing is saved. */
    public function withHardDeadline(string $date): self
    {
        return new self($this->id, $this->size, $this->estimatedSessions, $date, $this->softDeadline, $this->areaId, $this->doneMinutes, $this->important, $this->projectId, $this->groupId);
    }

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
