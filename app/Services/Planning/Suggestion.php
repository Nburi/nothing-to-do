<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Planning\Battery;
use App\Planning\Pick\Pick;
use Carbon\CarbonImmutable;

/**
 * What the idle board shows in its start strip (R1): one suggested task with
 * where to start and why, or the reason there is none.
 */
final readonly class Suggestion
{
    public function __construct(
        public SuggestionStatus $status,
        public Battery $battery,
        public ?Pick $pick = null,
        public ?Task $task = null,
        public ?string $startWith = null,
        public ?string $why = null,
        public ?int $categoryId = null,
        public ?CarbonImmutable $nextBlockAt = null,
        public int $alternatives = 0,
    ) {}

    public function hasPick(): bool
    {
        return $this->status === SuggestionStatus::Pick;
    }
}
