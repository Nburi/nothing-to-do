<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class UpdateScheduleEventTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'update_schedule_event';
    }

    public function description(): string
    {
        return 'Change one Zeitplan entry: move it (date/start_time/end_time), retitle or recolour an appointment, '
            .'switch its category (category_id; null turns it into an appointment), set travel/buffer minutes, '
            .'replace its linked tasks (linked_task_ids, [] clears) or its attribute values. Only the fields you '
            .'pass change. Moving one occurrence of a weekly series to another day detaches it from the series; '
            .'to change the series itself use update_week_plan_block.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'category_id' => ['type' => ['integer', 'null']],
                'title' => ['type' => 'string'],
                'color' => self::colorSchema(),
                'date' => ['type' => 'string', 'format' => 'date'],
                'start_time' => self::timeSchema('Start'),
                'end_time' => self::timeSchema('End (after start)'),
                'buffer_before' => self::bufferSchema('Travel/prep time before the start'),
                'buffer_after' => self::bufferSchema('Travel/wind-down time after the end'),
                'linked_task_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'attribute_values' => ['type' => 'object', 'description' => 'Map of category attribute id → value; replaces all values (omit an id to clear it).'],
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

        return ControllerBridge::call($user, ScheduleEventController::class, 'update', $arguments, 'PATCH', ['id' => $id]);
    }
}
