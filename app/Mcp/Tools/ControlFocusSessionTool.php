<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class ControlFocusSessionTool extends McpTool
{
    use ScheduleToolDefaults;

    private const ACTIONS = [
        'start' => 'startFocus',
        'stop' => 'stopFocus',
        'continue' => 'continueFocus',
        'skip_break' => 'skipFocusBreak',
    ];

    public function name(): string
    {
        return 'control_focus_session';
    }

    public function description(): string
    {
        return 'Drive the Pomodoro timer of a Pomodoro-enabled category block (find it with get_focus_session or '
            .'list_schedule_events). start = begin the first work session; stop = end and reset the session; '
            .'continue = begin the next phase when the app waits for it (autostart off); skip_break = jump over the '
            .'upcoming break straight to the next work session. Starting also sends the configured push notifications.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_id' => ['type' => 'integer', 'description' => 'The category block carrying the timer.'],
                'action' => ['type' => 'string', 'enum' => array_keys(self::ACTIONS)],
            ],
            'required' => ['event_id', 'action'],
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
        $action = self::ACTIONS[$arguments['action'] ?? ''] ?? null;

        if ($action === null) {
            throw new McpToolExecutionException('action must be one of: '.implode(', ', array_keys(self::ACTIONS)).'.');
        }

        return ControllerBridge::call($user, ScheduleEventController::class, $action, [], 'POST', ['id' => (int) ($arguments['event_id'] ?? 0)]);
    }
}
