<?php

namespace App\Shell;

/**
 * One user-facing action of the new app (CONVENTIONS 2, REQUIREMENTS R7).
 *
 * `since` is the refactor step that builds it. Until then the action is listed (so nothing of
 * the plan's twelve is forgotten) but has no entry points yet; from then on ActionParityTest
 * requires an entry point in Werkbank and Stapel, in board and in session mode.
 */
final readonly class Action
{
    public function __construct(
        public string $key,
        public string $since,
        public bool $available,
    ) {}

    public function label(): string
    {
        return __("shell.actions.{$this->key}");
    }
}
