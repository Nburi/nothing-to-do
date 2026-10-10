<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bulk variant of update_task (e.g. "give these 12 tasks a duration"): up to
 * MAX_ITEMS partial updates in one all-or-nothing transaction. Every item runs
 * through UpdateTaskTool, so ownership checks and TaskMutator's invariants
 * apply to each one exactly as for a single call.
 */
class UpdateTasksTool extends McpTool
{
    private const MAX_ITEMS = 50;

    public function name(): string
    {
        return 'update_tasks';
    }

    public function description(): string
    {
        return 'Update several tasks at once (max '.self::MAX_ITEMS.'). Each item is an update_task payload '
            .'(id plus the fields to change). All-or-nothing: if one item is invalid or not yours, nothing changes.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'updates' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_ITEMS,
                    'items' => (new UpdateTaskTool)->inputSchema(),
                ],
            ],
            'required' => ['updates'],
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
        $items = $arguments['updates'] ?? null;

        if (! is_array($items) || $items === [] || count($items) > self::MAX_ITEMS) {
            throw new McpToolExecutionException('Pass between 1 and '.self::MAX_ITEMS.' updates in "updates".');
        }

        $single = new UpdateTaskTool;

        $updated = DB::transaction(fn () => array_map(
            fn ($item) => $single->handle($user, is_array($item) ? $item : []),
            array_values($items),
        ));

        return ['updated' => count($updated), 'tasks' => $updated];
    }
}
