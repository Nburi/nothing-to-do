<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventCategoryController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class ClearCategoryTaskLinkTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'clear_category_task_link';
    }

    public function description(): string
    {
        return 'Remove a category\'s task link, so its focus sessions fall back to the generic suggestions. '
            .'Nothing is deleted — only the link (pinned tasks are unpinned, the tasks themselves stay).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['category_id' => ['type' => 'integer']], 'required' => ['category_id']];
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
        return ControllerBridge::call($user, EventCategoryController::class, 'clearTaskLink', [], 'DELETE', ['id' => (int) ($arguments['category_id'] ?? 0)]);
    }
}
