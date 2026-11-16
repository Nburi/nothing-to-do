<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ScheduleEventController;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ListScheduleEventsTool extends McpTool
{
    use ScheduleToolDefaults;

    private const MAX_DAYS = 62;

    public function name(): string
    {
        return 'list_schedule_events';
    }

    public function description(): string
    {
        return 'List the Zeitplan entries (appointments and category blocks) of one day or a date range, '
            .'including recurring occurrences of the weekly plan, linked tasks, attribute values and travel/'
            .'buffer minutes. Defaults to today. Paused days show no weekly-plan blocks. Max '.self::MAX_DAYS.' days.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'One day (YYYY-MM-DD).'],
                'start' => ['type' => 'string', 'format' => 'date', 'description' => 'First day of a range.'],
                'end' => ['type' => 'string', 'format' => 'date', 'description' => 'Last day of a range (on or after start).'],
            ],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'date' => ['sometimes', 'date'],
            'start' => ['sometimes', 'date'],
            'end' => ['sometimes', 'date', 'after_or_equal:start'],
        ])->validate();

        if (isset($data['start']) && isset($data['end'])
            && (int) abs(Carbon::parse($data['start'])->diffInDays(Carbon::parse($data['end']))) > self::MAX_DAYS) {
            throw new McpToolExecutionException('Pick a range of at most '.self::MAX_DAYS.' days.');
        }

        $events = ControllerBridge::call($user, ScheduleEventController::class, 'index', $data);

        return ['count' => count($events), 'events' => $events];
    }
}
