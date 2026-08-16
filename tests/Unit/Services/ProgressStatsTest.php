<?php

namespace Tests\Unit\Services;

use App\Models\Task;
use App\Models\User;
use App\Services\ProgressStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgressStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function completedOn(User $user, string $localDate, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            Task::factory()->for($user)->completed()->create([
                'completed_at' => $localDate.' 12:00:00',
            ]);
        }
    }

    public function test_today_count_only_counts_todays_completions(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->completedOn($user, '2026-08-16', 2);
        $this->completedOn($user, '2026-08-15', 5);

        $this->assertSame(2, ProgressStats::todayCount($user));
    }

    public function test_current_streak_counts_consecutive_days_through_today(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->completedOn($user, '2026-08-14');
        $this->completedOn($user, '2026-08-15');
        $this->completedOn($user, '2026-08-16');

        $this->assertSame(3, ProgressStats::currentStreak($user));
    }

    public function test_current_streak_still_counts_through_yesterday_when_today_is_empty_so_far(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->completedOn($user, '2026-08-14');
        $this->completedOn($user, '2026-08-15');
        // Nothing completed yet today — streak is "at risk", not broken.

        $this->assertSame(2, ProgressStats::currentStreak($user));
    }

    public function test_current_streak_resets_to_zero_after_a_gap(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->completedOn($user, '2026-08-10');
        // Nothing on the 14th/15th, nothing today.

        $this->assertSame(0, ProgressStats::currentStreak($user));
    }

    public function test_best_streak_finds_the_longest_historical_run_even_if_shorter_now(): void
    {
        $counts = [
            '2026-08-01' => 1, '2026-08-02' => 1, '2026-08-03' => 1, '2026-08-04' => 1,
            '2026-08-10' => 1, '2026-08-11' => 1,
        ];

        $this->assertSame(4, ProgressStats::bestStreak($counts));
    }

    public function test_best_streak_is_zero_with_no_completions(): void
    {
        $this->assertSame(0, ProgressStats::bestStreak([]));
    }

    public function test_best_daily_count_can_exclude_a_date(): void
    {
        $counts = ['2026-08-14' => 3, '2026-08-15' => 9, '2026-08-16' => 4];

        $this->assertSame(9, ProgressStats::bestDailyCount($counts));
        $this->assertSame(4, ProgressStats::bestDailyCount($counts, excluding: '2026-08-15'));
    }

    public function test_heatmap_spans_full_weeks_and_flags_today_and_future_days(): void
    {
        Carbon::setTestNow('2026-08-16 12:00:00'); // a Sunday
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 4]);

        $this->completedOn($user, '2026-08-16', 5); // above goal -> top level

        $counts = ProgressStats::completedCountsByDay($user);
        $days = ProgressStats::heatmap($user, $counts, weeks: 2);

        $this->assertCount(14, $days);
        $this->assertSame('2026-08-16', $days[13]['date']); // last cell is today
        $this->assertTrue($days[13]['isToday']);
        $this->assertFalse($days[13]['isFuture']);
        $this->assertSame(4, $days[13]['level']); // 5 > goal(4) -> max level
        $this->assertSame(0, $days[0]['level']); // nothing completed two weeks back
    }

    public function test_celebration_fires_once_when_crossing_the_daily_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);

        $this->completedOn($user, '2026-08-16', 2); // today already has 2

        $celebration = ProgressStats::celebrationFor($user, beforeCount: 2);

        $this->assertSame('goal', $celebration['kind']);
    }

    public function test_celebration_does_not_fire_before_reaching_the_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);

        $this->assertNull(ProgressStats::celebrationFor($user, beforeCount: 1));
    }

    public function test_celebration_does_not_refire_after_the_goal_was_already_crossed_today(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);

        $this->assertNull(ProgressStats::celebrationFor($user, beforeCount: 4));
    }

    public function test_celebration_fires_a_record_when_beating_the_all_time_best(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal out of reach

        $this->completedOn($user, '2026-08-10', 4); // previous best: 4
        $this->completedOn($user, '2026-08-16', 4); // today already tied the best

        $celebration = ProgressStats::celebrationFor($user, beforeCount: 4);

        $this->assertSame('record', $celebration['kind']);
        $this->assertSame('Neuer Bestwert: 5', $celebration['label']);
    }

    public function test_a_record_wins_over_a_simultaneous_goal_crossing(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);

        $this->completedOn($user, '2026-08-10', 4); // previous best: 4
        $this->completedOn($user, '2026-08-16', 4); // today: also 4, about to become 5

        $celebration = ProgressStats::celebrationFor($user, beforeCount: 4);

        $this->assertSame('record', $celebration['kind']);
    }

    public function test_the_very_first_completion_ever_is_not_celebrated_as_a_record(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        $this->assertNull(ProgressStats::celebrationFor($user, beforeCount: 0));
    }
}
