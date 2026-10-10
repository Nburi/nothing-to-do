<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\HeaderBadges;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * One tool for the plain "Einstellungen" values that have a Settings toggle or field
 * of their own. Rules mirror the Livewire Settings component field by field (and the
 * Planer / list concept / modules / landing page keep their dedicated tools). The
 * Tagesrahmen has set_day_frame. Only the keys you pass are touched.
 */
class UpdateSettingsTool extends McpTool
{
    private const TIME = ['date_format:H:i'];

    /** @return array<string, array{rules: array<int, mixed>, schema: array<string, mixed>}> */
    private static function fields(): array
    {
        return [
            // Fortschritt
            'daily_task_goal' => ['rules' => ['integer', 'min:1', 'max:30'], 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'description' => 'Tasks per day the ring and the streak aim for.']],
            'notify_daily_reminder' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Evening push when "Heute" tasks are still open.']],
            'daily_reminder_time' => ['rules' => self::TIME, 'schema' => ['type' => 'string', 'description' => 'HH:MM of that reminder.']],
            'notify_streak_risk' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Push at 21:00 when a streak is at risk.']],
            // Tagesüberblick
            'notify_day_preview' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Daily morning push summarising the day.']],
            'day_preview_notification_time' => ['rules' => self::TIME, 'schema' => ['type' => 'string', 'description' => 'HH:MM of that push.']],
            // Pomodoro
            'pomodoro_work' => ['rules' => ['integer', 'min:1'], 'schema' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Work phase, minutes.']],
            'pomodoro_short_break' => ['rules' => ['integer', 'min:1'], 'schema' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Short break, minutes.']],
            'pomodoro_long_break' => ['rules' => ['integer', 'min:1'], 'schema' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Long break, minutes.']],
            'pomodoro_long_every' => ['rules' => ['integer', 'min:1'], 'schema' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A long break after this many work sessions.']],
            'pomodoro_autostart' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Continue into the next phase by itself (the first session always needs a manual start).']],
            // Benachrichtigungen (Zeitplan)
            'notify_event_start' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Push when a Zeitplan entry starts.']],
            'notify_event_upcoming' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Push 5 minutes (plus travel time) before an entry starts.']],
            'notify_pomo_start' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Push when a Pomodoro work phase starts.']],
            'notify_break_start' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Push when a break starts.']],
            // Zeit
            'timezone_offset' => ['rules' => ['numeric', 'between:-12,14'], 'schema' => ['type' => 'number', 'minimum' => -12, 'maximum' => 14, 'description' => 'UTC offset in hours of the user\'s standard time, e.g. 1 for Swiss winter time.']],
            'timezone_auto_dst' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Add one hour automatically while European summer time is active.']],
            'task_reset_time' => ['rules' => self::TIME, 'schema' => ['type' => 'string', 'description' => 'HH:MM after which finished tasks disappear from the board.']],
            // Vorbereitung
            'prepare_time_of_day' => ['rules' => [['in', ['morning', 'evening']]], 'schema' => ['type' => 'string', 'enum' => ['morning', 'evening'], 'description' => 'Whether Vorbereitung plans today (morning) or tomorrow (evening).']],
            'prepare_reminder_mode' => ['rules' => [['in', ['off', 'automatic', 'fixed']]], 'schema' => ['type' => 'string', 'enum' => ['off', 'automatic', 'fixed'], 'description' => 'How Vorbereitung reminds: off, automatic (banner + fallback push) or fixed (push at prepare_reminder_time).']],
            'prepare_reminder_time' => ['rules' => ['nullable', 'date_format:H:i'], 'schema' => ['type' => ['string', 'null'], 'description' => 'HH:MM, used in mode "fixed".']],
            // Vorschauen
            'deadline_preview_enabled' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Zeitplan shows hard deadlines a few days ahead.']],
            'deadline_preview_days' => ['rules' => ['integer', 'min:0', 'max:14'], 'schema' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 14, 'description' => 'How many days ahead.']],
            'homework_preview_enabled' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Board shows the upcoming-homework strip.']],
            // Funktionen
            'planner_enabled' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Switch the Planer on/off. Its tools (get_planner, plan_task, …) appear on the next tool list once on.']],
            'show_presence' => ['rules' => ['boolean'], 'schema' => ['type' => 'boolean', 'description' => 'Classmates see when this user is online. false also stops recording it.']],
        ];
    }

    public function name(): string
    {
        return 'update_settings';
    }

    public function description(): string
    {
        return 'Change account settings. Pass only what should change. Covers: daily_task_goal, reminders and '
            .'notifications (notify_*, *_time), the Pomodoro rhythm (pomodoro_*), timezone (timezone_offset, '
            .'timezone_auto_dst), task_reset_time, Vorbereitung (prepare_*), previews, planner_enabled, show_presence '
            .'and header_badges (ordered list of {key, enabled}; keys: '.implode(', ', array_keys(HeaderBadges::CATALOG)).'). '
            .'Other settings have their own tools: set_list_concept, set_module_visibility, set_default_landing_page, '
            .'set_day_frame. get_settings shows the current values.';
    }

    public function inputSchema(): array
    {
        $properties = array_map(fn (array $f) => $f['schema'], self::fields());

        $properties['header_badges'] = [
            'type' => 'array',
            'description' => 'The header shortcuts, in display order. Listed badges come first in this order; unlisted ones keep their state and follow.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'enum' => array_keys(HeaderBadges::CATALOG)],
                    'enabled' => ['type' => 'boolean'],
                ],
                'required' => ['key', 'enabled'],
            ],
        ];

        return ['type' => 'object', 'properties' => $properties];
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
        $fields = self::fields();
        $unknown = array_diff(array_keys($arguments), [...array_keys($fields), 'header_badges']);

        if ($unknown !== []) {
            throw new McpToolExecutionException('Unknown setting(s): '.implode(', ', $unknown).'. See this tool\'s input schema for what can be changed.');
        }

        if ($arguments === []) {
            throw new McpToolExecutionException('Pass at least one setting to change.');
        }

        $rules = [];
        foreach ($fields as $key => $field) {
            $rules[$key] = ['sometimes', ...array_map(fn ($r) => is_array($r) ? Rule::in($r[1]) : $r, $field['rules'])];
        }
        $rules['header_badges'] = ['sometimes', 'array'];
        $rules['header_badges.*.key'] = ['required', Rule::in(array_keys(HeaderBadges::CATALOG))];
        $rules['header_badges.*.enabled'] = ['required', 'boolean'];

        $data = Validator::make($arguments, $rules)->validate();

        $updates = $data;
        unset($updates['header_badges']);

        if (array_key_exists('show_presence', $updates) && ! $updates['show_presence']) {
            // Opting out stops the recording, not just the display.
            $updates['last_seen_at'] = null;
        }

        if (isset($data['header_badges'])) {
            $current = collect(HeaderBadges::preferenceRowsFor($user))->keyBy('key');

            $rows = collect($data['header_badges'])->unique('key')
                ->map(fn (array $row) => ['key' => $row['key'], 'enabled' => (bool) $row['enabled']])
                ->values()->all();

            foreach ($current as $key => $row) {
                if (! in_array($key, array_column($rows, 'key'), true)) {
                    $rows[] = $row;
                }
            }

            $updates['header_badges'] = $rows;
        }

        $user->update($updates);

        return ['updated' => array_keys($data)];
    }
}
