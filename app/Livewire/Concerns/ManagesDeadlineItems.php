<?php

namespace App\Livewire\Concerns;

use App\Models\AgendaEntry;
use App\Services\ProgressStats;

/**
 * The two "tick it off" actions behind the all-day deadline strip (partials/schedule-deadline-item),
 * shared by the Zeitplan and step 3 of the Vorbereitung — the two pages that render that strip.
 */
trait ManagesDeadlineItems
{
    /**
     * Ticks a task off straight from the strip. Deliberately duplicates ManagesTasks::toggleComplete()
     * rather than pulling in the whole trait (its edit-sheet state isn't needed here) — the same small
     * duplication call already made between Task::effectiveDateLabel() and AgendaEntry::dateLabel().
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

        $task->syncLinkedAgendaEntry($user, $done);

        if ($done && ($celebration = ProgressStats::celebrationFor($user, $task, $before)) !== null) {
            $this->dispatch('celebrate', kind: $celebration['kind'], label: $celebration['label']);
        }
    }

    /** Ticks an Agenda entry off for this person only — see AgendaEntry::toggleDoneFor(). */
    public function toggleDeadlineAgendaDone(int $id): void
    {
        AgendaEntry::visibleTo(auth()->user())->findOrFail($id)->toggleDoneFor(auth()->user());
    }
}
