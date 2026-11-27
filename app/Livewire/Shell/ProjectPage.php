<?php

namespace App\Livewire\Shell;

use App\Livewire\Concerns\ManagesMembership;
use App\Livewire\Concerns\ManagesNotes;
use App\Livewire\Concerns\ManagesStepMoves;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\Projects\Bundle;
use App\Services\Projects\NextSteps;
use App\Services\Projects\ProjectEditor;
use App\Support\TaskMutator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Step 4.3: one project. Roadmap of stages on top, then each stage with its steps (the next two
 * marked, the waiting ones quiet), then the steps in no stage. Stages are the coarse
 * dependencies (stage 2 waits on stage 1); "wartet auf" covers the rare single step.
 * Step 4.3b: note cards with #tags in a column beside the stages (ManagesNotes).
 * Step 4.3c: steps move by dragging, keyboard or the ⋯ menu, between stages too (ManagesStepMoves).
 * Step 4.3d: stages change their order, board tasks move in and out, the project can be deleted
 * (ManagesMembership).
 */
#[Layout('layouts.shell')]
class ProjectPage extends Component
{
    use ManagesMembership;
    use ManagesNotes;
    use ManagesStepMoves;

    #[Locked]
    public int $projectId;

    public ?string $notice = null;

    /** What the notice's "Rückgängig" restores. */
    #[Locked]
    public ?array $undo = null;

    public bool $editingName = false;

    public string $name = '';

    public bool $addingStage = false;

    public string $stageName = '';

    public string $stageDate = '';

    #[Locked]
    public ?int $editingStage = null;

    public string $editStageName = '';

    public string $editStageDate = '';

    /** The "+ Schritt" field per stage ("stage-{id}") and for steps in no stage ("none"). */
    public array $newStep = [];

    public string $newStepList = 'tasks';

