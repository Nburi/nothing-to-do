<?php

namespace App\Services\Planning;

use App\Models\EventCategory;
use App\Planning\Crunch\Level;

/** Refactor step 4.2: the crunch as the board and the crunch screen show it. Built by CrunchOverview. */
final readonly class CrunchView
{
    /**
     * @param  list<array{date: string, label: string, sessions: int, capacity: int, load: int, overloaded: bool}>  $days
     * @param  list<CrunchItem>  $items  soft first, then hard by deadline
     * @param  ?array{date: string, label: string, start: string, end: string, categoryId: int, sessions: int}  $moreTime
     * @param  list<EventCategory>  $categories  the pomodoro categories, for "Mehr Zeit"
     */
    public function __construct(
        public ?Level $level,
        public ?string $crunchDate,
        public ?string $crunchLabel,
        public ?string $windowEnd,
        public int $missingMinutes,
        public int $missingSessions,
        public ?int $slackMinutes,
        public array $days,
        public array $items,
        public ?array $moreTime,
        public array $categories,
    ) {}

    public function hasCrunch(): bool
    {
        return $this->level !== null;
    }

    public function isHard(): bool
    {
        return $this->level === Level::Hard;
    }
}
