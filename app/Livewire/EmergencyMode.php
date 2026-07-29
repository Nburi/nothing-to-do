<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Notfallmodus" — pick a forgotten/urgent project, sequence its tasks into
 * the order they'll actually get done, and hand that project the spotlight
 * on the main board. Starting/ending is a single pointer on the user
 * (emergency_project_id); the sequence itself is just the project's own
 * task sort_order, so it stays intact as the project's real order even
 * after emergency mode ends.
 */
#[Layout('layouts.app')]
class EmergencyMode extends Component
{
    use ManagesTasks;

    public ?int $selectedProjectId = null;

    /** Quick-add within the arrange screen. */
    public string $newTitle = '';

    public function mount(): void
    {
        $requested = (int) request()->query('project');

        $projectId = $requested > 0
            ? auth()->user()->projects()->find($requested)?->id
            : auth()->user()->emergency_project_id;

        if ($projectId !== null) {
            $this->selectProject($projectId);
        }
    }

    /** Always re-resolve through the owner relationship — never trust the id alone. */
    #[Computed]
    public function project(): ?Project
    {
        return $this->selectedProjectId
            ? auth()->user()->projects()->find($this->selectedProjectId)
            : null;
    }

    /** True once this project is the one actually live on the dashboard (not just being arranged). */
    #[Computed]
    public function isActiveEmergency(): bool
    {
        return $this->selectedProjectId !== null
            && $this->selectedProjectId === auth()->user()->emergency_project_id;
    }

    /** Picker step: every project with its active-task count, for the "which one?" list. */
    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->projects()
            ->ordered()
            ->withCount(['tasks as active_count' => fn ($q) => $q->where('is_completed', false)])
            ->get();
    }

    /**
     * The full sequence for the selected project — one flat, manually-ordered
     * list. Each task also carries an emergency_list tag (inbox/todos/tasks)
     * saying which dashboard column it should surface under; the sequence
     * itself doesn't change per column.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function orderedTasks(): Collection
    {
        if ($this->project === null) {
            return collect();
        }

        return $this->project->tasks()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    // ── Writes ────────────────────────────────────────────────────────

    public function selectProject(int $id): void
    {
        $project = auth()->user()->projects()->find($id);

        if ($project === null) {
            return;
        }

        $this->selectedProjectId = $project->id;

        // Every row needs a concrete column to render under from the start.
        $project->tasks()->active()->whereNull('emergency_list')->update(['emergency_list' => 'tasks']);
    }

    public function backToPicker(): void
    {
        $this->selectedProjectId = null;
    }

    public function addTask(): void
    {
        if ($this->project === null) {
            return;
        }

        $this->newTitle = trim($this->newTitle);

        $data = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = ((int) $this->project->tasks()->active()->max('sort_order')) + 1;

        auth()->user()->tasks()->create([
            'title' => $data['newTitle'],
            'list' => 'projects',
            'project_id' => $this->project->id,
            'emergency_list' => 'tasks',
            'sort_order' => $nextOrder,
        ]);

        $this->reset('newTitle');
    }

    /** Assign which dashboard column (inbox/todos/tasks) this task surfaces under. */
    public function setTaskList(int $taskId, string $list): void
    {
        if (! in_array($list, Task::BOARD_LISTS, true)) {
            return;
        }

        $task = $this->userTask($taskId);

        if ($this->project === null || $task->project_id !== $this->project->id) {
            return;
        }

        $task->update(['emergency_list' => $list]);
    }

    /**
     * Drag & drop persistence for the sequence — the destination is always
     * the same single list, so this is a full renumber, not a cross-zone move.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorderTasks(array $ids): void
    {
        if ($this->project === null) {
            return;
        }

        foreach (array_values($ids) as $position => $id) {
            $task = $this->project->tasks()->find((int) $id);

            if ($task === null) {
                continue;
            }

            $task->update(['sort_order' => $position]);
        }
    }

    /** Declares this project the live emergency focus and jumps to the dashboard. */
    public function start(): void
    {
        if ($this->project === null) {
            return;
        }

        auth()->user()->update(['emergency_project_id' => $this->project->id]);

        $this->redirectRoute('app', navigate: true);
    }

    /** Ends emergency mode entirely — tasks and their order are left exactly as arranged. */
    public function end(): void
    {
        auth()->user()->update(['emergency_project_id' => null]);

        $this->redirectRoute('app', navigate: true);
    }

    public function render()
    {
        return view('livewire.emergency-mode');
    }
}
