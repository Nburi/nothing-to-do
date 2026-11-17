<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UnpauseScheduleTool extends McpTool
{
    public function name(): string
    {
        return 'unpause_schedule';
    }

    public function description(): string
    {
        return 'Switch the weekly plan back on for a date range (or a single day inside a longer pause). The '
            .'recurring blocks reappear immediately. Reverses pause_schedule; max one year.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'format' => 'date'],
                'to' => ['type' => 'string', 'format' => 'date', 'description' => 'On or after from; same day for a single day.'],
            ],
            'required' => ['from', 'to'],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'weekplan';
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
        $data = Validator::make($arguments, [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ])->validate();

        $start = Carbon::parse($data['from'])->startOfDay();
        $end = Carbon::parse($data['to'])->startOfDay();

        if ((int) $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => 'The range may span at most one year.']);
        }

        $removed = $user->schedulePauses()->forRange($start->toDateString(), $end->toDateString())->delete();

        // Bring the normal blocks back now instead of waiting for the next Zeitplan visit.
        ScheduleEvent::materializeRange($user, $start, $end);

        return ['unpaused' => true, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'days_removed' => $removed];
    }
}
