<?php

namespace App\Livewire;

use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskBoard extends Component
{
    /** Quick-add. */
    public string $newTitle = '';

    public string $newList = 'inbox';

    /** Active mobile page: inbox | todos | tasks | today. */
    public string $mobileTab = 'inbox';

    /** Inline edit sheet. */
    public ?int $editingId = null;

    public string $editTitle = '';

    public ?string $editDeadline = null;

    public ?string $editDueDate = null;

    // ── Reads (computed, cached per request) ──────────────────────────

    /** @return Collection<int, Task> */
    private function active(string $list): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->inList($list)
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function inbox(): Collection
    {
        return $this->active('inbox');
    }

    #[Computed]
    public function todosToday(): Collection
    {
        return $this->active('todos')->where('is_today', true)->values();
    }

    #[Computed]
    public function todosRest(): Collection
    {
        return $this->active('todos')->where('is_today', false)->values();
    }

    #[Computed]
    public function tasksToday(): Collection
    {
        return $this->active('tasks')->where('is_today', true)->values();
    }

    #[Computed]
    public function tasksRest(): Collection
    {
        return $this->active('tasks')->where('is_today', false)->values();
    }

    /** Mobile "Today" page: every focused task across todos + tasks. */
    #[Computed]
    public function today(): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        $base = Task::query()->forUser(auth()->user())->active();

        return [
            'inbox' => (clone $base)->inList('inbox')->count(),
            'todos' => (clone $base)->inList('todos')->count(),
            'tasks' => (clone $base)->inList('tasks')->count(),
            'today' => (clone $base)->where('is_today', true)->count(),
        ];
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    /** Always resolve a task through the owner relationship — never trust an id alone. */
    private function userTask(int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
    }

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'today'], true)) {
            return;
        }

        $this->mobileTab = $tab;

        // Quick-add on a list tab targets that list; the Today tab can't be added to.
        if (in_array($tab, Task::LISTS, true)) {
            $this->newList = $tab;
        }
    }

    public function addTask(): void
    {
        $data = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newList' => ['required', 'in:inbox,todos,tasks'],
        ]);

        auth()->user()->tasks()->create([
            'title' => trim($data['newTitle']),
            'list' => $data['newList'],
            'sort_order' => 0,
        ]);

        $this->newTitle = '';
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

    /** Set/clear the Today focus. Inbox tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox()) {
            return;
        }

        $task->update(['is_today' => $value]);
    }

    /**
     * Drag & drop persistence. Receives the full ordered list of task ids now
     * sitting in one zone (a column, or a column's Today area) and rewrites
     * their list / today / order to match. The source zone needs no update —
     * a moved task simply drops out of its old column's query.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(string $list, bool $today, array $ids): void
    {
        if (! in_array($list, Task::LISTS, true)) {
            return;
        }

        // Inbox has no Today area.
        $today = $list === 'inbox' ? false : $today;

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $task->update([
                'list' => $list,
                'is_today' => $today,
                'sort_order' => $position,
            ]);
        }
    }

    /** Mobile swipe outcomes. */
    public function swipeIntent(int $id, string $intent): void
    {
        $task = $this->userTask($id);

        match ($intent) {
            'todos' => $task->update(['list' => 'todos']),
            'tasks' => $task->update(['list' => 'tasks']),
            'today' => $task->isInbox() ? null : $task->update(['is_today' => true]),
            default => null,
        };
    }

    // ── Edit sheet ────────────────────────────────────────────────────

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

        $data = $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
            'editDeadline' => ['nullable', 'date'],
            'editDueDate' => ['nullable', 'date'],
        ]);

        $this->userTask($this->editingId)->update([
            'title' => trim($data['editTitle']),
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

    public function render()
    {
        return view('livewire.task-board');
    }
}
