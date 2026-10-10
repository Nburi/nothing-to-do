<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class AddGroupNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'add_group_note';
    }

    public function description(): string
    {
        return 'Add a note card (Markdown) to a task group. A group holds several separate cards — one idea, '
            .'checklist or reminder per card.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'content' => ['type' => 'string', 'description' => 'Markdown, 1–50000 characters.'],
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
        $group = $this->ownedTaskGroup($user, $arguments['group_id'] ?? null);

        $data = Validator::make($arguments, ['content' => ['required', 'string', 'max:50000']])->validate();

        $note = $group->notes()->create([
            'content' => trim($data['content']),
            'sort_order' => (int) ($group->notes()->max('sort_order') ?? -1) + 1,
        ]);

        return ['id' => $note->id, 'group_id' => $group->id, 'content' => $note->content];
    }
}
