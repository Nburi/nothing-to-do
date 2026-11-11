<?php

namespace App\Mcp\Tools;

use App\Http\Resources\PlanningPayload;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Planning\Capacity;
use App\Services\Planning\CrunchWatch;

/** Refactor step 1.4 / 1.5: capacity and load per day, the crunch and the early warning (R5, R10). */
class GetForecastTool extends McpTool
{
    public function name(): string
    {
        return 'get_forecast';
    }

    public function description(): string
    {
        return 'Load forecast for the next two weeks or more: per day the capacity (25 min sessions in '
            .'pomodoro blocks) and the planned deadline work in minutes, which days are overloaded, which '
            .'tasks would be late, and which soft deadlines to let slip first.';
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
        return PlanningPayload::forecast((new Capacity)->forecast($user), (new CrunchWatch)->state($user));
    }
}
