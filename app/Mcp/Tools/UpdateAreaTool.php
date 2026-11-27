<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\AreaController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Models\ScheduleEvent;
use App\Models\User;

/** Areas (api parity). Same rules as PATCH /api/areas/{id}. */
class UpdateAreaTool extends McpTool
{
    public function name(): string
    {
        return 'update_area';
    }

    public function description(): string
    {
        return 'Rename or recolour an area. Only the fields you pass change.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'maxLength' => 60],
                'color' => ['type' => 'string', 'enum' => ScheduleEvent::EVENT_COLORS],
            ],
            'required' => ['id'],
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
        $id = (int) ($arguments['id'] ?? 0);
        unset($arguments['id']);

        return ControllerBridge::call($user, AreaController::class, 'update', $arguments, 'PATCH', ['area' => $id]);
    }
}
