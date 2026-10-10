<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\AgendaEntry;
use App\Models\Project;
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

    protected function ownedProject(User $user, mixed $id): Project
    {
        $project = $user->projects()->find((int) $id);

        if ($project === null) {
            throw new McpToolExecutionException("No project with id {$id} found for this account.");
        }

        return $project;
    }

    /** An Agenda entry this user may see: their own private ones or any in a class they belong to. */
    protected function visibleAgendaEntry(User $user, mixed $id): AgendaEntry
    {
        $entry = AgendaEntry::query()->visibleTo($user)->withCompletionState($user)->withPrivateNoteFor($user)->find((int) $id);

        if ($entry === null) {
            throw new McpToolExecutionException("No agenda entry with id {$id} found for this account.");
        }

        return $entry;
    }
}
