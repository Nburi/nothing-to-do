<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\NoteEditor;

class DeleteNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_note';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a note card. Irreversible. Pass confirm_name matching the exact current name of the project or group the note belongs to, after checking with the user.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'The name of the note\'s project or group.'],
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
        $note = $this->ownedNote($user, $arguments['id'] ?? null);
        $owner = $note->project_id !== null ? $note->project : $note->group;

        if (($arguments['confirm_name'] ?? null) !== $owner->name) {
            throw new McpToolExecutionException("confirm_name did not match. This note belongs to \"{$owner->name}\". Pass that exact name to proceed, after checking with the user.");
        }

        $id = $note->id;
        app(NoteEditor::class)->delete($note);

        return ['deleted' => true, 'id' => $id];
    }
}
