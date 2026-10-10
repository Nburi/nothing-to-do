<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\CraftIdea;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ListCraftIdeasTool extends McpTool
{
    public function name(): string
    {
        return 'list_craft_ideas';
    }

    public function description(): string
    {
        return 'The Bastelideen — the low-pressure "what to do when bored" list (no dates). Each idea has a title and one '
            .'free-text note ("note": material, links, where to start). Open ideas by default; done=true lists the finished ones.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'done' => ['type' => 'boolean', 'description' => 'true = finished ideas instead of open ones.'],
                'search' => ['type' => 'string', 'description' => 'Substring match on title or note.'],
            ],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'crafts';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'done' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
        ])->validate();

        $query = $user->craftIdeas();
        ($data['done'] ?? false) ? $query->done() : $query->open();

        if (isset($data['search']) && trim($data['search']) !== '') {
            $term = '%'.trim($data['search']).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('where_to_begin', 'like', $term));
        }

        $ideas = $query->orderBy('id')->get();

        return [
            'count' => $ideas->count(),
            'ideas' => $ideas->map(fn (CraftIdea $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'note' => $i->where_to_begin,
                'is_done' => (bool) $i->is_done,
            ])->values()->all(),
        ];
    }
}
