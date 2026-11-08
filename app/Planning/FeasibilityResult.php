<?php

namespace App\Planning;

/** Outcome of DeadlineFeasibility::check(). */
final readonly class FeasibilityResult
{
    /**
     * @param  array<int, ?string>  $finishesOn  task id => date of its last planned minute, null when late
     * @param  array<int, array<string, int>>  $minutesByDate  task id => [date => minutes]
     * @param  list<int>  $lateTaskIds  tasks that cannot be finished by their binding deadline
     * @param  list<int>  $slippedSoftTaskIds  soft tasks that would fit alone but give way to hard deadlines
     * @param  array<int, int>  $missingMinutes  late task id => minutes that do not fit
     * @param  array<string, int>  $sessionsUsed  date => sessions with planned work
     */
    public function __construct(
        public array $finishesOn,
        public array $minutesByDate,
        public array $lateTaskIds,
        public array $slippedSoftTaskIds,
        public array $missingMinutes,
        public ?string $firstLateDeadline,
        private array $sessionsUsed,
    ) {}

    public function sessionsUsedOn(string $date): int
    {
        return $this->sessionsUsed[$date] ?? 0;
    }
}
