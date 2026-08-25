<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesSchedule;
use App\Models\ScheduleEvent;
use App\Services\WorkPlanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Planer" — deliberately narrow: only about which task/todo/homework
 * happens in which upcoming work-block. Not a general schedule editor (no
 * creating Termine/categories, that stays on Zeitplan/Wochenplan); reads and
 * writes the same ScheduleEvent data as the real Zeitplan, so a block moved
 * here really is moved there too — no shadow schedule to drift out of sync.
 *
 * `use ManagesSchedule` is pulled in purely for moveEvent()/resizeEvent(),
 * which the `scheduleEvent` Alpine component (drag-move/resize) already
 * calls by exactly those names — this page needs no gesture code of its own
 * for repositioning a block. The rest of that trait (the full event-creation
 * form) is unused here, which costs nothing.
 */
#[Layout('layouts.app')]
class Planner extends Component
{
    use ManagesSchedule;

    /**
     * Off by default (users.planner_enabled) — visiting the route directly
     * while it's off just bounces back, same as the nav pill never showing
     * it in the first place.
     *
     * Reconciling here — once, on the page's initial full load — rather than
     * inside the blocks() computed property is deliberate: a #[Computed]
     * property re-evaluates on every render, and Livewire re-renders the
     * whole view after every action on this page. Reconciling from inside it
     * would mean every drag, unassign, or block move triggers a full
     * replan-of-the-auto-layer immediately afterwards — fighting the very
     * action the user just took (confirmed by a failing test: removing a
     * task from a block, then watching WorkPlanner instantly refill the same
     * slot from the backlog on the next render). Once per page load is
     * enough to satisfy "never stale when you're actually looking at it".
     */
    public function mount(): void
    {
        if (! auth()->user()->planner_enabled) {
            $this->redirectRoute('app', navigate: true);

            return;
        }

        WorkPlanner::reconcile(auth()->user());
    }

    /** Upcoming work-blocks with their linked tasks, grouped by day. */
    #[Computed]
    public function blocks(): Collection
    {
        return WorkPlanner::upcomingBlocks(auth()->user());
    }

    /** Dated items with no placement before their own deadline — the always-visible "won't make it" list. */
    #[Computed]
    public function conflicts(): Collection
    {
        return WorkPlanner::conflicts(auth()->user());
    }

    protected function userWorkBlock(int $id): ScheduleEvent
    {
        return auth()->user()->scheduleEvents()
            ->whereHas('category', fn ($q) => $q->where('pomodoro_enabled', true))
            ->findOrFail($id);
    }

    /**
     * Drag & drop persistence for one block — receives the full ordered list
     * of task ids now sitting in it, the same shape TaskBoard::reorder()
     * already uses for board columns. Every id landing here is stamped
     * 'manual', whether it was already in this block (a reorder) or just
     * arrived from another one (a cross-block move) — the user touched it
     * by hand, so reconcile() must never reshuffle it again. A task can only
     * ever sit in one block, so its link anywhere else is dropped first.
     *
     * @param  array<int, int|string>  $taskIds
     */
    public function reorderBlock(int $blockId, array $taskIds): void
    {
        $user = auth()->user();
        $block = $this->userWorkBlock($blockId);

        $ids = collect($taskIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $user->tasks()->whereKey($id)->exists())
            ->values();

        if ($ids->isEmpty()) {
            $block->linkedTasks()->sync([]);

            return;
        }

        DB::table('schedule_event_task_links')
            ->whereIn('task_id', $ids)
            ->where('schedule_event_id', '!=', $block->id)
            ->delete();

        $sync = $ids->mapWithKeys(fn ($id, $position) => [$id => ['sort_order' => $position, 'source' => 'manual']])->all();

        $block->linkedTasks()->sync($sync);
        unset($this->blocks, $this->conflicts);
    }

    /** The small "x" on a task chip — releases it back to the unplaced pool without touching any other block. */
    public function unassignTask(int $taskId): void
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);

        DB::table('schedule_event_task_links')->where('task_id', $task->id)->delete();
        unset($this->blocks, $this->conflicts);
    }

    /**
     * "Neu planen" — a full, explicit replan that also discards manual
     * placements (see WorkPlanner::regenerate()). The one action here that
     * can throw away a deliberate choice, so the button in the view is
     * gated behind the app's usual armed double-click, not a plain click.
     */
    public function regenerate(): void
    {
        WorkPlanner::regenerate(auth()->user());
        unset($this->blocks, $this->conflicts);
    }

    public function render()
    {
        return view('livewire.planner');
    }
}
