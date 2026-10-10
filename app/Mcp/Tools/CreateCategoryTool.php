<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventCategoryController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class CreateCategoryTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'create_category';
    }

    public function description(): string
    {
        return 'Create a Zeitplan category (e.g. "Training", "Lernen") — a reusable kind of block with a name and '
            .'colour, optionally with a Pomodoro focus timer. Give it custom fields with create_category_attribute '
            .'and a task source for its focus sessions with set_category_task_link.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Max 255 characters.'],
                'color' => self::colorSchema(),
                'pomodoro_enabled' => ['type' => 'boolean', 'description' => 'Run a Pomodoro timer during blocks of this category.'],
            ],
            'required' => ['name', 'color'],
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
        return ControllerBridge::call($user, EventCategoryController::class, 'store', $arguments, 'POST');
    }
}
