<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Planning\FreeSlot;

/** Outcome of FreeTimeIdeas::current(): what the board shows for free time (step 3.4). */
final readonly class IdeaOffer
{
    public function __construct(
        public IdeaStatus $status,
        /** The free slot running now, or else the next one today; null when none is left. */
        public ?FreeSlot $slot = null,
        /** True when $slot runs now (false: it is the next one). */
        public bool $slotIsNow = false,
        /** The slot was kept "mine". */
        public bool $mine = false,
        /** The idea (a Later task) for Offer. */
        public ?Task $task = null,
        /** Ideas in Later not offered yet today, besides $task ("Andere" has somewhere to go). */
        public int $alternatives = 0,
        /** For Deadlines: the task whose deadline does not fit. */
        public ?Task $blocking = null,
    ) {}

    public function hasIdea(): bool
    {
        return $this->status === IdeaStatus::Offer && $this->task !== null;
    }

    /** "Meins behalten" makes sense: there is a free slot now or later today that is not kept yet. */
    public function canKeepMine(): bool
    {
        return $this->slot !== null && ! $this->mine;
    }
}
