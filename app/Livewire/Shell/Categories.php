<?php

namespace App\Livewire\Shell;

use App\Models\Area;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Categories of the week plan (step 4.1, REQUIREMENTS § 4), in the shell settings (Niels' pick).
 *
 * Create, rename, colour, the "Pomodoro-Sessions" switch, the areas a category may suggest from
 * (with "+ Bereich" right there: areas had no screen of their own before) and delete. Delete is a
 * soft delete with "Rückgängig" (CONVENTIONS 7): the blocks keep their stored name and colour and
 * stop counting as work time; undo brings everything back as it was.
 */
class Categories extends Component
{
    public string $newName = '';

    public string $newColor = 'forest';

    public bool $newPomodoro = true;

    /** The category whose "+ Bereich" field is open. */
    public ?int $areaFor = null;

    public string $areaName = '';

    public ?string $notice = null;

    /** The category deleted last, for "Rückgängig". */
    #[Locked]
    public ?int $undoId = null;

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /** @return Collection<int, EventCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return EventCategory::forUser($this->user)->with('areas:id')->ordered()->get();
    }

    /** @return Collection<int, Area> */
    #[Computed]
    public function areas(): Collection
    {
        return Area::forUser($this->user)->ordered()->get();
    }

    /** @return Collection<int, Project> the projects a category can be linked to */
    #[Computed]
    public function projects(): Collection
    {
        return Project::forUser($this->user)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, TaskGroup> groups that are still a group (a mixed group became a project: link that) */
    #[Computed]
    public function groups(): Collection
    {
        return TaskGroup::forUser($this->user)->whereNull('project_id')->orderBy('name')->get(['id', 'name']);
    }

    public function add(): void
    {
        $this->newName = trim($this->newName);
        $data = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newColor' => ['required', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'newPomodoro' => ['boolean'],
        ]);

        $this->user->eventCategories()->create([
            'name' => $data['newName'],
            'color' => $data['newColor'],
            'pomodoro_enabled' => $data['newPomodoro'],
            'sort_order' => (int) $this->user->eventCategories()->max('sort_order') + 1,
        ]);

        $this->reset('newName', 'newPomodoro', 'notice', 'undoId');
        $this->newColor = ScheduleEvent::EVENT_COLORS[$this->categories->count() % count(ScheduleEvent::EVENT_COLORS)];
        $this->fresh();
    }

    public function rename(int $id, string $name): void
    {
        $name = trim($name);
        $category = $this->category($id);
        if ($category === null) {
            return;
        }

        // Errors per row ("rename.{id}"), so the message stands under the field that was typed in.
        $this->resetValidation('rename.'.$id);
        validator(['rename' => [$id => $name]], ['rename.'.$id => ['required', 'string', 'max:255']], attributes: ['rename.'.$id => __('week.categories.name')])
            ->validate();
        $category->update(['name' => $name]);
        $this->fresh();
    }

    public function setColor(int $id, string $color): void
    {
        if (! in_array($color, ScheduleEvent::EVENT_COLORS, true)) {
            return;
        }
        $this->category($id)?->update(['color' => $color]);
        $this->fresh();
    }

    public function togglePomodoro(int $id): void
    {
        $category = $this->category($id);
        $category?->update(['pomodoro_enabled' => ! $category->pomodoro_enabled]);
        $this->fresh();
    }

    public function toggleArea(int $id, int $areaId): void
    {
        $category = $this->category($id);
        if ($category === null) {
            return;
        }

        $ids = $category->areas->pluck('id')->all();
        $ids = in_array($areaId, $ids, true) ? array_diff($ids, [$areaId]) : [...$ids, $areaId];
        $category->syncAreas(array_values($ids)); // drops ids that are not the user's own
        $this->fresh();
    }

    /**
     * The category's link to a project or group (step 5.4): "project:ID", "group:ID" or "" for none. Anything
     * that is not the user's own project or group is ignored, so a link can never point into another account.
     */
    public function setLink(int $id, string $value): void
    {
        $category = $this->category($id);
        if ($category === null) {
            return;
        }

        if (preg_match('/^(project|group):(\d+)$/', $value, $m) === 1) {
            $target = $m[1] === 'project' ? Project::forUser($this->user)->find((int) $m[2]) : TaskGroup::forUser($this->user)->find((int) $m[2]);
            if ($target === null) {
                return;
            }
            $category->linkSteps($m[1], $target->id);
        } else {
            $category->linkSteps(null, null);
        }

        $this->fresh();
    }

    public function openArea(int $id): void
    {
        $this->resetValidation('areaName');
        $this->areaFor = $id;
        $this->areaName = '';
    }

    public function closeArea(): void
    {
        $this->areaFor = null;
        $this->areaName = '';
        $this->resetValidation('areaName');
    }

    /** "+ Bereich": a new area, linked to this category right away. */
    public function addArea(): void
    {
        $category = $this->areaFor === null ? null : $this->category($this->areaFor);
        if ($category === null) {
            $this->closeArea();

            return;
        }

        $this->areaName = trim($this->areaName);
        $this->validate(['areaName' => ['required', 'string', 'max:60']]);

        $area = Area::forUser($this->user)->whereRaw('lower(name) = ?', [Str::lower($this->areaName)])->first()
            ?? $this->user->areas()->create([
                'name' => $this->areaName,
                'color' => ScheduleEvent::EVENT_COLORS[$this->areas->count() % count(ScheduleEvent::EVENT_COLORS)],
                'sort_order' => (int) $this->user->areas()->max('sort_order') + 1,
            ]);

        $category->syncAreas([...$category->areas->pluck('id')->all(), $area->id]);
        $this->closeArea();
        $this->fresh();
    }

    /** Soft delete, undoable. */
    public function delete(int $id): void
    {
        $category = $this->category($id);
        if ($category === null) {
            return;
        }

        $category->delete();
        $this->undoId = $category->id;
        $this->notice = __('week.categories.deleted', ['name' => Str::limit($category->name, 40)]);
        $this->fresh();
    }

    public function undo(): void
    {
        $category = $this->undoId === null ? null : EventCategory::onlyTrashed()->forUser($this->user)->find($this->undoId);
        $this->undoId = null;

        if ($category === null) {
            $this->notice = null;

            return;
        }

        DB::transaction(fn () => $category->restore());
        $this->notice = __('week.categories.restored', ['name' => Str::limit($category->name, 40)]);
        $this->fresh();
    }

    public function dismiss(): void
    {
        $this->notice = null;
        $this->undoId = null;
    }

    private function category(int $id): ?EventCategory
    {
        return EventCategory::forUser($this->user)->with('areas:id')->find($id);
    }

    private function fresh(): void
    {
        unset($this->categories, $this->areas, $this->projects, $this->groups);
    }

    public function render(): View
    {
        return view('livewire.shell.categories');
    }
}
