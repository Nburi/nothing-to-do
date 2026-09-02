<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\ListConcepts;

class SetListConceptTool extends McpTool
{
    public function name(): string
    {
        return 'set_list_concept';
    }

    public function description(): string
    {
        return 'Switch which mental model the board is organized by: three_things, simple, eisenhower, or kanban.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'concept' => ['type' => 'string', 'enum' => array_keys(ListConcepts::CATALOG)],
            ],
            'required' => ['concept'],
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
        $concept = $arguments['concept'] ?? null;

        if (! is_string($concept) || ! ListConcepts::isValid($concept)) {
            throw new McpToolExecutionException('Not a valid, available list concept. Valid options: '.implode(', ', array_keys(ListConcepts::CATALOG)));
        }

        $user->update(['list_concept' => $concept]);

        return ['list_concept' => $concept];
    }
}
