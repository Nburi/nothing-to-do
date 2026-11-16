<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Validation\Rule;

/** Field schema and identity resolution shared by create_/update_week_plan_block (mirrors WeekPlan::saveEventForm). */
trait WeekPlanBlockFields
{
    use ScheduleToolDefaults;

    /** @return array<string, mixed> */
    protected static function blockProperties(): array
    {
        return [
            'kind' => ['type' => 'string', 'enum' => ['appointment', 'category']],
            'category_id' => ['type' => 'integer', 'description' => 'For kind="category" (see list_categories).'],
            'title' => ['type' => 'string', 'description' => 'For kind="appointment", max 255.'],
            'color' => self::colorSchema(),
            'start_time' => self::timeSchema('Start'),
            'end_time' => self::timeSchema('End (after start)'),
            'days' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7], 'description' => 'Weekdays, 1=Monday..7=Sunday.'],
            'buffer_before' => self::bufferSchema('Travel/prep time before the start'),
            'buffer_after' => self::bufferSchema('Travel/wind-down time after the end'),
        ];
    }

    /**
     * Rules for a (possibly partial) block payload.
     *
     * @return array<string, mixed>
     */
    protected static function blockRules(User $user, bool $partial, string $kind): array
    {
        $req = $partial ? 'sometimes' : 'required';

        $rules = [
            'kind' => [$req, 'in:appointment,category'],
            'start_time' => [$req, 'date_format:H:i'],
            'end_time' => [$req, 'date_format:H:i'],
            'days' => [$req, 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'buffer_before' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'buffer_after' => ['sometimes', 'integer', 'min:0', 'max:240'],
        ];

        if ($kind === 'category') {
            $rules['category_id'] = [$req, Rule::exists('event_categories', 'id')->where('user_id', $user->id)];
        } else {
            $rules['title'] = [$req, 'string', 'max:255'];
            $rules['color'] = [$req, Rule::in(ScheduleEvent::EVENT_COLORS)];
        }

        return $rules;
    }
}
