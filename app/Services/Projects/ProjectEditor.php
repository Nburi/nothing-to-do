<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
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

    /**
     * Step 4.3c: one step to a place: a stage of its project (null = no stage; a group has none) and a
     * position among that stage's open steps ($index counts without the step itself, null = the end).
     * Dragging, the keyboard and the ⋯ menu all end here.
     *
     * The whole order is read once under a lock, so two quick moves queue up and each sees the other's
     * result; the target list is numbered 0..n-1 again (stable positions); and the call is idempotent:
     * a step that already sits there is left alone and the result says `changed: false`.
     *
     * A move into another stage can contradict a "wartet auf" link: a step in stage 1 that waits on one
     * in stage 2 would wait for something that cannot start before it is done. Such links are let go
     * in the same transaction (StepMove::$cleared names them, so the page can offer "Rückgängig").
     */
    public function place(Task $step, ?ProjectStage $stage, ?int $index = null): StepMove
    {
        $this->assertPlace($step, $stage);

        return DB::transaction(function () use ($step, $stage, $index) {
            $move = $this->arrange($step, $stage, $index);

            if (! $move->changed || $step->project_id === null) {
                return $move;
            }

            $at = $stage === null ? false : $this->stageIds($step->project_id)->search($stage->id);
            $move = $move->withStageNumber($at === false ? null : $at + 1);

            return $move->stageChanged() ? $move->withCleared($this->letGoOfContradictions($step)) : $move;
        });
    }

    /**
     * The undo of place(): the step goes back to its old stage and place, and the "wartet auf" links
     * the move let go are set again, wherever nothing has changed them since.
     *
     * @param  array<int, int>  $cleared  StepMove::$cleared
     */
    public function putBack(Task $step, ?ProjectStage $stage, int $index, array $cleared): void
    {
        $this->assertPlace($step, $stage);

        DB::transaction(function () use ($step, $index, $stage, $cleared) {
            $this->arrange($step, $stage, $index);

            foreach ($cleared as $waiterId => $blockerId) {
                $open = Task::where('user_id', $step->user_id)->where('project_id', $step->project_id)->active();
                $waiter = (clone $open)->whereNull('waits_on_id')->find($waiterId);
                $blocker = (clone $open)->find($blockerId);
                if ($waiter === null || $blocker === null) {
                    continue;
                }

                try {
                    $this->setWaitsOn($waiter, $blocker);
                } catch (InvalidArgumentException) {
                    // Another link closed a loop since then: this one stays let go.
                }
            }
        });
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

    private function assertPlace(Task $step, ?ProjectStage $stage): void
    {
        if ($step->project_id === null) {
            if ($stage !== null) {
                throw new InvalidArgumentException('A group has no stages.');
            }
        } elseif ($stage !== null && $stage->project_id !== $step->project_id) {
            throw new InvalidArgumentException('The stage belongs to another project.');
        }
    }

    /**
     * Put the step at its place and number that stage's open steps 0..n-1. Everything that shares
     * this order (all open steps of the project, or of the group) is read once, under a lock.
     */
    private function arrange(Task $step, ?ProjectStage $stage, ?int $index): StepMove
    {
        $open = $this->openSteps($step)->lockForUpdate()->get(['id', 'stage_id']);
        $at = $open->firstWhere('id', $step->id);
        if ($at === null) {
            // Done or deleted since the page was drawn.
            throw (new ModelNotFoundException)->setModel(Task::class, [$step->id]);
        }

        $fromStage = $at->stage_id === null ? null : (int) $at->stage_id;
        $toStage = $stage?->id;
        $inStage = fn (?int $stageId): Collection => $open->filter(fn (Task $t) => ($t->stage_id === null ? null : (int) $t->stage_id) === $stageId)->pluck('id')->values();

        $fromIndex = (int) $inStage($fromStage)->search($step->id);
        $others = $inStage($toStage)->reject(fn (int $id) => $id === $step->id)->values()->all();
        $to = $index === null ? count($others) : max(0, min($index, count($others)));

        if ($fromStage === $toStage && $to === $fromIndex) {
            return new StepMove(false, $fromStage, $fromIndex, $toStage, $to);
        }

        array_splice($others, $to, 0, [$step->id]);
        foreach ($others as $order => $id) {
            Task::whereKey($id)->update($id === $step->id ? ['sort_order' => $order, 'stage_id' => $toStage] : ['sort_order' => $order]);
        }

        return new StepMove(true, $fromStage, $fromIndex, $toStage, $to);
    }

    /**
     * After a step changed stage: every open "wartet auf" link of this step, or to it, whose waiting
     * side now sits in an earlier stage than the step it waits on is let go. No stage never contradicts.
     *
     * @return array<int, int> waiting step id => the step it waited on
     */
    private function letGoOfContradictions(Task $step): array
    {
        $rank = $this->stageIds($step->project_id)->flip();
        $rankOf = fn (?int $stageId): int => $stageId !== null && $rank->has($stageId) ? $rank[$stageId] : -1;

        $open = Task::where('user_id', $step->user_id)->where('project_id', $step->project_id)->active()
            ->get(['id', 'stage_id', 'waits_on_id'])->keyBy('id');

        $cleared = [];
        foreach ($open as $waiter) {
            $blocker = $waiter->waits_on_id === null ? null : $open->get((int) $waiter->waits_on_id);
            if ($blocker === null || ($waiter->id !== $step->id && $blocker->id !== $step->id)) {
                continue;
            }

            $waits = $rankOf($waiter->stage_id === null ? null : (int) $waiter->stage_id);
            $on = $rankOf($blocker->stage_id === null ? null : (int) $blocker->stage_id);
            if ($waits >= 0 && $on >= 0 && $waits < $on) {
                $cleared[$waiter->id] = $blocker->id;
            }
        }

        if ($cleared !== []) {
            Task::whereKey(array_keys($cleared))->update(['waits_on_id' => null]);
        }

        return $cleared;
    }

    /** @return Collection<int, int> the project's stage ids, in roadmap order */
    private function stageIds(int $projectId): Collection
    {
        return ProjectStage::where('project_id', $projectId)->orderBy('sort_order')->orderBy('id')->pluck('id')->values();
    }

    /** Open steps that share one order with $step: all of its project (every stage), or all of its group. */
    private function openSteps(Task $step): Builder
    {
        $query = Task::where('user_id', $step->user_id)->active();

        return ($step->project_id !== null
            ? $query->where('project_id', $step->project_id)
            : $query->whereNull('project_id')->where('group_id', $step->group_id))
            ->orderBy('sort_order')->orderBy('id');
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
