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

    /** Every local day with a "today set" (today-list and/or Planer plan), mapped to {total, done}. */
    #[Computed]
    public function todayListStats(): array
    {
        return ProgressStats::todayListStatsByDay(auth()->user());
    }

    /** {date => 'perfect'|'frozen'} — the streak's one authoritative basis, see ProgressStats::dailyOutcomeMap(). */
    #[Computed]
    public function outcomeMap(): array
    {
        return ProgressStats::dailyOutcomeMap(auth()->user(), $this->todayListStats, $this->counts);
    }

    #[Computed]
    public function currentStreak(): int
    {
        return ProgressStats::currentStreak(auth()->user(), $this->outcomeMap);
    }

    #[Computed]
    public function bestStreak(): int
    {
        return ProgressStats::bestStreak($this->outcomeMap);
    }

    #[Computed]
    public function perfectDaysCount(): int
    {
        return ProgressStats::perfectDaysCount($this->outcomeMap);
    }

    /** Null when nothing has ever been decided yet — "not applicable" rather than a misleading 0%. */
    #[Computed]
    public function perfectDayRate(): ?int
    {
        return ProgressStats::perfectDayRate($this->outcomeMap);
    }

    /** How many freezes this trailing week has already used — see ProgressStats::freezesUsedInTrailingWeek(). */
    #[Computed]
    public function freezesUsedThisWeek(): int
    {
        // +1 day so an already-frozen *today* (impossible in practice — see
        // evaluatePastDay()'s "never for today" rule — but harmless either
        // way) would still be included if it ever happened.
        return ProgressStats::freezesUsedInTrailingWeek(auth()->user(), auth()->user()->localToday()->addDay());
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
        return ProgressStats::heatmap(auth()->user(), $this->counts, outcomeMap: $this->outcomeMap);
    }

    /**
     * Today has real completions, but no task was ever flagged "Heute" (or
     * planned via the Planer) today, AND today still isn't perfect by any of
     * the today-set-free rules either (goal reached, whole board cleared) —
     * so the streak genuinely has nothing to count yet, which otherwise reads
     * as broken rather than "not started". UX research finding: easy to hit
     * in Eisenhower, where "Heute" is one small toggle pill per card rather
     * than a whole visible zone/column the way it is in 3 Things/Kanban, so
     * it's easy to never use at all.
     */
    #[Computed]
    public function todayHasCompletionsButNoTodayList(): bool
    {
        $today = auth()->user()->localToday()->toDateString();

        return $this->todayCount > 0
            && ! isset($this->todayListStats[$today])
            && ($this->outcomeMap[$today] ?? null) !== 'perfect';
    }

    public function render()
    {
        return view('livewire.progress');
    }
}
