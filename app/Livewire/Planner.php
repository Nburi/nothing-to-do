<?php

namespace App\Livewire;

use App\Services\DayPlanner;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Planer" — a day-by-day board. Drag a task onto a day; the day itself,
 * not a specific calendar block, is what's remembered, so moving/resizing a
 * Training block on the Zeitplan never strands what was planned around it.
 * Placement is manual by default; autoFillBacklog() is the one optional,
 * purely-additive convenience (see App\Services\DayPlanner).
 */
#[Layout('layouts.app')]
class Planner extends Component
{
    /** Off by default (users.planner_enabled) — visiting the route directly while it's off just bounces back. */
    public function mount(): void
    {
        if (! auth()->user()->planner_enabled) {
            $this->redirectRoute('app', navigate: true);
        }
    }

    /** HORIZON_DAYS days starting today, each with its capacity and planned tasks. */
    #[Computed]
    public function board(): Collection
    {
        return DayPlanner::board(auth()->user());
    }

    /** Every dated-or-not task/homework with no day yet. */
    #[Computed]
    public function backlog(): Collection
    {
        return DayPlanner::backlog(auth()->user());
    }

    /** Dated backlog items whose deadline has already passed — the always-visible "too late" list. */
    #[Computed]
    public function conflicts(): Collection
    {
        return DayPlanner::conflicts(auth()->user());
    }

    /**
     * Persists one day's full order — the destination of a drag, the same
     * "send the whole ordered list" shape TaskBoard::reorder() uses. Each
     * entry is a "task:<id>" or "agenda:<id>" token (see
     * DayPlanner::assignDay() for how an agenda token gets promoted).
     * Ownership of every id is verified inside the service, item by item.
     *
     * @param  array<int, string>  $items
     */
    public function assignDay(string $date, array $items): void
    {
        DayPlanner::assignDay(auth()->user(), $date, $items);
        $this->refreshComputeds();
    }

    /**
     * The mobile day-picker sheet's tap-a-day action (see
     * DayPlanner::moveToDay()) — appends one chip to the end of that day,
     * for the case where the caller doesn't already have the day's full
     * order to hand the way a desktop drag-drop does.
     */
    public function moveToDay(string $token, string $date): void
    {
        DayPlanner::moveToDay(auth()->user(), $token, $date);
        $this->refreshComputeds();
    }

    /** The small "×" on a placed task, and the backlog's own drop zone — releases a task back to the backlog. */
    public function unassignTask(int $taskId): void
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);

        DayPlanner::unassignTask($task);
        $this->refreshComputeds();
    }

    /**
     * "Rest automatisch einplanen" — purely additive, so unlike the old
     * block planner's "Neu planen" this needs no armed confirmation: it can
     * only ever fill tasks that have no day yet, never touch one you've
     * already placed by hand.
     */
    public function autoFillBacklog(): void
    {
        DayPlanner::autoFillBacklog(auth()->user());
        $this->refreshComputeds();
    }

    private function refreshComputeds(): void
    {
        unset($this->board, $this->backlog, $this->conflicts);
    }

    public function render()
    {
        return view('livewire.planner');
    }
}
