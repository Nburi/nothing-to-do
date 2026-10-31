<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use App\Services\ProgressStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Shared task mutations + the inline edit sheet. Used by the board (and any other
 * surface that shows task cards). Every write resolves
 * the task through the owner relationship, so an id alone is never trusted.
 */
trait ManagesTasks
{
    /** Inline edit sheet. */
    public ?int $editingId = null;

    public string $editTitle = '';

    public ?string $editDeadline = null;

    public ?string $editDueDate = null;

    public ?int $editDuration = null;

    public string $editNotes = '';

    public string $editList = 'inbox';

    /** Always resolve a task through the owner relationship — never trust an id alone. */
    protected function userTask(int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
    }

    /** The notes buffer rendered to safe HTML for the edit sheet's preview. */
    #[Computed]
    public function editNotesHtml(): string
    {
        return Task::renderNotesMarkdown($this->editNotes);
    }

    public function toggleImportant(int $id): void
    {
        $task = $this->userTask($id);
        $task->update(['is_important' => ! $task->is_important]);
    }

    /**
     * The Planer-provenance icon's second tap (see Task::isTodayFromPlanner(),
     * partials/task-card*.blade.php) — a server-driven redirect rather than a
     * plain `<a href wire:navigate>`: Livewire's own wire:navigate click
     * listener fires independently of a Blade-side `@click` handler calling
     * `preventDefault()`, so a native link can't be made to wait for the
     * first "peek" tap and would navigate immediately on tap one. Mirrors
     * ManagesSchedule::navigateToLinkedTask()'s shape exactly.
     */
    public function goToPlanner(): void
    {
        $this->redirectRoute('planner', navigate: true);
    }

    public function toggleComplete(int $id): void
    {
        $task = $this->userTask($id);
        $done = ! $task->is_completed;
        $user = auth()->user();

        // Captured before the write: ProgressStats::celebrationFor() needs to know
        // where today's count stood a moment ago to detect the exact crossing.
        $before = $done ? ProgressStats::todayCount($user) : null;

        $task->update([
            'is_completed' => $done,
            'completed_at' => $done ? now() : null,
        ]);

        if ($done && ($celebration = ProgressStats::celebrationFor($user, $task, $before)) !== null) {
            $this->dispatch('celebrate', kind: $celebration['kind'], label: $celebration['label']);
        }
    }

    public function startEdit(int $id): void
    {
        $task = $this->userTask($id);
        $this->editingId = $task->id;
        $this->editTitle = $task->title;
        $this->editDeadline = $task->deadline?->toDateString();
        $this->editDueDate = $task->due_date?->toDateString();
        $this->editDuration = $task->duration_minutes;
        $this->editNotes = (string) ($task->notes ?? '');
        $this->editList = in_array($task->list, Task::LISTS, true) ? $task->list : 'tasks';
    }

    public function saveEdit(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->editTitle = trim($this->editTitle);

        $data = $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
            'editDeadline' => ['nullable', 'date'],
            'editDueDate' => ['nullable', 'date'],
            'editDuration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'editNotes' => ['nullable', 'string', 'max:5000'],
            'editList' => ['required', Rule::in(Task::LISTS)],
        ]);

        $task = $this->userTask($this->editingId);

        $notes = trim((string) $data['editNotes']);

        $updates = [
            'title' => $data['editTitle'],
            'deadline' => $data['editDeadline'] ?: null,
            'due_date' => $data['editDueDate'] ?: null,
            'duration_minutes' => $data['editDuration'] ?: null,
            'notes' => $notes !== '' ? $notes : null,
        ];

        $newList = $data['editList'];
        $updates['list'] = $newList;

        if (! in_array($newList, Task::TODAY_LISTS, true)) {
            $updates['is_today'] = false;
            $updates['today_date'] = null;
        }

        $task->update($updates);

        $this->cancelEdit();
    }

    /**
     * Quick-set just the deadline/due-date from the card face, bypassing the
     * full edit sheet. Silently ignores invalid input — there's no form here
     * to surface errors on, the popover just auto-saves on change.
     */
    public function quickSetDates(int $id, ?string $deadline, ?string $dueDate): void
    {
        $validator = Validator::make(
            ['deadline' => $deadline, 'due_date' => $dueDate],
            ['deadline' => ['nullable', 'date'], 'due_date' => ['nullable', 'date']],
        );

        if ($validator->fails()) {
            return;
        }

        $this->userTask($id)->update([
            'deadline' => $deadline ?: null,
            'due_date' => $dueDate ?: null,
        ]);
    }

    /**
     * Quick-set the duration estimate from the card face's ghost/badge
     * popover — same bypass-the-edit-sheet shape as quickSetDates(). Never
     * required (a null value clears the estimate back to "unset"), and the
     * card face is the warning: an unset estimate keeps showing the ghost.
     */
    public function quickSetDuration(int $id, ?int $minutes): void
    {
        if ($minutes !== null && ($minutes < 1 || $minutes > 600)) {
            return;
        }

        $this->userTask($id)->update(['duration_minutes' => $minutes]);
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editTitle', 'editDeadline', 'editDueDate', 'editDuration', 'editNotes', 'editList']);
    }

    public function deleteTask(int $id): void
    {
        $this->userTask($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }
}
