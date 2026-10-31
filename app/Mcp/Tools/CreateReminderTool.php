<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ReminderResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Reminders;

class CreateReminderTool extends McpTool
{
    public function name(): string
    {
        return 'create_reminder';
    }

    public function description(): string
    {
        return 'Add a "Nicht vergessen" reminder for a day (today … one year ahead): "bring the gym shoes on Wednesday". '
            .'Not a task — it sits in no list and counts for no goal. From two days before it shows as a chip above the '
            .'Zeitstrahl; unless switched off it also pushes on the evening before and on the day itself (only to devices '
            .'that enabled push). Use create_task for something to actually work on.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Required, max '.Reminders::TITLE_MAX.'.'],
                'remind_on' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD, today or later.'],
                'notify_evening_before' => ['type' => 'boolean', 'default' => true],
                'notify_on_day' => ['type' => 'boolean', 'default' => true],
            ],
            'required' => ['title', 'remind_on'],
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
        $reminder = Reminders::create($user, array_intersect_key($arguments, array_flip(['title', 'remind_on', 'notify_evening_before', 'notify_on_day'])));

        return (new ReminderResource($reminder))->resolve();
    }
}
