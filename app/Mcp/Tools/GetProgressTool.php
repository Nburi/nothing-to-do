<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;
use App\Services\ProgressStats;

class GetProgressTool extends McpTool
{
    public function name(): string
    {
        return 'get_progress';
    }

    public function description(): string
    {
        return 'This user\'s progress: current/best streak of "perfect" today-lists, lifetime perfect-day '
            .'rate, today\'s completed count vs. their daily goal, best single-day count, and the last two '
            .'weeks of the completion heatmap.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredModule(): ?string
    {
        return 'progress';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $counts = ProgressStats::completedCountsByDay($user);
        $todayStats = ProgressStats::todayListStatsByDay($user);
        $successMap = ProgressStats::dailySuccessMap($todayStats);
        $streak = ProgressStats::currentStreak($user, $successMap);

        // Last 2 weeks only, not the full 12-week heatmap — a compact,
        // conversational summary rather than a wall of 84 daily cells.
        $recentHeatmap = array_slice(ProgressStats::heatmap($user, $counts, weeks: 2), -14);

        return [
            'today_count' => ProgressStats::todayCount($user, $counts),
            'daily_goal' => $user->dailyTaskGoal(),
            'current_streak' => $streak,
            'best_streak' => ProgressStats::bestStreak($successMap),
            'streak_tier' => ProgressStats::streakTier($streak),
            'perfect_days_count' => ProgressStats::perfectDaysCount($successMap),
            'perfect_day_rate_percent' => ProgressStats::perfectDayRate($successMap),
            'best_single_day_count' => ProgressStats::bestDailyCount($counts),
            'recent_days' => array_map(
                fn (array $day) => ['date' => $day['date'], 'completed_count' => $day['count']],
                $recentHeatmap,
            ),
        ];
    }
}
