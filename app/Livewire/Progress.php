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

    #[Computed]
    public function currentStreak(): int
    {
        return ProgressStats::currentStreak(auth()->user(), $this->counts);
    }

    #[Computed]
    public function bestStreak(): int
    {
        return ProgressStats::bestStreak($this->counts);
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

    public function render()
    {
        return view('livewire.progress');
    }
}
