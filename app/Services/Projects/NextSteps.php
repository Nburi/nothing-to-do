<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Step 4.3 (R6, risk F10): what the board shows of a user's projects and groups.
 *
 * A project or group never turns into a wall: the board shows one card with at most its next two
 * open steps (Niels, 2026-11-17), the rest waits on the Projekte page.
 *
 * Project steps are "next" when they are open, in To-dos or Tasks, not waiting on an open task
 * ("wartet auf …"), and in the current stage or in no stage. The current stage is the first stage
 * that still has an open step in any list, so a stage stays current until its last step is done,
 * even one still in the Inbox. Order: current stage first, then steps in no stage, each by the
 * project's own order (sort_order, id). Inbox and Later steps stay in their own columns.
 *
 * Group steps are the group's open tasks in its order; a group holds one list.
 *
 * Only the visible steps may be suggested (Suggestions excludes hiddenTaskIds).
 */
final class NextSteps
{
    public const VISIBLE = 2;

    /** @return Collection<int, Bundle> projects first, then groups, each in the user's order */
    public function bundles(User $user): Collection
    {
        $open = Task::forUser($user)->active()
            ->where(fn ($q) => $q->whereNotNull('project_id')->orWhereNotNull('group_id'))
            ->with('area')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $waitIds = $open->pluck('waits_on_id')->filter()->unique()->values();
        $stillOpen = $waitIds->isEmpty() ? collect() : Task::forUser($user)->active()->whereIn('id', $waitIds)->pluck('id')->flip();

        $doneByStage = Task::forUser($user)->where('is_completed', true)->whereNotNull('stage_id')
            ->selectRaw('stage_id, count(*) as n')->groupBy('stage_id')->pluck('n', 'stage_id');
        $doneByGroup = Task::forUser($user)->where('is_completed', true)->whereNull('project_id')->whereNotNull('group_id')
            ->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');

        $projects = Project::forUser($user)->with('stages')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Project $p) => $this->project($p, $open->where('project_id', $p->id)->values(), $stillOpen, $doneByStage));

        $groups = TaskGroup::forUser($user)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (TaskGroup $g) => $this->group($g, $open->whereNull('project_id')->where('group_id', $g->id)->values(), (int) ($doneByGroup[$g->id] ?? 0)));

        return $projects->concat($groups)->values();
    }

    /**
     * Open steps the board does not show and the pick engine must not suggest.
     *
     * @return list<int>
     */
    public function hiddenTaskIds(User $user): array
    {
        return $this->bundles($user)->flatMap(fn (Bundle $b) => $b->hidden)->values()->all();
    }

    /** @param  Collection<int, Task>  $steps  open steps, in project order */
    private function project(Project $project, Collection $steps, Collection $stillOpen, Collection $doneByStage): Bundle
    {
        $stages = $project->stages->values();
        $stageIds = $stages->pluck('id')->flip();
        $stageOf = fn (Task $t): ?int => $t->stage_id !== null && $stageIds->has($t->stage_id) ? $t->stage_id : null;

        /** @var ProjectStage|null $current */
        $current = $stages->first(fn (ProjectStage $s) => $steps->contains(fn (Task $t) => $stageOf($t) === $s->id));
        $blocked = fn (Task $t): bool => $t->waits_on_id !== null && $stillOpen->has($t->waits_on_id);

        $eligible = $steps
            ->filter(fn (Task $t) => in_array($t->list, Task::WORK_LISTS, true) && ! $blocked($t))
            ->filter(fn (Task $t) => $stageOf($t) === null || $stageOf($t) === $current?->id)
            ->sortBy(fn (Task $t) => $stageOf($t) === null ? 1 : 0) // stable: keeps the project order inside each part
            ->values();

        $visible = $eligible->take(self::VISIBLE)->values();
        $onBoard = $steps->filter(fn (Task $t) => in_array($t->list, Task::WORK_LISTS, true))->pluck('id')->all();

        $progress = null;
        if ($current !== null) {
            $done = (int) ($doneByStage[$current->id] ?? 0);
            $progress = ['done' => $done, 'total' => $done + $steps->filter(fn (Task $t) => $stageOf($t) === $current->id)->count()];
        }

        return new Bundle(
            kind: 'project',
            id: $project->id,
            name: $project->name,
            list: $visible->first()?->list,
            visible: $visible,
            more: $steps->count() - $visible->count(),
            open: $steps->count(),
            progress: $progress,
            stage: $current,
            stageNumber: $current === null ? 0 : $stages->search(fn (ProjectStage $s) => $s->id === $current->id) + 1,
            stageCount: $stages->count(),
            bundled: array_values($onBoard),
            hidden: array_values(array_diff($onBoard, $visible->pluck('id')->all())),
        );
    }

    /** @param  Collection<int, Task>  $steps  open steps, in group order */
    private function group(TaskGroup $group, Collection $steps, int $done): Bundle
    {
        $visible = $steps->take(self::VISIBLE)->values();
        $ids = $steps->pluck('id')->all();

        return new Bundle(
            kind: 'group',
            id: $group->id,
            name: $group->name,
            list: $steps->isEmpty() ? null : ($group->list ?? $visible->first()->list),
            visible: $visible,
            more: $steps->count() - $visible->count(),
            open: $steps->count(),
            progress: ['done' => $done, 'total' => $done + $steps->count()],
            stage: null,
            stageNumber: 0,
            stageCount: 0,
            bundled: $ids,
            hidden: array_values(array_diff($ids, $visible->pluck('id')->all())),
        );
    }
}
