<?php

namespace App\Planning;

/**
 * A week plan category (REQUIREMENTS § 4). Only categories with the pomodoro
 * switch create work time. `areaIds` are the task areas that may be suggested
 * inside its blocks (category ↔ area link).
 */
final readonly class Category
{
    /** @param  list<int>  $areaIds */
    public function __construct(
        public int $id,
        public bool $pomodoro,
        public array $areaIds = [],
    ) {}

    /** A task without an area fits any pomodoro category; otherwise the area must be linked. */
    public function accepts(?int $areaId): bool
    {
        return $areaId === null || in_array($areaId, $this->areaIds, true);
    }
}
