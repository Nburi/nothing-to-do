<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateGroupNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_group_note';
    }

    public function description(): string
    {
        return 'Replace the content of one note card of a task group (read it with get_task_group first '
            .'when you want to append). To remove a card use delete_group_note.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'note_id' => ['type' => 'integer'],
                'content' => ['type' => 'string', 'description' => 'Markdown, 1–50000 characters.'],
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
        $note = $group->notes()->find((int) ($arguments['note_id'] ?? 0));

        if ($note === null) {
            throw new McpToolExecutionException('No note with id '.($arguments['note_id'] ?? '?')." in group {$group->id}.");
        }

        $data = Validator::make($arguments, ['content' => ['required', 'string', 'max:50000']])->validate();

        $note->update(['content' => trim($data['content'])]);

        return ['id' => $note->id, 'group_id' => $group->id, 'content' => $note->content];
    }
}
