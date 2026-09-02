<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesFamilySpaces;
use App\Livewire\Support\FamilyColors;
use App\Models\FamilySpace;
use App\Models\FamilyTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Familie page (/app/family) — a shared household task board. See
 * CLAUDE.md, "Familie — geteilte Aufgaben", for the full interaction model
 * (tap an unclaimed card to claim it, tap a claimed card to complete it, tap
 * a done card to reopen it; the edit sheet's assignee picker is the
 * deliberate "assign to someone else" path).
 */
#[Layout('layouts.app')]
class FamilyList extends Component
{
    use ManagesFamilySpaces;

    public ?int $activeSpaceId = null;

    public ?int $editingTaskId = null;

    public string $editTitle = '';

    public string $editNotes = '';

    public string $newTaskTitle = '';

    public function mount(): void
    {
        // Land on the first family this account belongs to, if any — most
        // households only ever have one, so there is usually nothing to pick.
        $this->activeSpaceId = auth()->user()->familySpaces()->ordered()->value('family_spaces.id');
    }

    /** The space currently on screen, resolved fresh through membership every time — never trusted bare. */
    #[Computed]
    public function currentSpace(): ?FamilySpace
    {
        if ($this->activeSpaceId === null) {
            return null;
        }

        return auth()->user()->familySpaces()->find($this->activeSpaceId);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function members(): Collection
    {
        return $this->currentSpace?->members()->orderBy('name')->get() ?? collect();
    }

    /** This account's own card color in the current space. */
    #[Computed]
    public function myColor(): ?string
    {
        return $this->currentSpace?->colorFor(auth()->user());
    }

    /** @return Collection<int, FamilyTask> */
    #[Computed]
    public function openTasks(): Collection
    {
        if ($this->currentSpace === null) {
            return collect();
        }

        return FamilyTask::query()
            ->forSpace($this->currentSpace->id)
            ->open()
            ->with(['assignee', 'creator'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    /** @return Collection<int, FamilyTask> */
    #[Computed]
    public function doneTasks(): Collection
    {
        if ($this->currentSpace === null) {
            return collect();
        }

        return FamilyTask::query()
            ->forSpace($this->currentSpace->id)
            ->done()
            ->with(['assignee', 'completer'])
            ->orderByDesc('completed_at')
            ->limit(30)
            ->get();
    }

    /**
     * A task, resolved through its OWN space's membership — deliberately not
     * through $activeSpaceId, so a stale/switched-away id in the frontend can
     * never act on a task outside the space the user actually belongs to.
     * Mirrors TaskBoard::userTask()'s "never trust the frontend" rule.
     *
     * Scoped via the query itself (whereHas), not "fetch then abort() if not
     * a member" — a plain abort()'s HttpException does not propagate out of a
     * Livewire action the same way ModelNotFoundException does (see
     * CLAUDE.md's note on ManagesAgendaSpaces::removeMember()), so every id
     * lookup in this app that needs a 404-on-mismatch goes through
     * findOrFail() against an already-scoped query instead.
     */
    private function memberTask(int $id): FamilyTask
    {
        return FamilyTask::query()
            ->whereHas('familySpace', fn ($q) => $q->forMember(auth()->user()))
            ->with('familySpace')
            ->findOrFail($id);
    }

    public function switchSpace(int $id): void
    {
        $this->activeSpaceId = $this->memberFamilySpace($id)->id;

        unset($this->currentSpace, $this->members, $this->myColor, $this->openTasks, $this->doneTasks);
    }

    public function addTask(): void
    {
        $space = $this->currentSpace;
        $title = trim($this->newTaskTitle);

        if ($space === null || $title === '') {
            return;
        }

        FamilyTask::create([
            'family_space_id' => $space->id,
            'created_by' => auth()->id(),
            'title' => $title,
        ]);

        $this->newTaskTitle = '';
        unset($this->openTasks);
    }

    /** Tap an unclaimed card — Signature Moment A. No-op if it's already claimed by anyone. */
    public function claimTask(int $id): void
    {
        $this->memberTask($id)->claim(auth()->user());

        unset($this->openTasks);
    }

    /** Tap a claimed card — marks it done, credited to whoever tapped. No-op if unclaimed or already done. */
    public function completeTask(int $id): void
    {
        $this->memberTask($id)->completeBy(auth()->user());

        unset($this->openTasks, $this->doneTasks);
    }

    /** Tap a done card — reopens it, keeping the same assignee. */
    public function reopenTask(int $id): void
    {
        $this->memberTask($id)->reopen();

        unset($this->openTasks, $this->doneTasks);
    }

    /**
     * Re-checks membership on every read, not just at startEditTask() time —
     * $editingTaskId is a plain public property, so it must never be trusted
     * as-is: a tampered value pointing at another family's task must render
     * nothing here rather than leak that task's title/notes into this page.
     */
    #[Computed]
    public function editingTask(): ?FamilyTask
    {
        if ($this->editingTaskId === null) {
            return null;
        }

        $task = FamilyTask::with(['assignee', 'familySpace'])->find($this->editingTaskId);

        if ($task === null || ! $task->familySpace->hasMember(auth()->user())) {
            return null;
        }

        return $task;
    }

    public function startEditTask(int $id): void
    {
        $task = $this->memberTask($id);

        $this->editingTaskId = $task->id;
        $this->editTitle = $task->title;
        $this->editNotes = (string) $task->notes;
    }

    public function closeEditTask(): void
    {
        $this->editingTaskId = null;
        unset($this->editingTask);
    }

    public function saveTaskEdit(): void
    {
        if ($this->editingTaskId === null) {
            return;
        }

        $title = trim($this->editTitle);

        $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
        ], attributes: ['editTitle' => 'Titel']);

        $this->memberTask($this->editingTaskId)->update([
            'title' => $title,
            'notes' => trim($this->editNotes) !== '' ? $this->editNotes : null,
        ]);

        $this->editingTaskId = null;
        unset($this->openTasks, $this->doneTasks, $this->editingTask);
    }

    /**
     * The deliberate "assign to someone specific" path — the edit sheet's
     * assignee chips. $userId null unclaims it. Works regardless of
     * completion state (a simple, predictable field write, not gated on
     * whether the task happens to be done right now).
     */
    public function assignTask(int $taskId, ?int $userId): void
    {
        $task = $this->memberTask($taskId);

        if ($userId !== null && ! $task->familySpace->members()->whereKey($userId)->exists()) {
            return;
        }

        $task->update(['assigned_to' => $userId]);

        unset($this->openTasks, $this->doneTasks, $this->editingTask);
    }

    public function deleteTask(int $id): void
    {
        $this->memberTask($id)->delete();

        if ($this->editingTaskId === $id) {
            $this->editingTaskId = null;
            unset($this->editingTask);
        }

        unset($this->openTasks, $this->doneTasks);
    }

    protected function afterFamilySpaceGone(int $spaceId): void
    {
        if ($this->activeSpaceId === $spaceId) {
            $this->activeSpaceId = auth()->user()->familySpaces()->ordered()->value('family_spaces.id');
        }
    }

    /**
     * Creating or joining a family should land you on its board right away —
     * without this, the page would keep showing the "no family yet" empty
     * state (or whichever other family was already active) until an extra
     * "Hier anzeigen" click, the first time this matters most.
     */
    protected function afterFamilySpaceJoinedOrCreated(FamilySpace $space): void
    {
        $this->activeSpaceId = $space->id;

        unset($this->currentSpace, $this->members, $this->myColor, $this->openTasks, $this->doneTasks);
    }

    public function render()
    {
        return view('livewire.family-list', [
            'colorKeys' => FamilyColors::KEYS,
        ]);
    }
}
