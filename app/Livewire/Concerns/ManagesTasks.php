<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use App\Models\TaskGroup;
use App\Services\ProgressStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Shared task mutations + the inline edit sheet. Used by TaskBoard, ProjectPage
 * and GroupPage so the three surfaces behave identically. Every write resolves
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

    public ?int $editProjectId = null;

    /** The task group this task belongs to (board lists only — never together with a project). */
    public ?int $editGroupId = null;

    /** Always resolve a task through the owner relationship — never trust an id alone. */
    protected function userTask(int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
    }

    /**
     * Called after a task may have left a group as part of one of this
     * trait's mutations (saveEdit, deleteTask) — dissolves the group if that
     * leaves it with one task or none (see TaskGroup::pruneIfTooSmall()).
     *
     * GroupPage overrides this to also redirect away when the group being
     * dissolved is the one its own page is showing: this trait has no notion
     * of "the current page's group", so on its own it can only do the
     * generic half (TaskBoard/ProjectPage never need the redirect, since
     * neither page's identity depends on a specific group existing).
     */
    protected function afterGroupMayHaveShrunk(?TaskGroup $group): void
    {
        $group?->pruneIfTooSmall();
    }

    /** Projects available to assign in the edit sheet. */
    #[Computed]
    public function editableProjects(): Collection
    {
        return auth()->user()->projects()->ordered()->get();
    }

    /**
     * Task groups available in the edit sheet. This is the touch equivalent of
     * desktop's drag-onto-a-group-box: on a phone there is no drag, so the edit
     * sheet is where a task gets bundled or released.
     */
    #[Computed]
    public function editableGroups(): Collection
    {
        return auth()->user()->taskGroups()->ordered()->get();
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

        $task->syncLinkedAgendaEntry($user, $done);

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
        $this->editList = $task->list;
        $this->editProjectId = $task->project_id;
        $this->editGroupId = $task->group_id;
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
            'editProjectId' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', auth()->id())],
            'editGroupId' => ['nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', auth()->id())],
        ]);

        $task = $this->userTask($this->editingId);

        // Captured before the update — if the task leaves this group, it's
        // pruned (dissolved if only one task would be left) once the new
        // state is saved. Reading it now, not after, since group_id may be
        // overwritten below.
        $oldGroup = $task->group;

        $notes = trim((string) $data['editNotes']);

        $updates = [
            'title' => $data['editTitle'],
            'deadline' => $data['editDeadline'] ?: null,
            'due_date' => $data['editDueDate'] ?: null,
            'duration_minutes' => $data['editDuration'] ?: null,
            'notes' => $notes !== '' ? $notes : null,
        ];

        // editProjectId takes priority: if a project is selected the task is
        // always placed in the 'projects' list regardless of editList.
        $newProjectId = $data['editProjectId'] ? (int) $data['editProjectId'] : null;
        $newList = $data['editList'];

        // A task belongs to a project or to a group — never to both. Anything
        // that lands in the Projekte list therefore leaves its group behind.
        $newGroupId = $data['editGroupId'] ? (int) $data['editGroupId'] : null;

        if ($newProjectId !== null) {
            // Assigned to a specific project
            $updates['project_id'] = $newProjectId;
            $updates['group_id'] = null;
            $updates['list'] = 'projects';
            $updates['is_today'] = false;
            $updates['today_date'] = null;
        } elseif ($newList === 'projects') {
            // Standalone project task (no specific project)
            $updates['project_id'] = null;
            $updates['group_id'] = null;
            $updates['list'] = 'projects';
            $updates['is_today'] = false;
            $updates['today_date'] = null;
        } else {
            // Regular board list — clear any project assignment
            $updates['project_id'] = null;
            $updates['group_id'] = $newGroupId;
            $updates['list'] = $newList;
            if (! in_array($newList, Task::TODAY_LISTS, true)) {
                $updates['is_today'] = false;
                $updates['today_date'] = null;
            }
        }

        $task->update($updates);

        if ($oldGroup !== null && $oldGroup->id !== $newGroupId) {
            $this->afterGroupMayHaveShrunk($oldGroup);
        }

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
        $this->reset(['editingId', 'editTitle', 'editDeadline', 'editDueDate', 'editDuration', 'editNotes', 'editList', 'editProjectId', 'editGroupId']);
    }

    public function deleteTask(int $id): void
    {
        $task = $this->userTask($id);
        $group = $task->group;

        $task->delete();
        $this->afterGroupMayHaveShrunk($group);

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }
}
