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
        $outcomeMap = ProgressStats::dailyOutcomeMap($user, $todayStats, $counts);
        $streak = ProgressStats::currentStreak($user, $outcomeMap);

        // Last 2 weeks only, not the full 12-week heatmap — a compact,
        // conversational summary rather than a wall of 84 daily cells.
        $recentHeatmap = array_slice(ProgressStats::heatmap($user, $counts, weeks: 2, outcomeMap: $outcomeMap), -14);

        return [
            'today_count' => ProgressStats::todayCount($user, $counts),
            'daily_goal' => $user->dailyTaskGoal(),
            'current_streak' => $streak,
            'best_streak' => ProgressStats::bestStreak($outcomeMap),
            'streak_tier' => ProgressStats::streakTier($streak),
            'perfect_days_count' => ProgressStats::perfectDaysCount($outcomeMap),
            'perfect_day_rate_percent' => ProgressStats::perfectDayRate($outcomeMap),
            'best_single_day_count' => ProgressStats::bestDailyCount($counts),
            'freezes_used_this_week' => ProgressStats::freezesUsedInTrailingWeek($user, $user->localToday()->addDay()),
            'max_freezes_per_week' => ProgressStats::MAX_FREEZES_PER_WEEK,
            'recent_days' => array_map(
                fn (array $day) => [
                    'date' => $day['date'],
                    'completed_count' => $day['count'],
                    'is_streak_day' => $day['isStreakDay'],
                    'is_frozen' => $day['isFrozen'],
                ],
                $recentHeatmap,
            ),
        ];
    }
}
