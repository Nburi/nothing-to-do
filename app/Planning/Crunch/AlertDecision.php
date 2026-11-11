<?php

namespace App\Planning\Crunch;

/**
 * Outcome of EarlyWarning::evaluate(): the new state, and whether to tell the
 * user now (`notify`: a fresh hard warning, at most one a day) or that the
 * crunch is over (`cleared`).
 */
final readonly class AlertDecision
{
    public function __construct(
        public AlertState $state,
        public bool $notify,
        public bool $cleared,
    ) {}
}
