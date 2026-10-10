<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\EventTemplateController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class ApplyEventTemplateTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'apply_event_template';
    }

    public function description(): string
    {
        return 'Place a template (see list_event_templates) on a day as an independent Zeitplan entry — with its '
            .'duration and travel/buffer minutes. start_time defaults to the template\'s own start.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'template_id' => ['type' => 'integer'],
                'date' => ['type' => 'string', 'format' => 'date'],
                'start_time' => self::timeSchema('Start (optional)'),
            ],
            'required' => ['template_id', 'date'],
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
        $id = (int) ($arguments['template_id'] ?? 0);
        unset($arguments['template_id']);

        return ControllerBridge::call($user, EventTemplateController::class, 'apply', $arguments, 'POST', ['id' => $id]);
    }
}
