<?php

namespace App\Planning\Pick;

use App\Planning\PlannedTask;
use App\Planning\Size;

/**
 * A task the pick engine may suggest: the engine's view of it plus its place
 * on the user's own board (`position`, lower = higher up), which is the last
 * tie-breaker, so tasks without deadlines keep the order the user gave them.
 */
final readonly class PickCandidate
{
    public function __construct(
        public PlannedTask $task,
        public int $position = 0,
    ) {}

    public function id(): int
    {
        return $this->task->id;
    }

    public function isQuick(): bool
    {
        return $this->task->size === Size::Quick;
    }

    public function isBig(): bool
    {
        return $this->task->size === Size::Long || $this->task->size === Size::Block;
    }
}
