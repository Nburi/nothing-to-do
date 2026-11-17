<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Reminders;

class DeleteReminderTool extends McpTool
{
    public function name(): string
    {
        return 'delete_reminder';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a reminder. Irreversible. Pass confirm_title matching its exact current title '
            .'(call list_reminders first) and confirm with the user before calling. If it is merely done, '
            .'update_reminder with acknowledged: true is the gentler choice.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the reminder\'s current title.'],
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
        $reminder = $user->reminders()->find((int) ($arguments['id'] ?? 0));

        if ($reminder === null) {
            throw new McpToolExecutionException('No reminder with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        if (! is_string($arguments['confirm_title'] ?? null) || $arguments['confirm_title'] !== $reminder->title) {
            throw new McpToolExecutionException(
                "confirm_title did not match. This reminder's current title is: \"{$reminder->title}\". Pass that exact string after checking with the user."
            );
        }

        Reminders::delete($reminder);

        return ['deleted' => true, 'id' => (int) $arguments['id']];
    }
}
