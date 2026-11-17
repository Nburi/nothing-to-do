<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class CreateScheduleEventTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'create_schedule_event';
    }

    public function description(): string
    {
        return 'Add an entry to the Zeitplan. kind="appointment" is free text (title + colour); kind="category" '
            .'is a block of one of the user\'s categories (see list_categories; name and colour follow the '
            .'category). Optional: travel/buffer minutes, tasks to work on (linked_task_ids, in suggestion order) '
            .'and values for the category\'s custom attributes (attribute_values: {"<attribute_id>": value}). '
            .'recurring=true instead creates a weekly series on the given weekdays (days: 1=Monday..7=Sunday) — '
            .'tasks and attribute values cannot be attached to a series. Use update_week_plan_block to edit one later.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['appointment', 'category']],
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD (first/anchor day for a series).'],
                'start_time' => self::timeSchema('Start'),
                'end_time' => self::timeSchema('End (after start)'),
                'title' => ['type' => 'string', 'description' => 'Required for an appointment, max 255.'],
                'color' => self::colorSchema(),
                'category_id' => ['type' => 'integer', 'description' => 'Required for kind="category".'],
                'buffer_before' => self::bufferSchema('Travel/prep time before the start'),
                'buffer_after' => self::bufferSchema('Travel/wind-down time after the end'),
                'linked_task_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Tasks to work on during this entry, in order.'],
                'attribute_values' => ['type' => 'object', 'description' => 'Map of category attribute id → value (text, number, option label or true/false).'],
                'recurring' => ['type' => 'boolean', 'description' => 'true = weekly series.'],
                'days' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7], 'description' => 'Weekdays of the series (default: the weekday of "date").'],
                'save_as_template' => ['type' => 'boolean', 'description' => 'Appointments only: also keep it as a reusable quick-add template.'],
            ],
            'required' => ['kind', 'date', 'start_time', 'end_time'],
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
        return ControllerBridge::call($user, ScheduleEventController::class, 'store', $arguments, 'POST');
    }
}
