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
class ProjectPage extends Component
{
    use ManagesTasks;

    public int $projectId;

    /** Quick-add for tasks inside this project. */
    public string $newTitle = '';

    /** Inline rename. */
    public string $projectName = '';

    public bool $renaming = false;

    public function mount(Project $project): void
    {
        // Route binding loaded it; enforce ownership before trusting it.
        abort_unless($project->user_id === auth()->id(), 404);

        $this->projectId = $project->id;
        $this->projectName = $project->name;
    }

    /** Always re-resolve through the owner relationship — never trust the id alone. */
    #[Computed]
    public function project(): Project
    {
        return auth()->user()->projects()->findOrFail($this->projectId);
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function tasks(): Collection
    {
        return auth()->user()->tasks()
            ->where('project_id', $this->projectId)
            ->active()
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function doneCount(): int
    {
        return auth()->user()->tasks()
            ->where('project_id', $this->projectId)
            ->where('is_completed', true)
            ->count();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->tasks->count() + $this->doneCount;
    }

    /** Inbox tasks available to pull into this project. */
    #[Computed]
    public function inboxTasks(): Collection
    {
        return auth()->user()->tasks()
            ->active()
            ->onBoard()
            ->inList('inbox')
            ->boardOrdered()
            ->get();
    }

    // ── Writes ────────────────────────────────────────────────────────

    public function addTask(): void
    {
        $this->newTitle = trim($this->newTitle);

        $data = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
        ]);

        auth()->user()->tasks()->create([
            'title' => $data['newTitle'],
            'list' => 'projects',
            'project_id' => $this->projectId,
            'sort_order' => 0,
        ]);

        $this->newTitle = '';
    }

    /** Pull an existing inbox task into this project. */
    public function assignToProject(int $taskId): void
    {
        $task = $this->userTask($taskId);

        $task->update([
            'project_id' => $this->projectId,
            'list' => 'projects',
            'is_today' => false,
        ]);
    }

    /** Release a task from the project back into the inbox. */
    public function removeFromProject(int $taskId): void
    {
        $task = $this->userTask($taskId);

        if ($task->project_id !== $this->projectId) {
            return;
        }

        $task->update([
            'project_id' => null,
            'list' => 'inbox',
        ]);

        if ($this->editingId === $taskId) {
            $this->cancelEdit();
        }
    }

    public function saveRename(): void
    {
        $this->projectName = trim($this->projectName);

        $data = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
        ]);

        $this->project->update(['name' => $data['projectName']]);
        $this->renaming = false;
    }

    public function cancelRename(): void
    {
        $this->projectName = $this->project->name;
        $this->renaming = false;
    }

    public function deleteProject(): void
    {
        // Release active tasks back to the inbox; completed ones go with the
        // project (null-on-delete keeps them, but they're already done/hidden).
        auth()->user()->tasks()
            ->where('project_id', $this->projectId)
            ->where('is_completed', false)
            ->update(['project_id' => null, 'list' => 'inbox']);

        $this->project->delete();

        $this->redirectRoute('app', navigate: true);
    }

    public function render()
    {
        return view('livewire.project-page');
    }
}
