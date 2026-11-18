<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Step 4.3: every change to projects, stages, groups and their steps. All reads are scoped to the
 * user, so another user's id is a ModelNotFoundException (404), never a write. Nothing here
 * deletes a task: removing a stage leaves its steps without a stage.
 */
final class ProjectEditor
{
    public function createProject(User $user, string $name): Project
    {
        $project = new Project(['name' => $name, 'sort_order' => (int) Project::forUser($user)->max('sort_order') + 1]);
        $project->user_id = $user->id;
        $project->save();

        return $project;
    }

    /** @param  'todos'|'tasks'  $list */
    public function createGroup(User $user, string $name, string $list): TaskGroup
    {
        if (! in_array($list, TaskGroup::LISTS, true)) {
            throw new InvalidArgumentException("A group holds To-dos or Tasks, not [{$list}].");
        }

        $group = new TaskGroup(['name' => $name, 'list' => $list, 'sort_order' => (int) TaskGroup::forUser($user)->max('sort_order') + 1]);
        $group->user_id = $user->id;
        $group->save();

        return $group;
    }

    public function rename(Project|TaskGroup $bundle, string $name): void
    {
        $bundle->update(['name' => $name]);
    }

    public function addStage(Project $project, string $name, ?string $targetDate): ProjectStage
    {
        return $project->stages()->create([
            'name' => $name,
            'target_date' => $targetDate,
            'sort_order' => (int) $project->stages()->max('sort_order') + 1,
        ]);
    }

    public function updateStage(ProjectStage $stage, string $name, ?string $targetDate): void
    {
        $stage->update(['name' => $name, 'target_date' => $targetDate]);
    }

    /** The stage goes, its steps stay (without a stage). */
    public function removeStage(ProjectStage $stage): void
    {
        DB::transaction(function () use ($stage) {
            Task::where('stage_id', $stage->id)->update(['stage_id' => null]);
            $stage->delete();
        });
    }

    /** @param  'todos'|'tasks'  $list */
    public function addProjectStep(User $user, Project $project, ?ProjectStage $stage, string $title, string $list = 'tasks'): Task
    {
        if (! in_array($list, Task::WORK_LISTS, true)) {
            throw new InvalidArgumentException("A new step goes into To-dos or Tasks, not [{$list}].");
        }
        if ($stage !== null && $stage->project_id !== $project->id) {
            throw new InvalidArgumentException('The stage belongs to another project.');
        }

        $task = new Task(['title' => $title, 'list' => $list]);
        $task->forceFill(['user_id' => $user->id, 'project_id' => $project->id, 'stage_id' => $stage?->id]);
        $task->forceFill(['sort_order' => (int) $this->siblings($task)->max('sort_order') + 1])->save();

        return $task;
    }

    public function addGroupStep(User $user, TaskGroup $group, string $title): Task
    {
        $task = new Task(['title' => $title, 'list' => $group->list ?? 'tasks']);
        $task->forceFill([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'sort_order' => (int) $group->tasks()->max('sort_order') + 1,
        ])->save();

        return $task;
    }

    /** One place up (-1) or down (+1) among the open steps of the same stage or group. */
    public function move(Task $step, int $direction): void
    {
        DB::transaction(function () use ($step, $direction) {
            $ids = $this->siblings($step)->active()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $from = array_search($step->id, $ids, true);
            $to = $from + ($direction < 0 ? -1 : 1);
            if ($from === false || $to < 0 || $to >= count($ids)) {
                return;
            }

            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            foreach ($ids as $order => $id) {
                Task::whereKey($id)->update(['sort_order' => $order]);
            }
        });
    }

    /** Move a project step into a stage (null = no stage); it goes to the end there. */
    public function setStage(Task $step, ?ProjectStage $stage): void
    {
        if ($stage !== null && $stage->project_id !== $step->project_id) {
            throw new InvalidArgumentException('The stage belongs to another project.');
        }

        $step->forceFill(['stage_id' => $stage?->id]);
        $step->forceFill(['sort_order' => (int) $this->siblings($step)->whereKeyNot($step->id)->max('sort_order') + 1])->save();
    }

    /**
     * "Wartet auf …": the step waits on another step of the same project (null = on nothing).
     *
     * @throws InvalidArgumentException when the two would wait on each other (directly or round a chain)
     */
    public function setWaitsOn(Task $step, ?Task $on): void
    {
        if ($on !== null) {
            if ($on->id === $step->id || $on->project_id === null || $on->project_id !== $step->project_id) {
                throw new InvalidArgumentException('A step waits only on another step of its project.');
            }

            $seen = [];
            for ($at = $on; $at !== null && ! isset($seen[$at->id]); $at = $at->waits_on_id ? Task::find($at->waits_on_id) : null) {
                if ($at->waits_on_id === $step->id) {
                    throw new InvalidArgumentException('loop');
                }
                $seen[$at->id] = true;
            }
        }

        $step->forceFill(['waits_on_id' => $on?->id])->save();
    }

    /** Steps that share an order with $step: same project and stage, or same group. */
    private function siblings(Task $step): Builder
    {
        $query = Task::where('user_id', $step->user_id);

        if ($step->project_id !== null) {
            return $query->where('project_id', $step->project_id)
                ->when($step->stage_id === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $step->stage_id));
        }

        return $query->whereNull('project_id')->where('group_id', $step->group_id);
    }
}
