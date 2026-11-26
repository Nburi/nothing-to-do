<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

/** Old tool name, kept for existing clients: the same as delete_note for a card of a task group. */
class DeleteGroupNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_group_note';
    }

    public function description(): string
    {
        return 'Same as delete_note for a task group (kept under its old name): PERMANENTLY delete a group note card. '
            .'Pass confirm_group_name matching the group\'s exact current name, after checking with the user.';
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
        $note = $this->ownedNote($user, $arguments['note_id'] ?? null);
        if ($note->group_id !== $group->id) {
            throw new McpToolExecutionException('No note with id '.($arguments['note_id'] ?? '?')." in group {$group->id}.");
        }

        $result = (new DeleteNoteTool)->handle($user, ['id' => $note->id, 'confirm_name' => $arguments['confirm_group_name'] ?? null]);

        return $result + ['group_id' => $group->id];
    }
}
