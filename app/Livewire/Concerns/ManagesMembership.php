<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Services\Projects\ProjectEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Step 4.3d: who belongs to a project or group, on its page. Three things:
 *  - the picker: an existing board task becomes a step (adoptTask), with "Rückgängig";
 *  - "aus dem Projekt lösen" (releaseStep): the step is a plain board task again, with "Rückgängig";
 *  - deleting the project or group behind a calm in-app confirm (askDelete → deleteOwner).
 *
 * The page provides noteOwner() (the Project or TaskGroup), step(), stageForKey(), guard(), gone(), say(),
 * a computed `user`, a public ?string $notice and a public ?array $undo. Its undoLast() hands the kinds
 * "adopt" and "release" to undoMembership().
 */
trait ManagesMembership
{
    /** How many tasks the picker lists; the rest is reached by searching. */
    public const PICK_LIMIT = 30;

    public bool $picking = false;

    public string $pickSearch = '';

    /** The stage the picked task lands in: "none" or "stage-ID" (projects only). */
    public string $pickStage = 'none';

    public bool $confirmingDelete = false;

    /** One plain line when the delete failed; the dialog stays open so nothing seems to have happened. */
    public ?string $deleteError = null;

    // ---- picker ------------------------------------------------------------------------------

    public function openPicker(): void
    {
        $this->picking = true;
        $this->pickSearch = '';
        $this->pickStage = 'none';
    }

    public function closePicker(): void
    {
        $this->picking = false;
        $this->pickSearch = '';
    }

    /** @return Collection<int, Task> the first PICK_LIMIT matches */
    #[Computed]
    public function pickable(): Collection
    {
        return $this->pickQuery($this->pickSearch)->limit(self::PICK_LIMIT)->get();
    }

    #[Computed]
    public function pickableCount(): int
    {
        return $this->pickQuery($this->pickSearch)->count();
    }

    /** Is there any task at all to pull in (empty state vs. "nothing found")? */
    #[Computed]
    public function hasPickable(): bool
    {
        return $this->pickQuery('')->exists();
    }

    /** @return Builder<Task> */
    private function pickQuery(string $search): Builder
    {
        $owner = $this->noteOwner();

        return app(ProjectEditor::class)->attachable($this->user, $owner instanceof TaskGroup ? ($owner->list ?? 'tasks') : null, mb_substr($search, 0, 100));
    }

    public function adoptTask(int $id): void
    {
        $this->guard(function () use ($id) {
            $owner = $this->noteOwner();
            $editor = app(ProjectEditor::class);
            $task = $this->pickQuery('')->findOrFail($id);

            try {
                $stage = $owner instanceof Project ? $this->stageForKey($this->pickStage) : null;
                $editor->adopt($this->user, $owner, $task, $stage);
            } catch (InvalidArgumentException) {
                throw (new ModelNotFoundException)->setModel(Task::class, [$id]);
            }

            $this->undo = ['kind' => 'adopt', 'id' => $task->id];
            $this->notice = __($owner instanceof Project ? 'projects.adopt.added_project' : 'projects.adopt.added_group', ['title' => Str::limit($task->title, 60)]);
        });
    }

    // ---- release -----------------------------------------------------------------------------

    public function releaseStep(int $id): void
    {
        $this->guard(function () use ($id) {
            $step = $this->step($id);
            $was = app(ProjectEditor::class)->release($step);

            $this->undo = ['kind' => 'release', 'id' => $step->id, 'was' => $was];
            $this->notice = __('projects.release.done', ['title' => Str::limit($step->title, 60), 'list' => __('projects.lists.'.$step->list)]);
        });
    }

    /** Called by the page's undoLast() for the kinds "adopt" and "release". */
    protected function undoMembership(array $undo): void
    {
        try {
            if ($undo['kind'] === 'adopt') {
                app(ProjectEditor::class)->release($this->step((int) $undo['id']));
            } else {
                $this->reattachTask((int) $undo['id'], (array) $undo['was']);
            }
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->say(__('projects.move.undone'));
    }

    /** @param  array{project: ?int, group: ?int, stage: ?int, waits_on: ?int, waiting: array<int, int>}  $was */
    private function reattachTask(int $id, array $was): void
    {
        $owner = $this->noteOwner();
        $mine = $owner instanceof Project ? $was['project'] === $owner->id : $was['project'] === null && $was['group'] === $owner->id;
        $task = Task::forUser($this->user)->active()->whereNull('project_id')->whereNull('group_id')->find($id);

        // Nothing moved it elsewhere in between, and a group still gets only its own list.
        if (! $mine || $task === null || ($owner instanceof TaskGroup && $task->list !== $owner->list)) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$id]);
        }

        app(ProjectEditor::class)->reattach($task, $was);
    }

    // ---- delete ------------------------------------------------------------------------------

    public function askDelete(): void
    {
        $this->confirmingDelete = true;
        $this->deleteError = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
        $this->deleteError = null;
    }

    /** @return array{open: int, done: int, stages: int, notes: int} what the confirm text names */
    #[Computed]
    public function impact(): array
    {
        return app(ProjectEditor::class)->impact($this->noteOwner());
    }

    public function deleteOwner(): void
    {
        try {
            $owner = $this->noteOwner();
            $editor = app(ProjectEditor::class);
            $impact = $editor->impact($owner);
            $name = $owner->name;

            $owner instanceof Project ? $editor->deleteProject($owner) : $editor->deleteGroup($owner);
        } catch (ModelNotFoundException) {
            // Deleted in another tab meanwhile: nothing left to delete.
            session()->flash('projects.notice', __('projects.gone'));
            $this->redirectRoute('shell.projects', navigate: true);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->deleteError = __('projects.delete.failed');

            return;
        }

        session()->flash('projects.notice', trans_choice($owner instanceof Project ? 'projects.delete.done_project' : 'projects.delete.done_group', $impact['open'], ['name' => Str::limit($name, 60), 'count' => $impact['open']]));
        $this->redirectRoute('shell.projects', navigate: true);
    }
}
