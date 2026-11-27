<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;

/** Move a board task into a project or group, or out again (api parity with the project and group pages). */
class SetTaskBundleTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'set_task_bundle';
    }

    public function description(): string
    {
        return 'Move a board task into a project (project_id, optional stage_id of that project) or a group (group_id), or take it out again (pass project_id null or group_id null: it is a plain board task of its list afterwards, "waits on" links are let go). Only an open, loose, non-recurring task can move in, and a group takes only tasks of its own list; to move a step from one project to another, take it out first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'project_id' => ['type' => ['integer', 'null']],
                'group_id' => ['type' => ['integer', 'null']],
                'stage_id' => ['type' => ['integer', 'null'], 'description' => 'A stage of the project; null = no stage.'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['task_id'] ?? null);
        $editor = app(ProjectEditor::class);

        if (! array_key_exists('project_id', $arguments) && ! array_key_exists('group_id', $arguments)) {
            throw new McpToolExecutionException('Pass project_id or group_id (null to take the task out of its project or group).');
        }
        $projectId = $arguments['project_id'] ?? null;
        $groupId = $arguments['group_id'] ?? null;
        if ($projectId !== null && $groupId !== null) {
            throw new McpToolExecutionException('A task belongs to a project or a group, not both.');
        }

        if ($projectId === null && $groupId === null) {
            $editor->release($task);
        } else {
            $into = $projectId !== null ? $this->ownedProject($user, $projectId) : $this->ownedTaskGroup($user, $groupId);
            $stage = isset($arguments['stage_id']) ? $this->ownedStage($user, $arguments['stage_id']) : null;
            $this->guarded(fn () => $editor->adopt($user, $into, $task, $stage));
        }

        return (new TaskResource($task->refresh()))->resolve();
    }
}
