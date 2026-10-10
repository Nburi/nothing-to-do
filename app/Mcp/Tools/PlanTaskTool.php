<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\User;
use App\Services\DayPlanner;

class PlanTaskTool extends McpTool
{
    use PlannerToolDefaults;

    public function name(): string
    {
        return 'plan_task';
    }

    public function description(): string
    {
        return 'Plan one task (task_id) or one open homework entry (agenda_entry_id — it becomes a real task when '
            .'placed) for a day, appended to the end of that day. A task already planned elsewhere moves. Planning '
            .'for today also flags it "Heute". A task lives on at most one day. For a whole day\'s order use set_day_plan.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'agenda_entry_id' => ['type' => 'integer', 'description' => 'Open homework instead of a task.'],
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'Today … today+'.(DayPlanner::HORIZON_DAYS - 1).' days.'],
            ],
            'required' => ['date'],
        ];
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
        $date = self::horizonDate($user, $arguments['date'] ?? null);
        $token = self::plannerToken($user, $arguments);

        DayPlanner::moveToDay($user, $token, $date);

        return ['planned' => true, 'item' => $token, 'date' => $date];
    }
}
