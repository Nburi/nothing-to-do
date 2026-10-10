<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventTemplateController;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class ListEventTemplatesTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'list_event_templates';
    }

    public function description(): string
    {
        return 'List the reusable blueprints behind the Zeitplan: recurring weekly-plan blocks (is_recurring=true, '
            .'with recurrence_days 1=Monday..7=Sunday, default start and duration) and one-off quick-add templates '
            .'(is_recurring=false). Edit a recurring block with update_week_plan_block, place a quick-add template '
            .'with apply_event_template.';
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
        $templates = ControllerBridge::call($user, EventTemplateController::class, 'index');

        return ['templates' => $templates];
    }
}
