<?php

namespace App\Planning;

/**
 * A week plan category (REQUIREMENTS § 4). Only categories with the pomodoro
 * switch create work time. `areaIds` are the task areas that may be suggested
 * inside its blocks (category ↔ area link).
 */
final readonly class Category
{
    /**
     * @param  list<int>  $areaIds
     * @param  string  $name  only for showing (the block plan names its block); the engines ignore it
     * @param  int|null  $linkedProjectId  "suggest this project's next steps in my blocks" (category link, step 5.4)
     * @param  int|null  $linkedGroupId  the same for a group
     */
    public function __construct(
        public int $id,
        public bool $pomodoro,
        public array $areaIds = [],
        public string $name = '',
        public ?int $linkedProjectId = null,
        public ?int $linkedGroupId = null,
    ) {}

    /** Whether the user pointed this category at the project or group the task is a step of. */
    public function prefers(PlannedTask $task): bool
    {
        return ($this->linkedProjectId !== null && $task->projectId === $this->linkedProjectId)
            || ($this->linkedGroupId !== null && $task->groupId === $this->linkedGroupId);
    }

    /** A task without an area fits any pomodoro category; otherwise the area must be linked. */
    public function accepts(?int $areaId): bool
    {
        return $areaId === null || in_array($areaId, $this->areaIds, true);
    }
}
