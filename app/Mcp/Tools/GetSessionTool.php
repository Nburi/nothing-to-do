<?php

namespace App\Mcp\Tools;

use App\Http\Resources\PlanningPayload;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\FocusSession;
use Carbon\CarbonImmutable;

/** Refactor step 1.5: the running pomodoro session, the same one every device shows (R1, R9). */
class GetSessionTool extends McpTool
{
    public function name(): string
    {
        return 'get_session';
    }

    public function description(): string
    {
        return 'The user\'s pomodoro session: phase (idle, work, short_break, long_break, ready), the task, '
            .'remaining seconds, and how many sessions are done today (25 min work, 5 min break, 15 after every fourth).';
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
        $sessions = new FocusSession;
        $now = CarbonImmutable::now();

        return PlanningPayload::session($sessions->current($user, $now), $sessions->remainingSeconds($user, $now), $now);
    }
}
