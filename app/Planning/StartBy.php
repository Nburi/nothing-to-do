<?php

namespace App\Planning;

/**
 * Outcome of DeadlineFeasibility::startBy(): the latest local date each dated
 * task can start and still be finished by its binding deadline, with every
 * deadline task planned as late as possible.
 */
final readonly class StartBy
{
    /**
     * @param  array<int, string>  $dates  task id => latest start date "Y-m-d"
     * @param  list<int>  $lateTaskIds  tasks that cannot be finished in time even when started now
     */
    public function __construct(
        public array $dates,
        public array $lateTaskIds,
    ) {}

    public function of(int $taskId): ?string
    {
        return $this->dates[$taskId] ?? null;
    }

    public function isLate(int $taskId): bool
    {
        return in_array($taskId, $this->lateTaskIds, true);
    }
}
