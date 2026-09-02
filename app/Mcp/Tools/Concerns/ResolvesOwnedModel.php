<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;

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

    protected function ownedTaskGroup(User $user, mixed $id): TaskGroup
    {
        $group = $user->taskGroups()->find((int) $id);

        if ($group === null) {
            throw new McpToolExecutionException("No task group with id {$id} found for this account.");
        }

        return $group;
    }
}
