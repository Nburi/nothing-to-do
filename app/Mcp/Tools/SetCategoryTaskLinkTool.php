<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventCategoryController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class SetCategoryTaskLinkTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'set_category_task_link';
    }

    public function description(): string
    {
        return 'Point a Pomodoro-enabled category at what its focus sessions should suggest working on. '
            .'source: "tasks" (pinned tasks via task_ids, in order) or "text" (a free label). '
            .'Replaces any previous link. Remove it with clear_category_task_link.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer'],
                'source' => ['type' => 'string', 'enum' => ['tasks', 'text']],
                'task_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'For source="tasks", in suggestion order.'],
                'text' => ['type' => 'string', 'description' => 'For source="text", max 255.'],
            ],
            'required' => ['category_id', 'source'],
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
        $id = (int) ($arguments['category_id'] ?? 0);
        unset($arguments['category_id']);

        return ControllerBridge::call($user, EventCategoryController::class, 'setTaskLink', $arguments, 'PUT', ['id' => $id]);
    }
}
