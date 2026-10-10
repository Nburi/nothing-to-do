<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class DeleteGroupNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_group_note';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete one note card of a task group. Pass confirm_group_name matching the '
            .'group\'s exact current name. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'note_id' => ['type' => 'integer'],
                'confirm_group_name' => ['type' => 'string'],
            ],
            'required' => ['group_id', 'note_id', 'confirm_group_name'],
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
        $group = $this->ownedTaskGroup($user, $arguments['group_id'] ?? null);
        $note = $group->notes()->find((int) ($arguments['note_id'] ?? 0));

        if ($note === null) {
            throw new McpToolExecutionException('No note with id '.($arguments['note_id'] ?? '?')." in group {$group->id}.");
        }

        $confirm = $arguments['confirm_group_name'] ?? null;

        if (! is_string($confirm) || $confirm !== $group->name) {
            throw new McpToolExecutionException(
                "confirm_group_name did not match. This group's current name is: \"{$group->name}\". ".
                'Pass that exact string to proceed, after checking with the user.'
            );
        }

        $id = $note->id;
        $note->delete();

        return ['deleted' => true, 'id' => $id, 'group_id' => $group->id];
    }
}
