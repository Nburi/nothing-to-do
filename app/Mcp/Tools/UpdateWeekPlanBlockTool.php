<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\WeekPlanBlockFields;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\WeekPlanTemplates;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateWeekPlanBlockTool extends McpTool
{
    use WeekPlanBlockFields;

    public function name(): string
    {
        return 'update_week_plan_block';
    }

    public function description(): string
    {
        return 'Edit a weekly-plan block (id from list_event_templates, is_recurring=true): retime it, change its '
            .'weekdays, title/colour or category, travel/buffer minutes. The change is also applied to the '
            .'already-planned FUTURE occurrences (not today, not the past). Only the fields you pass change. '
            .'Switching kind needs the matching title+color or category_id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']] + self::blockProperties(),
            'required' => ['id'],
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
        $template = $user->eventTemplates()->find((int) ($arguments['id'] ?? 0));

        if ($template === null) {
            throw new McpToolExecutionException('No template with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        $kind = $arguments['kind'] ?? ($template->category_id !== null ? 'category' : 'appointment');
        $data = Validator::make($arguments, self::blockRules($user, true, $kind))->validate();

        $updates = [];

        if (isset($data['kind'])) {
            if ($kind === 'category') {
                $category = $user->eventCategories()->findOrFail($data['category_id'] ?? throw new McpToolExecutionException('category_id is required when switching to kind="category".'));
                $updates += ['category_id' => $category->id, 'name' => $category->name, 'color' => $category->color];
            } else {
                if (! isset($data['title'], $data['color'])) {
                    throw new McpToolExecutionException('title and color are required when switching to kind="appointment".');
                }
                $updates += ['category_id' => null, 'name' => trim($data['title']), 'color' => $data['color']];
            }
        } elseif ($template->category_id === null) {
            // Still an appointment: title/colour are editable, a category_id would be meaningless.
            if (isset($data['title'])) {
                $updates['name'] = trim($data['title']);
            }
            if (isset($data['color'])) {
                $updates['color'] = $data['color'];
            }
        } elseif (isset($data['category_id'])) {
            $category = $user->eventCategories()->findOrFail($data['category_id']);
            $updates += ['category_id' => $category->id, 'name' => $category->name, 'color' => $category->color];
        }

        $start = $data['start_time'] ?? $template->default_start;
        $end = $data['end_time'] ?? ScheduleEvent::fromMinutes(ScheduleEvent::toMinutes($template->default_start) + $template->duration);
        $duration = ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start);

        if ($duration <= 0) {
            throw ValidationException::withMessages(['end_time' => 'end_time must be after start_time.']);
        }

        if (isset($data['start_time']) || isset($data['end_time'])) {
            $updates['default_start'] = $start;
            $updates['duration'] = $duration;
        }
        if (isset($data['days'])) {
            $updates['recurrence'] = implode(',', collect($data['days'])->unique()->sort()->values()->all());
        }
        foreach (['buffer_before', 'buffer_after'] as $key) {
            if (isset($data[$key])) {
                $updates[$key] = $data[$key];
            }
        }

        $template->update($updates);
        WeekPlanTemplates::refreshOccurrences($user, $template);

        return CreateWeekPlanBlockTool::present($template->fresh());
    }
}
