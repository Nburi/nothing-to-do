<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bulk variant of create_task: up to MAX_ITEMS tasks in one call, all-or-nothing
 * (a transaction — one invalid item rolls back the whole batch, so a retry
 * can't create half of them twice). Every item goes through CreateTaskTool
 * itself, so the invariants live in exactly one place.
 */
class CreateTasksTool extends McpTool
{
    private const MAX_ITEMS = 25;

    public function name(): string
    {
        return 'create_tasks';
    }

    public function description(): string
    {
        return 'Create several tasks at once (max '.self::MAX_ITEMS.'). Each item takes the same fields as '
            .'create_task. All-or-nothing: if one item is invalid, none are created.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_ITEMS,
                    'items' => (new CreateTaskTool)->inputSchema(),
                ],
            ],
            'required' => ['tasks'],
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
        $items = $arguments['tasks'] ?? null;

        if (! is_array($items) || $items === [] || count($items) > self::MAX_ITEMS) {
            throw new McpToolExecutionException('Pass between 1 and '.self::MAX_ITEMS.' tasks in "tasks".');
        }

        $single = new CreateTaskTool;

        $created = DB::transaction(fn () => array_map(
            fn ($item) => $single->handle($user, is_array($item) ? $item : []),
            array_values($items),
        ));

        return ['created' => count($created), 'tasks' => $created];
    }
}
