<?php

namespace App\Mcp\Tools;

use App\Http\Resources\PlanningPayload;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Planning\Suggestions;

/** Refactor step 1.5: the one suggested task, with where to start and why (R1). */
class GetSuggestionTool extends McpTool
{
    public function name(): string
    {
        return 'get_suggestion';
    }

    public function description(): string
    {
        return 'The one task the app suggests starting now, with "start_with" (first step) and "why". '
            .'Only inside a pomodoro block of the week plan; outside, status is "outside_blocks" and '
            .'next_block_at says when work time begins. Ordered by deadlines, size and tonight\'s battery.';
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
        return PlanningPayload::suggestion((new Suggestions)->current($user));
    }
}
