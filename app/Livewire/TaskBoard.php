<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskBoard extends Component
{
    use ManagesTasks;

    /** Quick-add. */
    public string $newTitle = '';

    public string $newList = 'inbox';

    /** Add-project (Projects column / tab). */
    public string $newProjectName = '';

    /** Active mobile page: inbox | todos | tasks | today | projects. */
    public string $mobileTab = 'inbox';

    // ── Reads (computed, cached per request) ──────────────────────────

    /** @return Collection<int, Task> */
    private function active(string $list): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->onBoard()
            ->inList($list)
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function inbox(): Collection
    {
        return $this->active('inbox');
    }

    /** Whole-list collections, fetched once and split for the Today areas below. */
    #[Computed]
    public function todosAll(): Collection
    {
        return $this->active('todos');
    }

    #[Computed]
    public function tasksAll(): Collection
    {
        return $this->active('tasks');
    }

    #[Computed]
    public function todosToday(): Collection
    {
        return $this->todosAll->where('is_today', true)->values();
    }

    #[Computed]
    public function todosRest(): Collection
    {
        return $this->todosAll->where('is_today', false)->values();
    }

    #[Computed]
    public function tasksToday(): Collection
    {
        return $this->tasksAll->where('is_today', true)->values();
    }

    #[Computed]
    public function tasksRest(): Collection
    {
        return $this->tasksAll->where('is_today', false)->values();
    }

    /** Mobile "Today" page: every focused board task across todos + tasks. */
    #[Computed]
    public function today(): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->onBoard()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();
    }

    /**
     * Projects with their working set: every active task (ordered) for the
     * card preview + open count, plus a completed count for the progress label.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->forUser(auth()->user())
            ->ordered()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('activeTasks')
            ->get();
    }

    /** Derived from the already-loaded collections — no extra count queries. */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox' => $this->inbox->count(),
            'todos' => $this->todosAll->count(),
            'tasks' => $this->tasksAll->count(),
            'today' => $this->today->count(),
            'projects' => $this->projects->count(),
        ];
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'today', 'projects'], true)) {
            return;
        }

        $this->mobileTab = $tab;

        // Quick-add on a board-list tab targets that list. Today & Projects
        // don't feed the task quick-add, so leave the target untouched.
        if (in_array($tab, Task::BOARD_LISTS, true)) {
            $this->newList = $tab;
        }
    }

    public function addTask(): void
    {
        // Trim first so a whitespace-only title fails the required rule.
        $this->newTitle = trim($this->newTitle);

        $data = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newList' => ['required', 'in:inbox,todos,tasks'],
        ]);

        auth()->user()->tasks()->create([
            'title' => $data['newTitle'],
            'list' => $data['newList'],
            'sort_order' => 0,
        ]);

        $this->newTitle = '';
    }

    public function addProject(): void
    {
        $this->newProjectName = trim($this->newProjectName);

        $data = $this->validate([
            'newProjectName' => ['required', 'string', 'max:255'],
        ]);

        auth()->user()->projects()->create([
            'name' => $data['newProjectName'],
            'sort_order' => 0,
        ]);

        $this->newProjectName = '';
    }

    /**
     * Desktop drag & drop: a board task card dropped onto a project card.
     * Moves the task into that project (and off the board / out of Today).
     */
    public function assignTaskToProject(int $taskId, int $projectId): void
    {
        $task = $this->userTask($taskId);
        $project = auth()->user()->projects()->findOrFail($projectId);

        $task->update([
            'project_id' => $project->id,
            'list' => 'projects',
            'is_today' => false,
        ]);
    }

    /** Set/clear the Today focus. Inbox & project tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox() || $task->isInProject()) {
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
        // Only the three board columns are drag targets.
        if (! in_array($list, Task::BOARD_LISTS, true)) {
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
            'untoday' => $task->isInbox() ? null : $task->update(['is_today' => false]),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.task-board');
    }
}
