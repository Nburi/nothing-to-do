<?php

namespace App\Planning\Pick;

/**
 * One ranked suggestion. `minutes` is the work suggested tonight: the task's
 * remaining work, or one session when the battery shrank it (`shrunk`).
 */
final readonly class Pick
{
    public function __construct(
        public int $taskId,
        public Reason $reason,
        public int $minutes,
        public bool $shrunk = false,
        public ?string $deadline = null,
        public bool $deadlineIsHard = false,
        public ?string $startBy = null,
    ) {}
}
