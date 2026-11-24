<?php

namespace App\Livewire\Shell;

use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\Projects\NextSteps;
use App\Services\Projects\ProjectEditor;
use App\Support\TaskMutator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Step 4.3: one group. A few tasks of one list in the group's order; the first two are what the
 * board shows. New steps land in the group's list.
 */
#[Layout('layouts.shell')]
class GroupPage extends Component
{
    #[Locked]
    public int $groupId;

    public ?string $notice = null;

    #[Locked]
    public ?int $undoId = null;

    public bool $editingName = false;

    public string $name = '';

    public string $newStep = '';

    public function mount(int $group): void
    {
        $this->groupId = TaskGroup::forUser(Auth::user())->findOrFail($group)->id;
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function group(): TaskGroup
    {
        return TaskGroup::forUser($this->user)->findOrFail($this->groupId);
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function steps(): Collection
    {
        return $this->group->tasks()->where('is_completed', false)->with('area')->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function doneCount(): int
    {
        return $this->group->tasks()->where('is_completed', true)->count();
    }

    #[Computed]
    public function locked(): bool
    {
        return app(FocusSession::class)->current($this->user, CarbonImmutable::now())->phase === Phase::Work;
    }

    public function editName(): void
    {
        $this->editingName = true;
        $this->name = $this->group->name;
        $this->resetValidation();
    }

    public function saveName(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:'.TaskGroup::NAME_MAX]], [
            'name.required' => __('projects.errors.name_required'),
            'name.max' => __('projects.errors.name_max', ['max' => TaskGroup::NAME_MAX]),
        ]);
        app(ProjectEditor::class)->rename($this->group, trim($this->name));
        $this->editingName = false;
        $this->fresh();
    }

    public function addStep(): void
    {
        $this->validate(['newStep' => ['required', 'string', 'max:255']], ['newStep.required' => __('projects.errors.title_required')]);
        app(ProjectEditor::class)->addGroupStep($this->user, $this->group, trim($this->newStep));
        $this->newStep = '';
        $this->fresh();
    }

    public function move(int $id, int $direction): void
    {
        $this->guard(fn () => app(ProjectEditor::class)->move($this->step($id), $direction));
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

    public function done(int $id): void
    {
        $this->guard(function () use ($id) {
            $task = TaskMutator::applyUpdate($this->step($id), $this->user, ['is_completed' => true]);
            $this->undoId = $task->id;
            $this->notice = __('projects.done_notice', ['title' => Str::limit($task->title, 60)]);
        });
    }

    public function undoLast(): void
    {
        $task = $this->undoId === null ? null : $this->group->tasks()->whereKey($this->undoId)->first();
        $this->undoId = null;
        if ($task === null) {
            $this->gone();

            return;
        }

        TaskMutator::applyUpdate($task, $this->user, ['is_completed' => false]);
        $this->notice = __('projects.undone');
        $this->fresh();
    }

    public function dismiss(): void
    {
        $this->notice = null;
        $this->undoId = null;
    }

    private function step(int $id): Task
    {
        return Task::forUser($this->user)->active()->whereNull('project_id')->where('group_id', $this->groupId)->findOrFail($id);
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

    private function gone(): void
    {
        $this->undoId = null;
        $this->notice = __('projects.gone');
        $this->fresh();
    }

    private function fresh(): void
    {
        foreach (['group', 'steps', 'doneCount', 'locked'] as $computed) {
            unset($this->{$computed});
        }
    }

    public function render(): View
    {
        return view('livewire.shell.group-page', ['visible' => NextSteps::VISIBLE])->title($this->group->name.' · nothing-to-do');
    }
}
