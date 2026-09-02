<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;
use App\Services\AppModules;
use App\Services\ListConcepts;

class GetSettingsTool extends McpTool
{
    public function name(): string
    {
        return 'get_settings';
    }

    public function description(): string
    {
        return 'This user\'s app-level settings: active list concept, which optional feature modules are '
            .'visible, the default landing page, and (only when the relevant module is visible) the daily '
            .'task goal and Pomodoro rhythm.';
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
        $settings = [
            'list_concept' => ListConcepts::for($user),
            'available_list_concepts' => array_values(array_filter(
                array_map(fn ($row) => $row['available'] ? $row['key'] : null, ListConcepts::rowsFor($user)),
            )),
            'visible_modules' => array_values(array_filter(
                array_map(fn ($row) => $row['hidden'] ? null : $row['key'], AppModules::rowsFor($user)),
            )),
            'hidden_modules' => AppModules::hiddenKeys($user),
            'default_page' => $user->default_page,
        ];

        if (AppModules::isVisible($user, 'progress')) {
            $settings['daily_task_goal'] = $user->dailyTaskGoal();
        }

        if (AppModules::isVisible($user, 'schedule')) {
            $settings['pomodoro_rhythm'] = $user->pomodoro();
            $settings['pomodoro_autostart'] = (bool) $user->pomodoro_autostart;
        }

        return $settings;
    }
}
