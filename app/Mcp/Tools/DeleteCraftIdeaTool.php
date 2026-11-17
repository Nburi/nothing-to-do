<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;

class DeleteCraftIdeaTool extends McpTool
{
    public function name(): string
    {
        return 'delete_craft_idea';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a Bastelidee. Pass confirm_title matching its exact title. Prefer '
            .'update_craft_idea(is_done) to just tick it off. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the idea\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'crafts';
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
        $idea = $user->craftIdeas()->find((int) ($arguments['id'] ?? 0));

        if ($idea === null) {
            throw new McpToolExecutionException('No craft idea with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        if (($arguments['confirm_title'] ?? null) !== $idea->title) {
            throw new McpToolExecutionException(
                "confirm_title did not match. This idea's current title is: \"{$idea->title}\". ".
                'Pass that exact string as confirm_title to proceed, after checking with the user.'
            );
        }

        $id = $idea->id;
        $title = $idea->title;
        $idea->delete();

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }
}
