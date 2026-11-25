<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\NoteEditor;

/** Old tool name, kept for existing clients: the same as update_note for a card of a task group. */
class UpdateGroupNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_group_note';
    }

    public function description(): string
    {
        return 'Same as update_note for a task group (kept under its old name): replace the text of a group note card. REPLACES the whole text.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'note_id' => ['type' => 'integer'],
                'content' => ['type' => 'string', 'description' => 'Markdown, 1–'.NoteEditor::BODY_MAX.' characters.'],
            ],
            'required' => ['group_id', 'note_id', 'content'],
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
        $group = $this->ownedTaskGroup($user, $arguments['group_id'] ?? null);
        $note = $this->ownedNote($user, $arguments['note_id'] ?? null);
        if ($note->group_id !== $group->id) {
            throw new McpToolExecutionException('No note with id '.($arguments['note_id'] ?? '?')." in group {$group->id}.");
        }

        $result = (new UpdateNoteTool)->handle($user, ['id' => $note->id, 'body' => $arguments['content'] ?? null]);

        return $result + ['content' => $result['body']];
    }
}
