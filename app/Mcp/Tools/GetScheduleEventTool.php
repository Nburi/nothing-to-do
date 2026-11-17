<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class GetScheduleEventTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'get_schedule_event';
    }

    public function description(): string
    {
        return 'Get one Zeitplan entry by id (category, times, buffers, Pomodoro state, linked task ids, attribute values).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return ControllerBridge::call($user, ScheduleEventController::class, 'show', [], 'GET', ['id' => (int) ($arguments['id'] ?? 0)]);
    }
}
