<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateCraftIdeaTool extends McpTool
{
    public function name(): string
    {
        return 'update_craft_idea';
    }

    public function description(): string
    {
        return 'Edit a Bastelidee, or mark it done / bring it back (is_done). Only the fields you pass change; '
            .'note null or "" clears the note.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'note' => ['type' => ['string', 'null']],
                'is_done' => ['type' => 'boolean'],
            ],
            'required' => ['id'],
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
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $idea = $user->craftIdeas()->find((int) ($arguments['id'] ?? 0));

        if ($idea === null) {
            throw new McpToolExecutionException('No craft idea with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        $data = Validator::make($arguments, [
            'title' => ['sometimes', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_done' => ['sometimes', 'boolean'],
        ])->validate();

        $updates = [];

        if (array_key_exists('title', $data)) {
            $updates['title'] = trim($data['title']);
        }
        if (array_key_exists('note', $data)) {
            $note = trim((string) $data['note']);
            $updates['where_to_begin'] = $note !== '' ? $note : null;
        }
        if (array_key_exists('is_done', $data)) {
            $updates['is_done'] = $data['is_done'];
        }

        $idea->update($updates);

        return ['id' => $idea->id, 'title' => $idea->title, 'note' => $idea->where_to_begin, 'is_done' => (bool) $idea->is_done];
    }
}
