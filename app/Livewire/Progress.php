<?php

namespace App\Livewire;

use App\Services\ProgressStats;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Read-only "how did your day go" page — everything here is derived from
 * ProgressStats, which itself is derived from tasks.completed_at. No writes
 * happen on this page; the daily goal is edited in Settings, and completion
 * itself happens on the board/project page/Zeitplan strip.
 */
#[Layout('layouts.app')]
class Progress extends Component
{
    /** Every local day with ≥1 completed task, mapped to how many — the one query everything else reuses. */
    #[Computed]
    public function counts(): array
    {
        return ProgressStats::completedCountsByDay(auth()->user());
    }

    #[Computed]
    public function todayCount(): int
    {
        return ProgressStats::todayCount(auth()->user(), $this->counts);
    }

    #[Computed]
    public function goal(): int
    {
        return auth()->user()->dailyTaskGoal();
    }

    /** Every local day with a today-list, mapped to {total, done} — the streak's one query. */
    #[Computed]
    public function todayListStats(): array
    {
        return ProgressStats::todayListStatsByDay(auth()->user());
    }

    /** Days where the today-list was fully cleared — the streak's basis, distinct from raw completion count. */
    #[Computed]
    public function successMap(): array
    {
        return ProgressStats::dailySuccessMap($this->todayListStats);
    }

    #[Computed]
    public function currentStreak(): int
    {
        return ProgressStats::currentStreak(auth()->user(), $this->successMap);
    }

    #[Computed]
    public function bestStreak(): int
    {
        return ProgressStats::bestStreak($this->successMap);
    }

    #[Computed]
    public function perfectDaysCount(): int
    {
        return ProgressStats::perfectDaysCount($this->successMap);
    }

    /** Null when no today-list has ever been set — "not applicable" rather than a misleading 0%. */
    #[Computed]
    public function perfectDayRate(): ?int
    {
        return ProgressStats::perfectDayRate($this->successMap);
    }

    #[Computed]
    public function bestDailyCount(): int
    {
        return ProgressStats::bestDailyCount($this->counts);
    }

    #[Computed]
    public function totalCompleted(): int
    {
        return array_sum($this->counts);
    }

    #[Computed]
    public function heatmap(): array
    {
        return ProgressStats::heatmap(auth()->user(), $this->counts);
    }

    /**
     * Today has real completions, but no task was ever flagged "Heute"
     * today, so the streak (which only counts a day once every "Heute"
     * task on it is done) has nothing to count and correctly stays at 0 —
     * without this, that reads as broken rather than as "not started yet".
     * UX research finding: easy to hit in Eisenhower, where "Heute" is one
     * small toggle pill per card rather than a whole visible zone/column
     * the way it is in 3 Things/Kanban, so it's easy to never use at all.
     */
    #[Computed]
    public function todayHasCompletionsButNoTodayList(): bool
    {
        return $this->todayCount > 0
            && ! isset($this->todayListStats[auth()->user()->localToday()->toDateString()]);
    }

    public function render()
    {
        return view('livewire.progress');
    }
}
