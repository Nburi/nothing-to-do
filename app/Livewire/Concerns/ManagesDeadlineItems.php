<?php

namespace App\Livewire\Concerns;

use App\Services\DayPlanner;
use App\Services\ProgressStats;

/**
 * The two "tick it off" actions behind the all-day deadline strip (partials/schedule-deadline-item),
 * used by the Zeitplan, the page that renders that strip.
 */
trait ManagesDeadlineItems
{
    /**
     * Ticks a task off straight from the strip. Deliberately duplicates ManagesTasks::toggleComplete()
     * rather than pulling in the whole trait (its edit-sheet state isn't needed here).
     */
    public function toggleDeadlineTaskDone(int $id): void
    {
        $task = auth()->user()->tasks()->findOrFail($id);
        $done = ! $task->is_completed;
        $user = auth()->user();

        $before = $done ? ProgressStats::todayCount($user) : null;

        $task->update([
            'is_completed' => $done,
            'completed_at' => $done ? now() : null,
        ]);

        $task->syncRepeat($user, $done);

        if ($done && ($celebration = ProgressStats::celebrationFor($user, $task, $before)) !== null) {
            $this->dispatch('celebrate', kind: $celebration['kind'], label: $celebration['label']);
        }
    }

    /**
     * The tick on a split task's part in the strip: marks that part done (the task
     * itself stays open — see DayPlanner::completeSession()). A foreign id is a no-op.
     */
    public function toggleDeadlinePartDone(int $sessionId): void
    {
        $result = DayPlanner::completeSession(auth()->user(), $sessionId);

        if ($result !== null && $result['celebration'] !== null) {
            $this->dispatch('celebrate', kind: $result['celebration']['kind'], label: $result['celebration']['label']);
        }
    }
}
