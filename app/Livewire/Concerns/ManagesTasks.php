<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Shared task mutations + the inline edit sheet. Used by both the main
 * TaskBoard and the per-project ProjectPage so the two surfaces behave
 * identically. Every write resolves the task through the owner relationship,
 * so an id alone is never trusted.
 */
trait ManagesTasks
{
    /** Inline edit sheet. */
    public ?int $editingId = null;

    public string $editTitle = '';

    public ?string $editDeadline = null;

    public ?string $editDueDate = null;

    public ?int $editProjectId = null;

    /** Always resolve a task through the owner relationship — never trust an id alone. */
    protected function userTask(int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
    }

    /** Projects available to assign in the edit sheet. */
    #[Computed]
    public function editableProjects(): Collection
    {
        return auth()->user()->projects()->ordered()->get();
    }

    public function toggleImportant(int $id): void
    {
        $task = $this->userTask($id);
        $task->update(['is_important' => ! $task->is_important]);
    }

    public function toggleComplete(int $id): void
    {
        $task = $this->userTask($id);
        $done = ! $task->is_completed;

        $task->update([
            'is_completed' => $done,
            'completed_at' => $done ? now() : null,
        ]);
    }

    public function startEdit(int $id): void
    {
        $task = $this->userTask($id);
        $this->editingId = $task->id;
        $this->editTitle = $task->title;
        $this->editDeadline = $task->deadline?->toDateString();
        $this->editDueDate = $task->due_date?->toDateString();
        $this->editProjectId = $task->project_id;
    }

    public function saveEdit(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->editTitle = trim($this->editTitle);

        $data = $this->validate([
            'editTitle'     => ['required', 'string', 'max:255'],
            'editDeadline'  => ['nullable', 'date'],
            'editDueDate'   => ['nullable', 'date'],
            'editProjectId' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', auth()->id())],
        ]);

        $task = $this->userTask($this->editingId);

        $updates = [
            'title'    => $data['editTitle'],
            'deadline' => $data['editDeadline'] ?: null,
            'due_date' => $data['editDueDate'] ?: null,
        ];

        $newProjectId = $data['editProjectId'] ? (int) $data['editProjectId'] : null;

        if ($newProjectId !== $task->project_id) {
            if ($newProjectId !== null) {
                // Moving into a project — task leaves the board
                $updates['project_id'] = $newProjectId;
                $updates['list'] = 'projects';
                $updates['is_today'] = false;
            } else {
                // Removing from a project — task lands in inbox
                $updates['project_id'] = null;
                $updates['list'] = 'inbox';
            }
        }

        $task->update($updates);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editTitle', 'editDeadline', 'editDueDate', 'editProjectId']);
    }

    public function deleteTask(int $id): void
    {
        $this->userTask($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }
}
