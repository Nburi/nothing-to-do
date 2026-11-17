<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ReminderResource;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Reminders;

class ListRemindersTool extends McpTool
{
    public function name(): string
    {
        return 'list_reminders';
    }

    public function description(): string
    {
        return 'List the "Nicht vergessen" reminders from today on, earliest first — small dated notes ("take the gym '
            .'shoes to school on Wednesday") that are deliberately not tasks. Each shows its day, whether it gets a push '
            .'the evening before / on the day, and whether it was already ticked off ("Hab ich").';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_done' => ['type' => 'boolean', 'default' => true, 'description' => 'false leaves out reminders already ticked off.'],
            ],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $reminders = Reminders::upcoming($user);

        if (($arguments['include_done'] ?? true) === false) {
            $reminders = $reminders->reject(fn ($r) => $r->isAcknowledged())->values();
        }

        return [
            'today' => $user->localToday()->toDateString(),
            'reminders' => $reminders->map(fn ($r) => (new ReminderResource($r))->resolve())->all(),
        ];
    }
}
