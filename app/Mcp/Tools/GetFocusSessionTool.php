<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class GetFocusSessionTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'get_focus_session';
    }

    public function description(): string
    {
        return 'The Pomodoro focus session that is running now or starts within 5 minutes (a Pomodoro-enabled '
            .'category block of today): its phase (work/break, remaining seconds, or "awaiting_next" when waiting '
            .'for a manual continue) and what the app suggests working on. All null when there is none.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return ControllerBridge::call($user, ScheduleEventController::class, 'focus');
    }
}
