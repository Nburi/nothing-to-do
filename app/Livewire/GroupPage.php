<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\GroupNote;
use App\Models\Task;
use App\Models\TaskGroup;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A task group's own dashboard: the same three lists as the main board
 * (Inbox / To-Dos / Tasks) plus a Notizen column of separate Markdown note
 * cards where the board has its Projekte column. Kanban on desktop,
 * bottom-navigation on mobile — deliberately the main board's shape, so
 * nothing new has to be learned to use one.
 *
 * There is no "Heute" area here: the day's focus belongs to the main board, and
 * two places both claiming to own it would be one too many. A group task can
 * still be flagged for today and then shows up in the board's Heute tab.
 */
#[Layout('layouts.app')]
class GroupPage extends Component
{
    use ManagesTasks;

    public int $groupId;

    /** Active mobile page: inbox | todos | tasks | notes. */
    public string $mobileTab = 'todos';

    /** Per-column quick-add buffers, keyed by list. */
    public array $newTitle = ['inbox' => '', 'todos' => '', 'tasks' => ''];

    public string $groupName = '';

    public bool $renaming = false;

    /**
     * The Notizen column is a stack of separate small note cards rather than
     * one growing document — a group can hold a few unrelated things worth
     * jotting down (a checklist, a quote, a deadline reminder), and one blob
     * made finding any single one of them slower as it grew. Only one card is
     * ever being edited at a time, same as the task edit sheet.
     */
    public ?int $editingNoteId = null;

    public string $noteDraft = '';

    public function mount(TaskGroup $group): void
    {
        // Route binding loaded it; enforce ownership before trusting it.
        abort_unless($group->user_id === auth()->id(), 404);

        $this->groupId = $group->id;
        $this->groupName = $group->name;
    }

    /** Always re-resolve through the owner relationship — never trust the id alone. */
    #[Computed]
    public function group(): TaskGroup
    {
        return auth()->user()->taskGroups()->findOrFail($this->groupId);
    }

    /**
     * The group's tasks: everything active, plus whatever was completed inside
     * the current visibility window — same rule as the board's columns, so a
     * task ticked off five minutes ago doesn't vanish mid-session.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        $windowStart = auth()->user()->completedWindowStart();

        return auth()->user()->tasks()
            ->inGroup($this->groupId)
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')
            ->groupOrdered()
            ->get();
    }

    /** Active tasks of one list, in group order. */
    public function activeIn(string $list): Collection
    {
        return $this->tasks->where('is_completed', false)->where('list', $list)->values();
    }

    /** Recently completed tasks of one list. */
    public function completedIn(string $list): Collection
    {
        return $this->tasks->where('is_completed', true)->where('list', $list)->values();
    }

    /** All-time completed count — drives the progress bar. */
    #[Computed]
    public function doneCount(): int
    {
        return auth()->user()->tasks()
            ->inGroup($this->groupId)
            ->where('is_completed', true)
            ->count();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->tasks->where('is_completed', false)->count() + $this->doneCount;
    }

