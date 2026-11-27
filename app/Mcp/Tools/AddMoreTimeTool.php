<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ScheduleEventResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Planning\CrunchDecisions;
use Illuminate\Support\Facades\Validator;

/** The crunch screen's "Mehr Zeit" (api parity): one more block of a pomodoro category within 14 days. */
class AddMoreTimeTool extends McpTool
{
    public function name(): string
    {
        return 'add_more_time';
    }

    public function description(): string
    {
        return 'Add one more work block, like "Mehr Zeit" on the crunch screen: a one-off block of a category with pomodoro sessions, within the next '.CrunchDecisions::MORE_TIME_DAYS.' days. Undo with delete_schedule_event. get_forecast shows where time is missing.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date'],
                'start_time' => ['type' => 'string', 'description' => 'HH:MM'],
                'end_time' => ['type' => 'string', 'description' => 'HH:MM, after start_time.'],
                'category_id' => ['type' => 'integer', 'description' => 'A category with pomodoro sessions (list_categories).'],
            ],
            'required' => ['date', 'start_time', 'end_time', 'category_id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function requiredModule(): ?string
    {
        return 'weekplan';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'category_id' => ['required', 'integer'],
        ])->validate();

        $event = app(CrunchDecisions::class)->addTime($user, $data['date'], $data['start_time'], $data['end_time'], (int) $data['category_id']);

        return (new ScheduleEventResource($event->load('category')))->resolve();
    }
}
