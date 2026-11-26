<?php

namespace App\Livewire\Shell;

use App\Models\Project;
use App\Models\TaskGroup;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\Projects\Bundle;
use App\Services\Projects\NextSteps;
use App\Services\Projects\ProjectEditor;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Step 4.3: the Projekte page. Every project and group as a card with its next two steps (the same
 * NextSteps the board uses), the ones with nothing open as "ruht gerade" chips at the bottom
 * (Niels, round 1: "leere Projekte ruhen"). New projects and groups are created inline.
 */
#[Layout('layouts.shell')]
class Projects extends Component
{
    public ?string $notice = null;

    /** 'project' | 'group' | null: which inline create form is open. */
    public ?string $creating = null;

    public string $newName = '';

    public string $newList = 'tasks';

    /** Step 4.3d: a deleted project or group sends its one plain line here (flashed, so it shows once). */
    public function mount(): void
    {
        $this->notice = session('projects.notice');
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /** @return Collection<int, Bundle> */
    #[Computed]
    public function bundles(): Collection
    {
        return app(NextSteps::class)->bundles($this->user);
    }

    #[Computed]
    public function locked(): bool
    {
        return app(FocusSession::class)->current($this->user, CarbonImmutable::now())->phase === Phase::Work;
    }

    public function open(string $kind): void
    {
        $this->creating = in_array($kind, ['project', 'group'], true) ? $kind : null;
        $this->newName = '';
        $this->newList = 'tasks';
        $this->resetValidation();
    }

    public function create(): void
    {
        $max = $this->creating === 'group' ? TaskGroup::NAME_MAX : Project::NAME_MAX;
        $this->validate([
            'newName' => ['required', 'string', 'max:'.$max],
            'newList' => ['required', 'in:'.implode(',', TaskGroup::LISTS)],
        ], [
            'newName.required' => __('projects.errors.name_required'),
            'newName.max' => __('projects.errors.name_max', ['max' => $max]),
        ]);

        $name = trim($this->newName);
        $editor = app(ProjectEditor::class);

        if ($this->creating === 'group') {
            $group = $editor->createGroup($this->user, $name, $this->newList);
            $this->redirectRoute('shell.group', $group->id, navigate: true);
        } else {
            $project = $editor->createProject($this->user, $name);
            $this->redirectRoute('shell.project', $project->id, navigate: true);
        }
    }

    /** ▶ on a step: start a session with it, like on the board. */
    public function start(int $taskId): void
    {
        $this->notice = null;

        if ($this->locked) {
            $this->notice = __('projects.not_now');

            return;
        }

        try {
            $task = Task::forUser($this->user)->active()->findOrFail($taskId);
            app(FocusSession::class)->start($this->user, $task->id, CarbonImmutable::now());
        } catch (ModelNotFoundException) {
            $this->notice = __('projects.gone');
            unset($this->bundles);

            return;
        }

        $this->redirectRoute('shell.board', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.shell.projects')->title(__('projects.title').' · nothing-to-do');
    }
}
