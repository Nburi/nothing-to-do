<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;

/** Delete a task group (api parity with the group page). Its tasks stay on the board. */
class DissolveTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'dissolve_task_group';
    }

    public function description(): string
    {
        return 'Dissolve a task group. Its tasks are NOT deleted: every task, open or done, stays in its list as a plain board task. The note cards of the group are deleted and cannot be brought back. Pass confirm_name matching the group\'s exact name, and ask the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the group\'s current name.'],
            ],
            'required' => ['id', 'confirm_name'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $group = $this->ownedTaskGroup($user, $arguments['id'] ?? null);

        if (($arguments['confirm_name'] ?? null) !== $group->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This group's current name is: \"{$group->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $editor = app(ProjectEditor::class);
        $impact = $editor->impact($group);
        $editor->deleteGroup($group);

        return [
            'dissolved' => true,
            'id' => $group->id,
            'name' => $group->name,
            'tasks_kept' => $impact['open'] + $impact['done'],
            'notes_deleted' => $impact['notes'],
        ];
    }
}
