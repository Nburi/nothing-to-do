<?php

namespace App\Planning\Crunch;

final readonly class SlipDecision
{
    public function __construct(
        public SlipAction $action,
        public ?string $newSoftDeadline = null,
    ) {}
}
