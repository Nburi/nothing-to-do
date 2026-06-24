<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /** Free-form Markdown scratchpad for ideas and thoughts about the project. */
    public string $brainstorm = '';

    /** Whether the brainstorm space shows the editor (true) or the rendered notes (false). */
    public bool $editingBrainstorm = false;

    /** URL of an external task board linked to this project (e.g. Jira, GitHub, Linear). */
    public string $externalUrl = '';

    /** Whether the external-link inline form is open. */
    public bool $editingExternalLink = false;

    /** Project-level deadline (date string or empty). */
    public string $projectDeadline = '';

    /** Whether the deadline inline form is open. */
    public bool $editingDeadline = false;

    public function mount(Project $project): void
    {
        // Route binding loaded it; enforce ownership before trusting it.
        abort_unless($project->user_id === auth()->id(), 404);

        $this->projectId = $project->id;
        $this->projectName = $project->name;
        $this->brainstorm = (string) ($project->brainstorm ?? '');
        $this->externalUrl = (string) ($project->external_url ?? '');
        $this->projectDeadline = $project->deadline?->toDateString() ?? '';

        // Empty projects open straight into the editor for fast capture.
        $this->editingBrainstorm = $this->brainstorm === '';
    }

    /** Always re-resolve through the owner relationship — never trust the id alone. */
    #[Computed]
    public function project(): Project
    {
        return auth()->user()->projects()->findOrFail($this->projectId);
    }

    /**
     * Active project tasks + tasks completed within the current visibility window.
     * Active tasks come first (orderBy is_completed ASC prepended before boardOrdered).
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        $windowStart = auth()->user()->completedWindowStart();

        return auth()->user()->tasks()
            ->where('project_id', $this->projectId)
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')
            ->boardOrdered()
            ->get();
    }

    /** All-time completed count — used for the progress bar. */
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
        // Use active count from tasks() to avoid double-counting recently completed.
        return $this->tasks->where('is_completed', false)->count() + $this->doneCount;
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

    /**
     * The brainstorming notes rendered to safe HTML for the read view.
     * GitHub-flavoured Markdown (headings, lists, task lists, links); raw HTML
     * is stripped and unsafe link schemes dropped, so user input can't inject.
     */
    #[Computed]
    public function brainstormHtml(): string
    {
        $text = trim($this->brainstorm);

        if ($text === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    // ── Writes ────────────────────────────────────────────────────────

    public function editBrainstorm(): void
    {
        $this->editingBrainstorm = true;
        $this->dispatch('brainstorm-focus');
    }

    /** Persist the buffer and leave the editor (the "Fertig" button). */
    public function stopEditingBrainstorm(): void
    {
        $this->saveBrainstorm();
        $this->editingBrainstorm = false;
    }

    /** Autosave the brainstorming notes — fires on every Livewire model sync. */
    public function updatedBrainstorm(): void
    {
        $this->saveBrainstorm();
        $this->dispatch('brainstorm-saved');
    }

    /** Validate and write the notes through the owner relationship; empty stores null. */
    protected function saveBrainstorm(): void
    {
        $data = $this->validate([
            'brainstorm' => ['nullable', 'string', 'max:50000'],
        ]);

        $text = trim((string) ($data['brainstorm'] ?? ''));

        $this->project->update(['brainstorm' => $text !== '' ? $this->brainstorm : null]);
    }

    public function editExternalLink(): void
    {
        $this->externalUrl = (string) ($this->project->external_url ?? '');
        $this->editingExternalLink = true;
    }

    public function saveExternalLink(): void
    {
        $data = $this->validate([
            'externalUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        $url = trim((string) ($data['externalUrl'] ?? ''));
        $this->project->update(['external_url' => $url !== '' ? $url : null]);
        $this->externalUrl = $url;
        $this->editingExternalLink = false;
    }

    public function removeExternalLink(): void
    {
        $this->project->update(['external_url' => null]);
        $this->externalUrl = '';
    }

    public function editDeadline(): void
    {
        $this->projectDeadline = $this->project->deadline?->toDateString() ?? '';
        $this->editingDeadline = true;
    }

    public function saveDeadline(): void
    {
        $data = $this->validate([
            'projectDeadline' => ['nullable', 'date'],
        ]);

        $date = trim((string) ($data['projectDeadline'] ?? ''));
        $this->project->update(['deadline' => $date !== '' ? $date : null]);
        $this->projectDeadline = $date;
        $this->editingDeadline = false;
    }

    public function removeDeadline(): void
    {
        $this->project->update(['deadline' => null]);
        $this->projectDeadline = '';
    }

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
