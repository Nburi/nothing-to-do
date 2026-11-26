<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Projects\NoteEditor;

class AddGroupNoteTool extends McpTool
{
    public function name(): string
    {
        return 'add_group_note';
    }

    public function description(): string
    {
        return 'Same as add_note for a task group (kept under its old name): add a note card (Markdown) to a task group.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'content' => ['type' => 'string', 'description' => 'Markdown, 1–'.NoteEditor::BODY_MAX.' characters.'],
            ],
            'required' => ['group_id', 'content'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $note = (new AddNoteTool)->handle($user, ['group_id' => $arguments['group_id'] ?? null, 'body' => $arguments['content'] ?? null]);

        return $note + ['content' => $note['body']];
    }
}
