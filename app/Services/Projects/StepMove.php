<?php

namespace App\Services\Projects;

/**
 * Step 4.3c: what ProjectEditor::place() did to one step. The page keeps these numbers for the
 * toast's "Rückgängig" (ProjectEditor::putBack() reads them again), so an undo restores the place
 * and the "wartet auf" links the move had to let go.
 *
 * Places are 0-based positions among the open steps of one stage (or of the group), the step itself
 * included for `fromIndex` and `toIndex`.
 */
final readonly class StepMove
{
    /**
     * @param  array<int, int>  $cleared  waiting step id => the step it waited on, for every link the move let go
     */
    public function __construct(
        public bool $changed,
        public ?int $fromStageId,
        public int $fromIndex,
        public ?int $toStageId,
        public int $toIndex,
        public ?int $toStageNumber = null,
        public array $cleared = [],
    ) {}

    public function stageChanged(): bool
    {
        return $this->fromStageId !== $this->toStageId;
    }

    /** @param  array<int, int>  $cleared */
    public function withCleared(array $cleared): self
    {
        return new self($this->changed, $this->fromStageId, $this->fromIndex, $this->toStageId, $this->toIndex, $this->toStageNumber, $cleared);
    }

    public function withStageNumber(?int $number): self
    {
        return new self($this->changed, $this->fromStageId, $this->fromIndex, $this->toStageId, $this->toIndex, $number, $this->cleared);
    }
}
