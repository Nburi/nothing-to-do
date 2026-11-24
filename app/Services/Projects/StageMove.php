<?php

namespace App\Services\Projects;

/**
 * What ProjectEditor::moveStage() did to the roadmap. The page keeps `order` and `cleared` for the
 * toast's "Rückgängig" (ProjectEditor::restoreStageOrder() reads them again).
 */
final readonly class StageMove
{
    /**
     * @param  array<int, int>  $order  the stage ids in their old roadmap order
     * @param  array<int, int>  $cleared  waiting step id => the step it waited on, for every link the move let go
     */
    public function __construct(
        public bool $changed,
        public int $fromIndex,
        public int $toIndex,
        public array $order,
        public array $cleared = [],
    ) {}

    /** @param  array<int, int>  $cleared */
    public function withCleared(array $cleared): self
    {
        return new self($this->changed, $this->fromIndex, $this->toIndex, $this->order, $cleared);
    }
}