    /** Per-list active counts for the mobile tab badges. */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox' => $this->activeIn('inbox')->count(),
            'todos' => $this->activeIn('todos')->count(),
            'tasks' => $this->activeIn('tasks')->count(),
        ];
    }

    /** Loose board tasks that can be pulled into this group. */
    #[Computed]
    public function boardInboxTasks(): Collection
    {
        return auth()->user()->tasks()
            ->active()
            ->onBoard()
            ->ungrouped()
            ->inList('inbox')
            ->boardOrdered()
            ->get();
    }

    /** The group's note cards, in display order. */
    #[Computed]
    public function notes(): Collection
    {
        return $this->group->notes;
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'notes'], true)) {
            return;
        }

        $this->mobileTab = $tab;
    }

    /** Quick-add straight into one of the group's three lists. */
    public function addTask(string $list): void
    {
        if (! in_array($list, Task::BOARD_LISTS, true)) {
            return;
        }

        $this->newTitle[$list] = trim((string) ($this->newTitle[$list] ?? ''));

        $this->validate(
            ['newTitle.'.$list => ['required', 'string', 'max:255']],
            [
                'newTitle.'.$list.'.required' => 'Ohne Titel geht es nicht.',
                'newTitle.'.$list.'.max' => 'Höchstens 255 Zeichen.',
            ],
        );

        auth()->user()->tasks()->create([
            'title' => $this->newTitle[$list],
            'list' => $list,
            'group_id' => $this->groupId,
            'sort_order' => 0,
        ]);

        $this->newTitle[$list] = '';
    }

    /**
     * Drag & drop inside the group. Only ever touches tasks that are already in
     * this group — an id from anywhere else is silently skipped, exactly like
     * the board's own reorder().
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(string $list, bool $today, array $ids): void
    {
        if (! in_array($list, Task::BOARD_LISTS, true)) {
            return;
        }

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->inGroup($this->groupId)->find((int) $id);

            if ($task === null) {
                continue;
            }

            $task->update([
                'list' => $list,
                'sort_order' => $position,
                // Moving into the Inbox drops the today flag, same as the board.
                'is_today' => $list === 'inbox' ? false : $task->is_today,
            ]);
        }
    }

    /** Mobile swipe outcomes — the same intents as the board's cards. */
    public function swipeIntent(int $id, string $intent): void
    {
        $task = auth()->user()->tasks()->inGroup($this->groupId)->findOrFail($id);

        match ($intent) {
            'todos' => $task->update(['list' => 'todos']),
            'tasks' => $task->update(['list' => 'tasks']),
            'today' => $task->isInbox() ? null : $task->update(['is_today' => true]),
            'untoday' => $task->isInbox() ? null : $task->update(['is_today' => false]),
            default => null,
        };
    }

    /** Pull a loose board task into this group — it keeps its list. */
    public function assignToGroup(int $taskId): void
    {
        $task = $this->userTask($taskId);

        if ($task->isInProject()) {
            return; // a task belongs to a project or a group, never to both
        }

        $task->update(['group_id' => $this->groupId]);
    }

    /** Release one task from the group. It stays exactly where it is on the board. */
    public function removeFromGroup(int $taskId): void
    {
        $task = $this->userTask($taskId);

        if ($task->group_id !== $this->groupId) {
            return;
        }

        $task->update(['group_id' => null]);

        if ($this->editingId === $taskId) {
            $this->cancelEdit();
        }
    }

    /** Hand a task over to a different group (mobile long-press sheet). */
    public function moveTaskToGroup(int $taskId, int $groupId): void
    {
        $task = auth()->user()->tasks()->inGroup($this->groupId)->findOrFail($taskId);
        $target = auth()->user()->taskGroups()->findOrFail($groupId);

        $task->update(['group_id' => $target->id]);
    }

    public function saveRename(): void
    {
        $this->groupName = trim($this->groupName);

        $data = $this->validate([
            'groupName' => ['required', 'string', 'max:255'],
        ], ['groupName.required' => 'Die Gruppe braucht einen Namen.']);

        $this->group->update(['name' => $data['groupName']]);
        $this->renaming = false;
    }

    public function cancelRename(): void
    {
        $this->groupName = $this->group->name;
        $this->renaming = false;
    }

    /** Always re-resolve through the group — never trust a note id alone. */
    protected function groupNote(int $id): GroupNote
    {
        return $this->group->notes()->findOrFail($id);
    }

    /** Adds a fresh, empty card and opens it for editing straight away. */
    public function addNote(): void
    {
        $nextOrder = (int) ($this->group->notes()->max('sort_order') ?? -1) + 1;

        $note = $this->group->notes()->create([
            'content' => '',
            'sort_order' => $nextOrder,
        ]);

        $this->editingNoteId = $note->id;
        $this->noteDraft = '';
        $this->dispatch('group-notes-focus');
    }

    /** Only one card is ever being edited at a time — switching saves/discards whatever was open. */
    public function editNote(int $id): void
    {
        if ($this->editingNoteId !== null && $this->editingNoteId !== $id) {
            $this->stopEditingNote();
        }

        $note = $this->groupNote($id);
        $this->editingNoteId = $note->id;
        $this->noteDraft = (string) $note->content;
        $this->dispatch('group-notes-focus');
    }

    /** Autosave — fires on every Livewire model sync of the draft buffer. */
    public function updatedNoteDraft(): void
    {
        $this->saveDraft();
        $this->dispatch('group-notes-saved');
    }

    protected function saveDraft(): void
    {
        if ($this->editingNoteId === null) {
            return;
        }

        $data = $this->validate([
            'noteDraft' => ['nullable', 'string', 'max:50000'],
        ]);

        $this->groupNote($this->editingNoteId)->update([
            'content' => trim((string) ($data['noteDraft'] ?? '')),
        ]);
    }

    /**
     * "Fertig" — saves and closes the editor. A card left completely empty is
     * removed rather than kept as a blank tile, so cancelling out of "+ Notiz"
     * (or clearing a card's text entirely) never leaves a shell behind.
     */
    public function stopEditingNote(): void
    {
        if ($this->editingNoteId === null) {
            return;
        }

        $this->saveDraft();
        $note = $this->groupNote($this->editingNoteId);

        if (trim((string) $note->content) === '') {
            $note->delete();
        }

        $this->editingNoteId = null;
        $this->noteDraft = '';
    }

    public function deleteNote(int $id): void
    {
        $this->groupNote($id)->delete();

        if ($this->editingNoteId === $id) {
            $this->editingNoteId = null;
            $this->noteDraft = '';
        }
    }

    /**
     * Dissolve the group. Deliberately non-destructive: `group_id` is nulled by
     * the FK's nullOnDelete, so every task — done or open — stays exactly where
     * it already was and simply becomes loose on the board again.
     */
    public function dissolveGroup(): void
    {
        $this->group->delete();

        $this->redirectRoute('app', navigate: true);
    }

    /** Set/clear the Today focus. The Inbox has no today, same as on the board. */
    public function setToday(int $id, bool $value): void
    {
        $task = auth()->user()->tasks()->inGroup($this->groupId)->findOrFail($id);

        if ($task->isInbox()) {
            return;
        }

        $task->update(['is_today' => $value]);
    }

    /** Groups a task can be moved to from the mobile picker sheet. */
    #[Computed]
    public function pickableGroups(): Collection
    {
        return auth()->user()->taskGroups()->ordered()->get();
    }

    public function render()
    {
        return view('livewire.group-page');
    }
}
