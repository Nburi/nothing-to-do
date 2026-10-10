<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

/** Same safety net as delete_task: mcp:delete plus an exact confirm_name. */
class DeleteProjectTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_project';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a project and its brainstorm text. Its OPEN tasks are not deleted — they '
            .'are released back to the Inbox; its completed tasks stay as loose finished tasks. You must pass '
            .'confirm_name matching the project\'s exact current name. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the project\'s current name.'],
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
        $project = $this->ownedProject($user, $arguments['id'] ?? null);
        $confirm = $arguments['confirm_name'] ?? null;

        if (! is_string($confirm) || $confirm !== $project->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This project's current name is: \"{$project->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $user->tasks()
            ->where('project_id', $project->id)
            ->where('is_completed', false)
            ->update(['project_id' => null, 'list' => 'inbox']);

        $id = $project->id;
        $name = $project->name;
        $project->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $name];
    }
}
