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

    /** Seeds a today-list for $date: $total tasks flagged today for it, of which $done are completed. */
    private function todayListOn(User $user, string $date, int $total, int $done): void
    {
        for ($i = 0; $i < $total; $i++) {
            Task::factory()->for($user)->todos()->todayOn($date)->create([
                'is_completed' => $i < $done,
                'completed_at' => $i < $done ? $date.' 12:00:00' : null,
            ]);
        }
    }

    // ── todayCount / completedCountsByDay (unaffected by the streak rework) ──

    public function test_today_count_only_counts_todays_completions(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->completedOn($user, '2026-08-16', 2);
        $this->completedOn($user, '2026-08-15', 5);

        $this->assertSame(2, ProgressStats::todayCount($user));
    }

    // ── todayListStatsByDay / dailySuccessMap ────────────────────────────

    public function test_today_list_stats_group_by_day_with_total_and_done(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-16', total: 3, done: 2);

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertSame(['total' => 3, 'done' => 2], $stats['2026-08-16']);
    }

    public function test_a_day_never_flagged_today_is_simply_absent_from_the_stats(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->completedOn($user, '2026-08-16', 3); // completed, but never flagged "today"

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertArrayNotHasKey('2026-08-16', $stats);
    }

    public function test_daily_success_map_is_true_only_when_every_today_task_is_done(): void
    {
        $map = ProgressStats::dailySuccessMap([
            '2026-08-14' => ['total' => 2, 'done' => 2],
            '2026-08-15' => ['total' => 2, 'done' => 1],
        ]);

        $this->assertTrue($map['2026-08-14']);
        $this->assertFalse($map['2026-08-15']);
    }

    // ── currentStreak / bestStreak (now based on "cleared the today-list") ──

    public function test_current_streak_counts_consecutive_perfect_days_through_today(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-14', total: 2, done: 2);
        $this->todayListOn($user, '2026-08-15', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-16', total: 3, done: 3);

        $this->assertSame(3, ProgressStats::currentStreak($user));
    }

    public function test_current_streak_still_counts_through_yesterday_when_today_isnt_cleared_yet(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-14', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-15', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-16', total: 2, done: 0); // today's list exists but is open

        $this->assertSame(2, ProgressStats::currentStreak($user));
    }

    public function test_current_streak_breaks_on_a_day_with_an_incomplete_today_list(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-15', total: 2, done: 1); // not fully cleared
        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);

        $this->assertSame(1, ProgressStats::currentStreak($user));
    }

    public function test_current_streak_breaks_on_a_day_with_no_today_list_at_all(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);
        // Nothing flagged "today" at all on the 15th — even though tasks may
        // have been completed that day, an empty today-list isn't a success.
        $this->completedOn($user, '2026-08-15', 4);

        $this->assertSame(1, ProgressStats::currentStreak($user));
    }

    public function test_finishing_an_old_leftover_task_retroactively_heals_a_streak_gap(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-14', total: 1, done: 1);
        $leftover = Task::factory()->for($user)->todos()->todayOn('2026-08-15')->create(); // still open
        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);

        // As it stands, the 15th is still open, so the streak is broken by it.
        $this->assertSame(1, ProgressStats::currentStreak($user));

        // Finishing the old leftover — no "close the day" ritual stops this
        // from counting, even though it happens two days late.
        $leftover->update(['is_completed' => true, 'completed_at' => now()]);

        $this->assertSame(3, ProgressStats::currentStreak($user));
    }

    public function test_best_streak_finds_the_longest_historical_run_even_if_shorter_now(): void
    {
        $successMap = [
            '2026-08-01' => true, '2026-08-02' => true, '2026-08-03' => true, '2026-08-04' => true,
            '2026-08-10' => true, '2026-08-11' => true,
        ];

        $this->assertSame(4, ProgressStats::bestStreak($successMap));
    }

    public function test_best_streak_is_zero_with_no_perfect_days(): void
    {
        $this->assertSame(0, ProgressStats::bestStreak([]));
    }

    // ── perfectDaysCount / perfectDayRate ─────────────────────────────────

    public function test_perfect_days_count_and_rate(): void
    {
        $successMap = ['2026-08-14' => true, '2026-08-15' => false, '2026-08-16' => true];

        $this->assertSame(2, ProgressStats::perfectDaysCount($successMap));
        $this->assertSame(67, ProgressStats::perfectDayRate($successMap));
    }

    public function test_perfect_day_rate_is_null_without_any_today_list_ever(): void
    {
        $this->assertNull(ProgressStats::perfectDayRate([]));
    }

    // ── bestDailyCount / heatmap (unaffected by the streak rework) ────────

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

    // ── celebrationFor: goal / record (perfect-day-free completions) ──────

    public function test_celebration_fires_once_when_crossing_the_daily_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);

        $this->completedOn($user, '2026-08-16', 2); // today already has 2
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // not part of any today-list

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 2);

        $this->assertSame('goal', $celebration['kind']);
    }

    public function test_celebration_does_not_fire_before_reaching_the_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 1));
    }

    public function test_celebration_does_not_refire_after_the_goal_was_already_crossed_today(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 4));
    }

    public function test_celebration_fires_a_record_when_beating_the_all_time_best(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal out of reach

        $this->completedOn($user, '2026-08-10', 4); // previous best: 4
        $this->completedOn($user, '2026-08-16', 4); // today already tied the best
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 4);

        $this->assertSame('record', $celebration['kind']);
        $this->assertSame('Neuer Bestwert: 5', $celebration['label']);
    }

    public function test_a_record_wins_over_a_simultaneous_goal_crossing(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);

        $this->completedOn($user, '2026-08-10', 4); // previous best: 4
        $this->completedOn($user, '2026-08-16', 4); // today: also 4, about to become 5
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 4);

        $this->assertSame('record', $celebration['kind']);
    }

    public function test_the_very_first_completion_ever_is_not_celebrated_as_a_record(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 0));
    }

    // ── celebrationFor: perfect day ────────────────────────────────────────

    public function test_celebration_fires_perfect_day_when_the_last_open_today_task_is_cleared(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal/record out of reach

        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);
        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 2);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    public function test_celebration_does_not_fire_perfect_day_while_other_today_tasks_are_still_open(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $justDone = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create(); // still open

        $celebration = ProgressStats::celebrationFor($user, $justDone, beforeCount: 0);

        $this->assertNotSame('perfect-day', $celebration['kind'] ?? null);
    }

    public function test_completing_a_task_unrelated_to_todays_list_never_fires_perfect_day(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // no today_date at all

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 0);

        $this->assertNull($celebration);
    }

    public function test_perfect_day_wins_over_a_simultaneous_goal_crossing(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);

        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    // ── celebrationFor: streak record ──────────────────────────────────────

    public function test_celebration_fires_a_streak_record_when_beating_the_all_time_best_streak(): void
    {
        Carbon::setTestNow('2026-08-20 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        // A historical best streak of 3, well separated from the current run.
        $this->todayListOn($user, '2026-08-10', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-11', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-12', total: 1, done: 1);

        // A fresh run already 3 days long going into today — completing
        // today's last task will make it 4, beating the historical best of 3.
        $this->todayListOn($user, '2026-08-17', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-18', total: 1, done: 1);
        $this->todayListOn($user, '2026-08-19', total: 1, done: 1);
        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-20')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('streak-record', $celebration['kind']);
        $this->assertSame('Neue Bestserie: 4 Tage', $celebration['label']);
    }

    public function test_celebration_falls_back_to_perfect_day_when_the_streak_hasnt_beaten_the_record_yet(): void
    {
        Carbon::setTestNow('2026-08-20 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        // A historical best streak of 5 — well out of reach of today's run.
        for ($i = 10; $i <= 14; $i++) {
            $this->todayListOn($user, "2026-08-{$i}", total: 1, done: 1);
        }

        // Today is only the 2nd day of a fresh run.
        $this->todayListOn($user, '2026-08-19', total: 1, done: 1);
        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-20')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    public function test_the_very_first_streak_ever_is_not_celebrated_as_a_streak_record(): void
    {
        Carbon::setTestNow('2026-08-20 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-20')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }
}
