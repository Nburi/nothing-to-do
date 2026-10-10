<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateCraftIdeaTool extends McpTool
{
    public function name(): string
    {
        return 'create_craft_idea';
    }

    public function description(): string
    {
        return 'Add a Bastelidee — something to do when there is time and nothing urgent.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Required, max 255.'],
                'note' => ['type' => 'string', 'description' => 'Optional free text (material, links, where to start), max 2000.'],
            ],
            'required' => ['title'],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'crafts';
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
        $data = Validator::make($arguments, [
            'title' => ['required', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ])->validate();

        $note = trim((string) ($data['note'] ?? ''));

        $idea = $user->craftIdeas()->create([
            'title' => trim($data['title']),
            'where_to_begin' => $note !== '' ? $note : null,
        ]);

        return ['id' => $idea->id, 'title' => $idea->title, 'note' => $idea->where_to_begin, 'is_done' => false];
    }
}
