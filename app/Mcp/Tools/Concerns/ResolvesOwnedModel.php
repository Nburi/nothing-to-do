<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Every tool resolves a record through the owner relationship — never a bare
 * `Model::find($id)` — mirroring Api\TaskController::userTask(). A foreign
 * or missing id becomes a structured tool error (isError: true), not a
 * protocol error: it's a normal, expected outcome of a model that can
 * hallucinate an id, not a broken request.
 */
trait ResolvesOwnedModel
{
    protected function ownedTask(User $user, mixed $id): Task
    {
        $task = $user->tasks()->find((int) $id);

        if ($task === null) {
            throw new McpToolExecutionException("No task with id {$id} found for this account.");
        }

        return $task;
    }

    protected function ownedProject(User $user, mixed $id): Project
    {
        return Project::forUser($user)->find((int) $id)
            ?? throw new McpToolExecutionException("No project with id {$id} found for this account.");
    }

    protected function ownedTaskGroup(User $user, mixed $id): TaskGroup
    {
        return TaskGroup::forUser($user)->find((int) $id)
            ?? throw new McpToolExecutionException("No task group with id {$id} found for this account.");
    }

    protected function ownedStage(User $user, mixed $id): ProjectStage
    {
        return ProjectStage::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->find((int) $id)
            ?? throw new McpToolExecutionException("No stage with id {$id} found for this account.");
    }

    protected function ownedNote(User $user, mixed $id): Note
    {
        return Note::where('user_id', $user->id)->find((int) $id)
            ?? throw new McpToolExecutionException("No note with id {$id} found for this account.");
    }

    /**
     * Runs a project edit and turns the editor's refusals (a stage of another project, two steps that
     * would wait on each other, a step that is already done) into a normal tool error.
     */
    protected function guarded(callable $edit): mixed
    {
        try {
            return $edit();
        } catch (ModelNotFoundException) {
            throw new McpToolExecutionException('That step is done or no longer exists.');
        } catch (InvalidArgumentException $e) {
            throw new McpToolExecutionException($e->getMessage() === 'loop' ? 'These two steps would wait on each other.' : $e->getMessage());
        }
    }
}
