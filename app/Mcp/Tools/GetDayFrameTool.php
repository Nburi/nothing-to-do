<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;
use App\Services\DayWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class GetDayFrameTool extends McpTool
{
    public function name(): string
    {
        return 'get_day_frame';
    }

    public function description(): string
    {
        return 'The Tagesrahmen: from when to when a day lasts in the Zeitplan ("Ich stehe auf um" / "Ich gehe ins '
            .'Bett um"). Returns the account default, the setting for each weekday (source "default" or "weekday") '
            .'and — if you pass date — the effective frame of that concrete day (source "date", "weekday" or "default").';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['date' => ['type' => 'string', 'format' => 'date']],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'schedule';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, ['date' => ['sometimes', 'date']])->validate();

        $row = fn (array $s) => [
            'start' => DayWindow::format($s['start']),
            'end' => DayWindow::format($s['end']),
            'source' => $s['source'] ?? 'default',
        ];

        $weekdays = [];
        foreach (range(1, 7) as $weekday) {
            $weekdays[$weekday] = $row(DayWindow::settingForWeekday($user, $weekday));
        }

        $result = [
            'default' => $row(DayWindow::defaultSetting($user)),
            'weekdays' => $weekdays,
        ];

        if (isset($data['date'])) {
            $date = Carbon::parse($data['date']);
            $result['date'] = ['date' => $date->toDateString()] + $row(DayWindow::settingForDate($user, $date));
        }

        return $result;
    }
}
