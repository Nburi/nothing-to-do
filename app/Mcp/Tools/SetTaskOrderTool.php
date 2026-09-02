<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * A concept-agnostic reorder: writes `sort_order` sequentially for the given
 * ids, which every list concept's own ordering ties back to eventually
 * (Task::scopeBoardOrdered()/scopeGroupOrdered()). Deliberately simpler than
 * mirroring each concept's own zone-based reorder endpoint (three_things'
 * list+today zones, simple's today/other zones, eisenhower's quadrant axes,
 * kanban's columns) — those each also flip other fields (is_today, list) as
 * a side effect of the drop zone, which belongs in update_task/complete_task
 * instead of a bulk reorder call. Ids not owned by this user are skipped,
 * never trusted.
 */
class SetTaskOrderTool extends McpTool
{
    public function name(): string
    {
        return 'set_task_order';
    }

    public function description(): string
    {
        return 'Set the manual sort order for a list of tasks — pass their ids in the order you want them '
            .'to appear. Does not change any other field (list, project, today-flag); use update_task for that.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Task ids, in the desired order.'],
            ],
            'required' => ['ids'],
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
        $data = Validator::make($arguments, [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])->validate();

        $updated = [];

        foreach (array_values($data['ids']) as $position => $id) {
            $task = $user->tasks()->find((int) $id);

            if ($task === null) {
                continue;
            }

            $task->update(['sort_order' => $position]);
            $updated[] = $task->id;
        }

        return [
            'updated_count' => count($updated),
            'updated_ids' => $updated,
            'skipped_ids' => array_values(array_diff(array_map('intval', $data['ids']), $updated)),
        ];
    }
}
