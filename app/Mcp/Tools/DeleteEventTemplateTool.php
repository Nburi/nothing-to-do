<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class DeleteEventTemplateTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'delete_event_template';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a template. For a recurring weekly-plan block this removes the WHOLE SERIES: all '
            .'its occurrences vanish from the Zeitplan too (entries placed by hand from a template stay). Pass '
            .'confirm_name matching the template\'s exact name (list_event_templates). Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the template\'s current name.'],
            ],
            'required' => ['id', 'confirm_name'],
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
        $template = $user->eventTemplates()->find((int) ($arguments['id'] ?? 0));

        if ($template === null) {
            throw new McpToolExecutionException('No template with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        $name = $template->displayName();

        if (($arguments['confirm_name'] ?? null) !== $name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This template's current name is: \"{$name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $id = $template->id;
        $template->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $name];
    }
}
