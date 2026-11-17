<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ReminderResource;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Reminders;

class UpdateReminderTool extends McpTool
{
    public function name(): string
    {
        return 'update_reminder';
    }

    public function description(): string
    {
        return 'Change a reminder: its title, day, or which pushes it gets — or tick it off with acknowledged: true '
            .'("Hab ich": no further pushes) / reopen it with false. Moving it to another day also reopens it and '
            .'re-arms its pushes.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'remind_on' => ['type' => 'string', 'format' => 'date'],
                'notify_evening_before' => ['type' => 'boolean'],
                'notify_on_day' => ['type' => 'boolean'],
                'acknowledged' => ['type' => 'boolean', 'description' => 'true = ticked off ("Hab ich"), false = reopen.'],
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
        $reminder = $user->reminders()->find((int) ($arguments['id'] ?? 0));

        if ($reminder === null) {
            throw new McpToolExecutionException('No reminder with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        $fields = array_intersect_key($arguments, array_flip(['title', 'remind_on', 'notify_evening_before', 'notify_on_day']));

        if ($fields !== []) {
            $reminder = Reminders::update($user, $reminder, $fields);
        }

        if (array_key_exists('acknowledged', $arguments)) {
            $reminder = Reminders::acknowledge($reminder, (bool) $arguments['acknowledged']);
        }

        return (new ReminderResource($reminder))->resolve();
    }
}
