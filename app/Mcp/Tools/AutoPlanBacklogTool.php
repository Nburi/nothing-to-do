<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\User;
use App\Services\DayPlanner;
use App\Models\TaskDayPlan;

class AutoPlanBacklogTool extends McpTool
{
    use PlannerToolDefaults;

    public function name(): string
    {
        return 'auto_plan_backlog';
    }

    public function description(): string
    {
        return '"Rest automatisch einplanen": fills the Planer with the backlog by urgency, fit and importance, '
            .'day by day. Purely additive — it only places tasks that have no day yet and never moves or removes '
            .'anything planned by hand. Only days that have capacity (Pomodoro-enabled category blocks in the Zeitplan) are '
            .'filled, and an item only goes where it fits; the rest stays in the backlog. Returns how many were placed.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
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
        $before = DayPlanner::backlog($user)->count();

        DayPlanner::autoFillBacklog($user);

        $after = DayPlanner::backlog($user)->count();

        return ['placed' => max(0, $before - $after), 'still_in_backlog' => $after];
    }
}
