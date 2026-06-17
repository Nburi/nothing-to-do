<?php

namespace App\Livewire\Concerns;

use App\Models\Task;

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

    /** Always resolve a task through the owner relationship — never trust an id alone. */
    protected function userTask(int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
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
        ]);

        $this->userTask($this->editingId)->update([
            'title' => $data['editTitle'],
            'deadline' => $data['editDeadline'] ?: null,
            'due_date' => $data['editDueDate'] ?: null,
        ]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editTitle', 'editDeadline', 'editDueDate']);
    }

    public function deleteTask(int $id): void
    {
        $this->userTask($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }
}
