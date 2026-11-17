<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\WeekPlanBlockFields;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\WeekPlanTemplates;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateWeekPlanBlockTool extends McpTool
{
    use WeekPlanBlockFields;

    public function name(): string
    {
        return 'create_week_plan_block';
    }

    public function description(): string
    {
        return 'Add a recurring block to the weekly plan (Wochenplan) — the part of the week that is the same '
            .'every week (school, training). It shows up in the Zeitplan of every matching weekday. Same fields as '
            .'create_schedule_event with recurring=true, but without a date. Paused days (see pause_schedule) stay empty.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => self::blockProperties(),
            'required' => ['kind', 'start_time', 'end_time', 'days'],
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
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, self::blockRules($user, false, $arguments['kind'] ?? 'appointment'))->validate();

        $duration = ScheduleEvent::toMinutes($data['end_time']) - ScheduleEvent::toMinutes($data['start_time']);

        if ($duration <= 0) {
            throw ValidationException::withMessages(['end_time' => 'end_time must be after start_time.']);
        }

        if ($data['kind'] === 'category') {
            $category = $user->eventCategories()->findOrFail($data['category_id']);
            $categoryId = $category->id;
            $title = $category->name;
            $color = $category->color;
        } else {
            $categoryId = null;
            $title = trim($data['title']);
            $color = $data['color'];
        }

        /** @var EventTemplate $template */
        $template = $user->eventTemplates()->create([
            'category_id' => $categoryId,
            'name' => $title,
            'color' => $color,
            'duration' => $duration,
            'buffer_before' => $data['buffer_before'] ?? 0,
            'buffer_after' => $data['buffer_after'] ?? 0,
            'default_start' => $data['start_time'],
            'is_recurring' => true,
            'recurrence' => implode(',', collect($data['days'])->unique()->sort()->values()->all()),
        ]);

        WeekPlanTemplates::refreshOccurrences($user, $template);

        return $this->present($template->fresh());
    }

    /** @return array<string, mixed> */
    public static function present(EventTemplate $template): array
    {
        return [
            'id' => $template->id,
            'category_id' => $template->category_id,
            'name' => $template->displayName(),
            'color' => $template->colorToken(),
            'default_start' => $template->default_start,
            'duration' => $template->duration,
            'buffer_before' => (int) $template->buffer_before,
            'buffer_after' => (int) $template->buffer_after,
            'is_recurring' => (bool) $template->is_recurring,
            'recurrence_days' => $template->recurrenceDays(),
        ];
    }
}
