<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class DeleteScheduleEventTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'delete_schedule_event';
    }

    public function description(): string
    {
        return 'Remove one Zeitplan entry. A one-off entry is PERMANENTLY deleted; one occurrence of a weekly '
            .'series is only cancelled for that day (the series stays — use delete_event_template for the series). '
            .'Pass confirm_title matching the entry\'s exact title. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the entry\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $id = (int) ($arguments['id'] ?? 0);
        $event = ControllerBridge::call($user, ScheduleEventController::class, 'show', [], 'GET', ['id' => $id]);

        if (($arguments['confirm_title'] ?? null) !== $event['title']) {
            throw new McpToolExecutionException(
                "confirm_title did not match. This entry's current title is: \"{$event['title']}\". ".
                'Pass that exact string as confirm_title to proceed, after checking with the user.'
            );
        }

        ControllerBridge::call($user, ScheduleEventController::class, 'destroy', [], 'DELETE', ['id' => $id]);

        return ['deleted' => true, 'id' => $id, 'title' => $event['title'], 'was_recurring_occurrence' => $event['is_recurring']];
    }
}
