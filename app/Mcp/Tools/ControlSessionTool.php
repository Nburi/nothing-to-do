<?php

namespace App\Mcp\Tools;

use App\Http\Resources\PlanningPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\FocusSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Refactor step 1.5: start, finish, pause, resume or stop the pomodoro session (R9). */
class ControlSessionTool extends McpTool
{
    public function name(): string
    {
        return 'control_session';
    }

    public function description(): string
    {
        return 'Control the pomodoro session. start (task_id, or none to continue the carried-over task), '
            .'done (task_id of the running task: completes it; the next suggested task starts inside the '
            .'session with at least 5 minutes left, otherwise the break starts), pause, resume, stop.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['start', 'done', 'pause', 'resume', 'stop']],
                'task_id' => ['type' => 'integer'],
            ],
            'required' => ['action'],
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
        $data = Validator::make($arguments, [
            'action' => ['required', Rule::in(['start', 'done', 'pause', 'resume', 'stop'])],
            'task_id' => ['required_if:action,done', 'nullable', 'integer'],
        ])->validate();

        $sessions = new FocusSession;
        $now = CarbonImmutable::now();

        $state = match ($data['action']) {
            'start' => $sessions->start($user, $data['task_id'] ?? null, $now),
            'done' => $sessions->done($user, (int) $data['task_id'], $now),
            'pause' => $sessions->pause($user, $now),
            'resume' => $sessions->resume($user, $now),
            'stop' => $sessions->stop($user, $now),
        };

        return PlanningPayload::session($state, $sessions->remainingSeconds($user, $now), $now);
    }
}
