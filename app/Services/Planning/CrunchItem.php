<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Planning\Size;

/** Refactor step 4.2: one task on the crunch screen and what can be done with it. */
final readonly class CrunchItem
{
    public function __construct(
        public Task $task,
        public bool $hard,
        public string $deadline,
        public string $deadlineLabel,
        public int $minutes,
        /** One size smaller, or null when it is Quick already. */
        public ?Size $smaller,
        /** Soft only: where "Verschieben" puts the date in one tap. */
        public ?string $softMoveTo,
        /** Soft work in the crunch window: suggested to let go first (R5). */
        public bool $suggested,
    ) {}
}
