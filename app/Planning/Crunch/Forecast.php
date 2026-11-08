<?php

namespace App\Planning\Crunch;

use App\Planning\DayLoad;
use App\Planning\FeasibilityResult;

/**
 * The load forecast (R5, R10): per day, capacity (25 minute sessions in
 * pomodoro blocks) against load (deadline work planned for it plus the work
 * that does not fit, put on its deadline day). Built by LoadPlanner.
 */
final readonly class Forecast
{
    /**
     * @param  list<DayLoad>  $days  today … horizon
     * @param  list<int>  $hardLateTaskIds  hard deadlines that cannot be met
     * @param  list<int>  $softLateTaskIds  soft deadlines that cannot be met, slipped ones included
     * @param  ?int  $slackMinutes  room at the tightest deadline (ignoring areas); null without deadlines
     */
    public function __construct(
        public array $days,
        public FeasibilityResult $plan,
        public array $hardLateTaskIds,
        public array $softLateTaskIds,
        public ?int $slackMinutes,
    ) {}

    public function level(): ?Level
    {
        return match (true) {
            $this->hardLateTaskIds !== [] => Level::Hard,
            $this->softLateTaskIds !== [] => Level::Soft,
            default => null,
        };
    }

    public function hasCrunch(): bool
    {
        return $this->level() !== null;
    }

    /** @return list<DayLoad> */
    public function overloadedDays(): array
    {
        return array_values(array_filter($this->days, fn (DayLoad $d) => $d->overloaded));
    }

    /** The first day that is overloaded, or null. */
    public function crunchDate(): ?string
    {
        return $this->overloadedDays()[0]->date ?? null;
    }

    /** Minutes that do not fit, for one level or for all. */
    public function missingMinutes(?Level $level = null): int
    {
        $ids = match ($level) {
            Level::Hard => $this->hardLateTaskIds,
            Level::Soft => $this->softLateTaskIds,
            null => [...$this->hardLateTaskIds, ...$this->softLateTaskIds],
        };

        return array_sum(array_map(fn (int $id) => $this->plan->missingMinutes[$id] ?? 0, $ids));
    }

    /**
     * What the user is asked to let go of first in a crunch (R5): soft
     * deadlines, the ones that already gave way to hard work first.
     *
     * @return list<int>
     */
    public function slipFirst(): array
    {
        return array_values(array_unique([...$this->plan->slippedSoftTaskIds, ...$this->softLateTaskIds]));
    }

    public function day(string $date): ?DayLoad
    {
        foreach ($this->days as $day) {
            if ($day->date === $date) {
                return $day;
            }
        }

        return null;
    }
}
