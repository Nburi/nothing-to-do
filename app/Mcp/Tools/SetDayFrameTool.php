<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\DayWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetDayFrameTool extends McpTool
{
    public function name(): string
    {
        return 'set_day_frame';
    }

    public function description(): string
    {
        return 'Set the Tagesrahmen (visible length of a day in the Zeitplan). scope="default" is the account-wide '
            .'frame, scope="weekday" overrides one weekday (weekday 1=Monday..7=Sunday), scope="date" overrides '
            .'one concrete day. Pass start+end (HH:MM, at least '.intdiv(DayWindow::MIN_SPAN, 60).' hours apart), '
            .'or reset=true to remove a weekday/date override (the default cannot be reset, only changed). '
            .'Blocks outside the frame are never hidden — the grid widens to show them.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scope' => ['type' => 'string', 'enum' => ['default', 'weekday', 'date']],
                'weekday' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7, 'description' => 'For scope="weekday".'],
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'For scope="date".'],
                'start' => ['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d$', 'description' => 'Day starts, HH:MM.'],
                'end' => ['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d$', 'description' => 'Day ends, HH:MM.'],
                'reset' => ['type' => 'boolean', 'description' => 'Remove the weekday/date override instead of setting one.'],
            ],
            'required' => ['scope'],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'schedule';
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
            'scope' => ['required', 'in:default,weekday,date'],
            'weekday' => ['required_if:scope,weekday', 'nullable', 'integer', 'between:1,7'],
            'date' => ['required_if:scope,date', 'nullable', 'date'],
            'start' => ['nullable', 'date_format:H:i'],
            'end' => ['nullable', 'date_format:H:i'],
            'reset' => ['sometimes', 'boolean'],
        ])->validate();

        $scope = $data['scope'];

        if ($data['reset'] ?? false) {
            if ($scope === 'default') {
                throw new McpToolExecutionException('The account default cannot be reset — set a new start and end instead.');
            }

            $scope === 'weekday'
                ? DayWindow::clearWeekday($user, (int) $data['weekday'])
                : DayWindow::clearDate($user, Carbon::parse($data['date']));

            return ['reset' => true, 'scope' => $scope];
        }

        if (empty($data['start']) || empty($data['end'])) {
            throw ValidationException::withMessages(['start' => 'start and end are required unless reset=true.']);
        }

        $start = DayWindow::toMinutes($data['start'], -1);
        $end = DayWindow::toMinutes($data['end'], -1);

        if ($end <= $start) {
            throw ValidationException::withMessages(['end' => 'end must be after start.']);
        }
        if ($end - $start < DayWindow::MIN_SPAN) {
            throw ValidationException::withMessages(['end' => 'A day must be at least '.intdiv(DayWindow::MIN_SPAN, 60).' hours long.']);
        }
        if ($start < DayWindow::EARLIEST_START || $end > DayWindow::LATEST_END) {
            throw ValidationException::withMessages([
                'start' => 'The frame must lie between '.DayWindow::format(DayWindow::EARLIEST_START).' and '.DayWindow::format(DayWindow::LATEST_END).'.',
            ]);
        }

        match ($scope) {
            'default' => DayWindow::setDefault($user, $data['start'], $data['end']),
            'weekday' => DayWindow::setWeekday($user, (int) $data['weekday'], $data['start'], $data['end']),
            'date' => DayWindow::setDate($user, Carbon::parse($data['date']), $data['start'], $data['end']),
        };

        return ['saved' => true, 'scope' => $scope, 'start' => $data['start'], 'end' => $data['end']];
    }
}
