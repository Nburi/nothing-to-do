<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventCategoryController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class UpdateCategoryTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'update_category';
    }

    public function description(): string
    {
        return 'Rename or recolour a category, or switch its Pomodoro timer on/off. Name and colour are read live '
            .'by every block of the category, past and future, so they repaint immediately.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'color' => self::colorSchema(),
                'pomodoro_enabled' => ['type' => 'boolean'],
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

        return ControllerBridge::call($user, EventCategoryController::class, 'update', $arguments, 'PATCH', ['id' => $id]);
    }
}