    public function mount(int $project): void
    {
        $this->projectId = Project::forUser(Auth::user())->findOrFail($project)->id;
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function project(): Project
    {
        return Project::forUser($this->user)->with('stages')->findOrFail($this->projectId);
    }

    /** @return Collection<int, Task> open steps in project order */
    #[Computed]
    public function steps(): Collection
    {
        return Task::forUser($this->user)->active()->where('project_id', $this->projectId)
            ->with(['area', 'waitsOn'])->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function bundle(): Bundle
    {
        return app(NextSteps::class)->bundles($this->user)->first(fn (Bundle $b) => $b->isProject() && $b->id === $this->projectId);
    }

    /** @return Collection<int, int> done steps per stage id */
    #[Computed]
    public function doneByStage(): Collection
    {
        return Task::forUser($this->user)->where('project_id', $this->projectId)->where('is_completed', true)->whereNotNull('stage_id')
            ->selectRaw('stage_id, count(*) as n')->groupBy('stage_id')->pluck('n', 'stage_id');
    }

    #[Computed]
    public function locked(): bool
    {
        return app(FocusSession::class)->current($this->user, CarbonImmutable::now())->phase === Phase::Work;
    }

    // ---- name --------------------------------------------------------------------------------

    public function editName(): void
    {
        $this->editingName = true;
        $this->name = $this->project->name;
        $this->resetValidation();
    }

    public function saveName(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:'.Project::NAME_MAX]], $this->messages('name'));
        app(ProjectEditor::class)->rename($this->project, trim($this->name));
        $this->editingName = false;
        $this->fresh();
    }

    // ---- stages ------------------------------------------------------------------------------

    public function openStage(): void
    {
        $this->addingStage = true;
        $this->stageName = '';
        $this->stageDate = '';
        $this->resetValidation();
    }

    public function addStage(): void
    {
        $this->validate([
            'stageName' => ['required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'stageDate' => ['nullable', 'date'],
        ], [...$this->messages('stageName'), 'stageDate.date' => __('projects.errors.date')]);

        $stage = app(ProjectEditor::class)->addStage($this->project, trim($this->stageName), $this->stageDate ?: null);
        $this->addingStage = false;
        $this->say(__('projects.stage_added', ['name' => $stage->name]));
    }

    public function editStage(int $id): void
    {
        $stage = $this->stage($id);
        $this->editingStage = $stage->id;
        $this->editStageName = $stage->name;
        $this->editStageDate = $stage->target_date?->toDateString() ?? '';
        $this->resetValidation();
    }

    public function saveStage(): void
    {
        $this->validate([
            'editStageName' => ['required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'editStageDate' => ['nullable', 'date'],
        ], [...$this->messages('editStageName'), 'editStageDate.date' => __('projects.errors.date')]);

        $this->guard(function () {
            app(ProjectEditor::class)->updateStage($this->stage($this->editingStage), trim($this->editStageName), $this->editStageDate ?: null);
            $this->editingStage = null;
        });
    }

    public function cancelStage(): void
    {
        $this->editingStage = null;
        $this->addingStage = false;
        $this->resetValidation();
    }

    /** The stage goes, its steps stay without a stage; undoable (CONVENTIONS 7). */
    public function removeStage(int $id): void
    {
        $this->guard(function () use ($id) {
            $stage = $this->stage($id);
            $this->undo = [
                'kind' => 'stage',
                'name' => $stage->name,
                'date' => $stage->target_date?->toDateString(),
                'order' => $stage->sort_order,
                'steps' => Task::where('stage_id', $stage->id)->pluck('id')->all(),
            ];
            app(ProjectEditor::class)->removeStage($stage);
            $this->editingStage = null;
            $this->notice = __('projects.stage_removed', ['name' => $stage->name]);
        });
    }

    /** One place earlier (-1) or later (+1) in the roadmap; undoable. A step's "wartet auf" that now contradicts is let go. */
    public function shiftStage(int $id, int $by): void
    {
        $this->guard(function () use ($id, $by) {
            $stage = $this->stage($id);
            $at = $this->project->stages->search(fn (ProjectStage $s) => $s->id === $stage->id);

            $this->placeStage($stage, (int) $at + ($by < 0 ? -1 : 1));
        });
    }

    /**
     * A stage dragged to a new place (board-followups): the page sends every stage ref ("stage:ID") in the
     * order it now shows. Exactly one stage moved, so the one whose removal leaves both orders equal is placed
     * through the same path as the buttons. A list that does not match the project's stages (one was added or
     * removed meanwhile) changes nothing: the page is redrawn with what the server has.
     *
     * @param  list<string>  $refs
     */
    public function reorderStages(array $refs): void
    {
        $this->guard(function () use ($refs) {
            $new = collect($refs)->map(fn ($ref) => is_string($ref) && preg_match('/^stage:(\d+)$/', $ref, $m) === 1 ? (int) $m[1] : null)->all();
            $old = $this->project->stages->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (in_array(null, $new, true) || count($new) !== count($old) || array_diff($old, $new) !== [] || $new === $old) {
                return;
            }

            foreach ($new as $at => $id) {
                if (array_values(array_diff($old, [$id])) === array_values(array_diff($new, [$id]))) {
                    $this->placeStage($this->stage($id), $at);

                    return;
                }
            }
        });
    }

    /** One stage to a place; undoable. A step's "wartet auf" that now contradicts is let go. */
    private function placeStage(ProjectStage $stage, int $index): void
    {
        $move = app(ProjectEditor::class)->moveStage($stage, $index);

        if (! $move->changed) {
            return;
        }

        $this->undo = ['kind' => 'stages', 'order' => $move->order, 'cleared' => $move->cleared];
        $text = __('projects.stage_move.moved', ['name' => $stage->name, 'number' => $move->toIndex + 1]);
        $this->notice = $move->cleared === [] ? $text : $text.' '.__('projects.move.waits_cleared');
    }

    // ---- steps -------------------------------------------------------------------------------

    public function addStep(string $key): void
    {
        $field = 'newStep.'.$key;
        $this->validate([$field => ['required', 'string', 'max:255']], [$field.'.required' => __('projects.errors.title_required')]);

        $this->guard(function () use ($key) {
            $stage = $key === 'none' ? null : $this->stage((int) Str::after($key, 'stage-'));
            $list = in_array($this->newStepList, Task::WORK_LISTS, true) ? $this->newStepList : 'tasks';
            app(ProjectEditor::class)->addProjectStep($this->user, $this->project, $stage, trim($this->newStep[$key]), $list);
            $this->newStep[$key] = '';
        });
    }

    public function setWaitsOn(int $id, string $onId): void
    {
        $this->guard(function () use ($id, $onId) {
            try {
                app(ProjectEditor::class)->setWaitsOn($this->step($id), $onId === '' ? null : $this->step((int) $onId));
            } catch (InvalidArgumentException) {
                $this->notice = __('projects.errors.loop');
            }
        });
    }

    public function start(int $id): void
    {
        $this->notice = null;
        if ($this->locked) {
            $this->notice = __('projects.not_now');

            return;
        }

        try {
            app(FocusSession::class)->start($this->user, $this->step($id)->id, CarbonImmutable::now());
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->redirectRoute('shell.board', navigate: true);
    }

    /** "Erledigt", undoable (CONVENTIONS 7). */
    public function done(int $id): void
    {
        $this->guard(function () use ($id) {
            $task = TaskMutator::applyUpdate($this->step($id), $this->user, ['is_completed' => true]);
            $this->undo = ['kind' => 'done', 'id' => $task->id];
            $this->notice = __('projects.done_notice', ['title' => Str::limit($task->title, 60)]);
        });
    }

    public function undoLast(): void
    {
        $undo = $this->undo;
        $this->undo = null;
        $this->notice = null;

        if (($undo['kind'] ?? null) === 'done') {
            $task = Task::forUser($this->user)->where('project_id', $this->projectId)->whereKey($undo['id'])->first();
            if ($task === null) {
                $this->gone();

                return;
            }
            TaskMutator::applyUpdate($task, $this->user, ['is_completed' => false]);
        } elseif (($undo['kind'] ?? null) === 'move') {
            $this->undoMove($undo);

            return;
        } elseif (in_array($undo['kind'] ?? null, ['adopt', 'release'], true)) {
            $this->undoMembership($undo);

            return;
        } elseif (($undo['kind'] ?? null) === 'stages') {
            app(ProjectEditor::class)->restoreStageOrder($this->project, (array) $undo['order'], (array) $undo['cleared']);
            $this->say(__('projects.move.undone'));

            return;
        } elseif (($undo['kind'] ?? null) === 'note') {
            $this->undoNote($undo);
        } elseif (($undo['kind'] ?? null) === 'stage') {
            $stage = $this->project->stages()->create(['name' => $undo['name'], 'target_date' => $undo['date'], 'sort_order' => $undo['order']]);
            Task::forUser($this->user)->where('project_id', $this->projectId)->whereIn('id', $undo['steps'])->whereNull('stage_id')->update(['stage_id' => $stage->id]);
        }

        $this->say(__('projects.undone'));
    }

    public function dismiss(): void
    {
        $this->notice = null;
        $this->undo = null;
    }

    // ---- helpers -----------------------------------------------------------------------------

    protected function noteOwner(): Project
    {
        return $this->project;
    }

    private function stage(?int $id): ProjectStage
    {
        return ProjectStage::where('project_id', $this->projectId)->whereHas('project', fn ($q) => $q->where('user_id', $this->user->id))->findOrFail($id);
    }

    /** "none" (no stage) or "stage-ID", the keys of the page's lanes. */
    protected function stageForKey(string $key): ?ProjectStage
    {
        if ($key === 'none') {
            return null;
        }

        return preg_match('/^stage-(\d+)$/', $key, $m) === 1 ? $this->stage((int) $m[1]) : throw new ModelNotFoundException;
    }

    private function step(int $id): Task
    {
        return Task::forUser($this->user)->active()->where('project_id', $this->projectId)->findOrFail($id);
    }

    private function messages(string $field): array
    {
        return [
            $field.'.required' => __('projects.errors.name_required'),
            $field.'.max' => __('projects.errors.name_max', ['max' => Project::NAME_MAX]),
        ];
    }

    private function guard(callable $action): void
    {
        try {
            $action();
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->fresh();
    }

    private function say(string $notice): void
    {
        $this->notice = $notice;
        $this->fresh();
    }

    private function gone(): void
    {
        $this->undo = null;
        $this->notice = __('projects.gone');
        $this->fresh();
    }

    private function fresh(): void
    {
        foreach (['project', 'steps', 'bundle', 'doneByStage', 'locked', 'impact'] as $computed) {
            unset($this->{$computed});
        }
    }

    public function render(): View
    {
        return view('livewire.shell.project-page')->title($this->project->name.' · nothing-to-do');
    }
}
