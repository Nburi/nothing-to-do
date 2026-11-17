<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\SchedulePause;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PauseScheduleTool extends McpTool
{
    public function name(): string
    {
        return 'pause_schedule';
    }

    public function description(): string
    {
        return 'Switch the weekly plan off for a date range (Ferien, krank, …): recurring blocks no longer appear '
            .'on those days in the Zeitplan, the focus strip or notifications. Nothing is deleted — undo it with '
            .'unpause_schedule. Entries placed by hand stay. Max one year.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'format' => 'date'],
                'to' => ['type' => 'string', 'format' => 'date', 'description' => 'On or after from; same day for a single day.'],
                'note' => ['type' => 'string', 'description' => 'Optional label, e.g. "Sommerferien".'],
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
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $start = Carbon::parse($data['from']);
        $end = Carbon::parse($data['to']);

        // A fat-fingered end date shouldn't silently create years of pause rows.
        if ((int) $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => 'The range may span at most one year.']);
        }

        SchedulePause::pauseRange($user, $start, $end, ($data['note'] ?? null) ?: null);

        return ['paused' => true, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => (int) $start->diffInDays($end) + 1];
    }
}
