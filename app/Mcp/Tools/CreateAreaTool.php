<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\AreaController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Models\ScheduleEvent;
use App\Models\User;

/** Areas (api parity). Same rules as POST /api/areas. */
class CreateAreaTool extends McpTool
{
    public function name(): string
    {
        return 'create_area';
    }

    public function description(): string
    {
        return 'Create an area (a topic to sort tasks by). Put tasks in it with area_id on create_task / update_task; let a category suggest it with area_ids on update_category.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'maxLength' => 60],
                'color' => ['type' => 'string', 'enum' => ScheduleEvent::EVENT_COLORS],
            ],
            'required' => ['name'],
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
        return ControllerBridge::call($user, AreaController::class, 'store', $arguments, 'POST');
    }
}
