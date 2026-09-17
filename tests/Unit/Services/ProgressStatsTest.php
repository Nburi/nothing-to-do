<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\StreakDayOutcome;
use App\Models\Task;
use App\Models\TaskDayPlan;
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

    /** A still-open board task, so a scenario doesn't accidentally clear the whole board. */
    private function decoyOpenTask(User $user): Task
    {
        return Task::factory()->for($user)->inbox()->create();
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

    // ── todayListStatsByDay: union of today_date and (planner-enabled) TaskDayPlan ──

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

    public function test_planner_plans_are_ignored_while_planner_is_disabled(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0, 'planner_enabled' => false]);
        $task = Task::factory()->for($user)->tasks()->create();
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => '2026-08-16', 'sort_order' => 0, 'source' => 'manual']);

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertArrayNotHasKey('2026-08-16', $stats);
    }

    public function test_planner_plans_count_toward_the_day_while_planner_is_enabled(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0, 'planner_enabled' => true]);
        $task = Task::factory()->for($user)->tasks()->create();
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => '2026-08-16', 'sort_order' => 0, 'source' => 'manual']);

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertSame(['total' => 1, 'done' => 0], $stats['2026-08-16']);
    }

    public function test_a_task_flagged_today_and_also_planned_for_today_is_not_double_counted(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0, 'planner_enabled' => true]);
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create();
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => '2026-08-16', 'sort_order' => 0, 'source' => 'manual']);

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertSame(['total' => 1, 'done' => 0], $stats['2026-08-16']);
    }

    public function test_a_project_task_planned_for_today_counts_while_planner_is_enabled_even_without_the_today_flag(): void
    {
        // Project-owned tasks are deliberately never promoted to is_today
        // (DayPlanner::promoteIfToday()'s onBoard() guard) — this is exactly
        // the gap Planner-awareness closes: without the union, this task
        // could never contribute to a perfect day at all.
        $user = User::factory()->create(['timezone_offset' => 0, 'planner_enabled' => true]);
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'is_completed' => true, 'completed_at' => '2026-08-16 09:00:00',
        ]);
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => '2026-08-16', 'sort_order' => 0, 'source' => 'manual']);

        $stats = ProgressStats::todayListStatsByDay($user);

        $this->assertSame(['total' => 1, 'done' => 1], $stats['2026-08-16']);
    }

    // ── isBoardFullyClear ────────────────────────────────────────────────

    public function test_board_is_fully_clear_once_every_active_board_task_is_done(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->completed()->create();

        $this->assertTrue(ProgressStats::isBoardFullyClear($user));
    }

    public function test_board_is_not_fully_clear_with_an_open_task_remaining(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create();

        $this->assertFalse(ProgressStats::isBoardFullyClear($user));
    }

    public function test_a_project_task_does_not_count_toward_board_clear_either_way(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]); // open, but not a board list

        $this->assertTrue(ProgressStats::isBoardFullyClear($user));
    }

    // ── dailyOutcomeMap ──────────────────────────────────────────────────

    public function test_outcome_map_marks_a_fully_cleared_today_list_as_perfect(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-16', total: 2, done: 2);

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertSame('perfect', $map['2026-08-16']);
    }

    public function test_outcome_map_marks_today_perfect_via_the_goal_when_theres_no_today_list_at_all(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 2]);
        $this->decoyOpenTask($user); // keeps the board non-empty, isolating the goal rule from full-clear
        $this->completedOn($user, '2026-08-16', 2);

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertSame('perfect', $map['2026-08-16']);
    }

    public function test_outcome_map_does_not_mark_today_perfect_below_the_goal_with_no_today_list(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);
        $this->decoyOpenTask($user);
        $this->completedOn($user, '2026-08-16', 2);

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertArrayNotHasKey('2026-08-16', $map);
    }

    public function test_outcome_map_marks_today_perfect_once_the_whole_board_is_cleared(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal out of reach
        Task::factory()->for($user)->todos()->completed()->create(['completed_at' => '2026-08-16 09:00:00']); // the only task, now done

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertSame('perfect', $map['2026-08-16']);
    }

    public function test_outcome_map_never_marks_an_untouched_day_perfect_just_because_the_board_is_vacuously_empty(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        // No tasks at all, nothing done today — an empty board here means
        // "never used the app today", not "cleared everything".

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertArrayNotHasKey('2026-08-16', $map);
    }

    public function test_a_persisted_perfect_day_survives_a_today_task_added_after_the_fact(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);

        // First read: today is genuinely, currently perfect — persist it.
        $this->assertSame('perfect', ProgressStats::dailyOutcomeMap($user)['2026-08-16']);
        ProgressStats::recordOutcome($user, $user->localToday(), StreakDayOutcome::OUTCOME_PERFECT, 'today_list');

        // A new today-task shows up later the same day (e.g. prepping ahead for tomorrow) — still open.
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create();

        $mapAfter = ProgressStats::dailyOutcomeMap($user);
        $this->assertSame('perfect', $mapAfter['2026-08-16'], 'A day already recorded perfect must not un-perfect itself.');
    }

    public function test_outcome_map_marks_an_incomplete_today_list_as_broken_for_rate_purposes(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-16', total: 2, done: 1);

        $map = ProgressStats::dailyOutcomeMap($user);

        $this->assertSame('broken', $map['2026-08-16']);
    }

    public function test_record_outcome_is_idempotent_the_first_write_wins(): void
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2026-08-16');

        $this->assertTrue(ProgressStats::recordOutcome($user, $date, StreakDayOutcome::OUTCOME_PERFECT, 'today_list'));
        $this->assertFalse(ProgressStats::recordOutcome($user, $date, StreakDayOutcome::OUTCOME_FROZEN, 'empty_day'));
        $this->assertSame(
            'perfect',
            StreakDayOutcome::query()->forUser($user)->whereDate('date', '2026-08-16')->first()->outcome,
        );
    }

    // ── evaluatePastDay / freezesUsedInTrailingWeek ───────────────────────

    public function test_evaluate_past_day_marks_a_fully_cleared_today_list_perfect(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-10', total: 2, done: 2);

        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10'));

        $this->assertDatabaseHas('streak_day_outcomes', [
            'user_id' => $user->id, 'date' => '2026-08-10', 'outcome' => 'perfect', 'reason' => 'today_list',
        ]);
    }

    public function test_evaluate_past_day_leaves_an_incomplete_today_list_undecided(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->todayListOn($user, '2026-08-10', total: 2, done: 1);

        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10'));

        $this->assertDatabaseMissing('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-10']);
    }

    public function test_evaluate_past_day_marks_perfect_via_the_goal_when_theres_no_today_list(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 2]);
        $this->completedOn($user, '2026-08-10', 2);

        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10'));

        $this->assertDatabaseHas('streak_day_outcomes', [
            'user_id' => $user->id, 'date' => '2026-08-10', 'outcome' => 'perfect', 'reason' => 'goal_empty_day',
        ]);
    }

    public function test_evaluate_past_day_freezes_a_genuinely_empty_day_when_budget_allows(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);
        // Nothing at all on 2026-08-10.

        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10'));

        $this->assertDatabaseHas('streak_day_outcomes', [
            'user_id' => $user->id, 'date' => '2026-08-10', 'outcome' => 'frozen', 'reason' => 'empty_day',
        ]);
    }

    public function test_evaluate_past_day_leaves_a_genuine_break_once_the_weekly_freeze_budget_is_spent(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-08'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-09'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');

        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10')); // a third empty day, same trailing week

        $this->assertDatabaseMissing('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-10']);
    }

    public function test_evaluate_past_day_is_a_no_op_once_a_day_is_already_decided(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-10'), StreakDayOutcome::OUTCOME_PERFECT, 'full_clear');

        // Would otherwise freeze (nothing else recorded for this day) — must stay untouched.
        ProgressStats::evaluatePastDay($user, Carbon::parse('2026-08-10'));

        $this->assertSame(
            'perfect',
            StreakDayOutcome::query()->forUser($user)->whereDate('date', '2026-08-10')->first()->outcome,
        );
    }

    public function test_freezes_used_in_trailing_week_counts_only_the_six_days_before_the_given_date(): void
    {
        $user = User::factory()->create();
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-03'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day'); // 7 days before -> outside
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-05'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day'); // inside

        $this->assertSame(1, ProgressStats::freezesUsedInTrailingWeek($user, Carbon::parse('2026-08-10')));
    }

    // ── currentStreak / bestStreak (perfect + frozen bridging) ────────────

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

    public function test_current_streak_breaks_on_a_genuinely_empty_day_once_the_freeze_budget_is_gone(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);
        // Nothing at all flagged "today" on the 15th, no completions, and its
        // freeze budget is already spent — a genuine break.
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-13'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-14'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');

        $this->assertSame(1, ProgressStats::currentStreak($user));
    }

    public function test_a_frozen_day_bridges_the_streak_without_extending_it(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $this->todayListOn($user, '2026-08-14', total: 1, done: 1);
        ProgressStats::recordOutcome($user, Carbon::parse('2026-08-15'), StreakDayOutcome::OUTCOME_FROZEN, 'empty_day');
        $this->todayListOn($user, '2026-08-16', total: 1, done: 1);

        // 3 days span the run, but only the 2 perfect ones count.
        $this->assertSame(2, ProgressStats::currentStreak($user));
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
        $outcomeMap = [
            '2026-08-01' => 'perfect', '2026-08-02' => 'perfect', '2026-08-03' => 'perfect', '2026-08-04' => 'perfect',
            '2026-08-10' => 'perfect', '2026-08-11' => 'perfect',
        ];

        $this->assertSame(4, ProgressStats::bestStreak($outcomeMap));
    }

    public function test_best_streak_bridges_a_frozen_day_without_extending_the_run(): void
    {
        $outcomeMap = [
            '2026-08-01' => 'perfect', '2026-08-02' => 'frozen', '2026-08-03' => 'perfect',
        ];

        $this->assertSame(2, ProgressStats::bestStreak($outcomeMap));
    }

    public function test_best_streak_is_zero_with_no_perfect_days(): void
    {
        $this->assertSame(0, ProgressStats::bestStreak([]));
        $this->assertSame(0, ProgressStats::bestStreak(['2026-08-01' => 'frozen']));
    }

    // ── perfectDaysCount / perfectDayRate ─────────────────────────────────

    public function test_perfect_days_count_and_rate_reflect_decided_days_only(): void
    {
        $outcomeMap = ['2026-08-14' => 'perfect', '2026-08-15' => 'frozen', '2026-08-16' => 'perfect'];

        $this->assertSame(2, ProgressStats::perfectDaysCount($outcomeMap));
        $this->assertSame(67, ProgressStats::perfectDayRate($outcomeMap));
    }

    public function test_perfect_day_rate_is_null_without_anything_ever_decided(): void
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
        $this->assertFalse($days[13]['isStreakDay']); // no outcome map passed -> both flags default false
        $this->assertFalse($days[13]['isFrozen']);
    }

    public function test_heatmap_flags_perfect_and_frozen_days_from_a_given_outcome_map(): void
    {
        Carbon::setTestNow('2026-08-16 12:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);

        $days = ProgressStats::heatmap($user, [], weeks: 2, outcomeMap: [
            '2026-08-15' => 'perfect',
            '2026-08-14' => 'frozen',
        ]);

        $byDate = collect($days)->keyBy('date');
        $this->assertTrue($byDate['2026-08-15']['isStreakDay']);
        $this->assertFalse($byDate['2026-08-15']['isFrozen']);
        $this->assertTrue($byDate['2026-08-14']['isFrozen']);
        $this->assertFalse($byDate['2026-08-14']['isStreakDay']);
    }

    // ── celebrationFor: goal / record (perfect-day-free completions) ──────

    public function test_crossing_the_daily_goal_with_no_today_set_at_all_now_fires_perfect_day(): void
    {
        // Requirement 3 of the streak rework: reaching the goal on a day with
        // no today-list/plan makes the day perfect outright, which — per the
        // pre-existing priority (perfect-day already outranked the plain
        // 'goal' tier) — is what now shows here instead of 'goal'. The 'goal'
        // tier itself still exists for the narrower case below: a today-set
        // that exists but isn't finished yet.
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);

        $this->decoyOpenTask($user); // keeps the board non-empty, isolating this from full-clear
        $this->completedOn($user, '2026-08-16', 2); // today already has 2
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // not part of any today-list

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 2);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    public function test_the_goal_tier_still_fires_on_its_own_when_todays_own_list_exists_but_isnt_finished(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);

        $this->decoyOpenTask($user);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create(); // today's own list stays open
        $this->completedOn($user, '2026-08-16', 2); // 2 completions so far, unrelated to that list
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // crosses the overall daily goal

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 2);

        $this->assertSame('goal', $celebration['kind']);
    }

    public function test_celebration_does_not_fire_before_reaching_the_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);
        $this->decoyOpenTask($user);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 1));
    }

    public function test_celebration_does_not_refire_after_the_goal_was_already_crossed_today(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);
        $this->decoyOpenTask($user);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 4));
    }

    public function test_celebration_fires_a_record_when_beating_the_all_time_best(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal out of reach

        $this->decoyOpenTask($user);
        $this->completedOn($user, '2026-08-10', 4); // previous best: 4
        $this->completedOn($user, '2026-08-16', 4); // today already tied the best
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 4);

        $this->assertSame('record', $celebration['kind']);
        $this->assertSame('Neuer Bestwert: 5', $celebration['label']);
    }

    public function test_a_record_wins_over_a_simultaneous_goal_crossing_when_todays_own_list_isnt_perfect(): void
    {
        // Isolated the same way as the 'goal' tier test above: with no
        // today-set at all, this scenario would now become 'perfect-day'
        // instead (goal-empty-day is itself a perfect-day reason) — a
        // still-open today-task keeps this specifically a record/goal case.
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);

        $this->decoyOpenTask($user);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create(); // today's own list stays open
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
        $this->decoyOpenTask($user);
        $task = Task::factory()->for($user)->inbox()->completed()->create();

        $this->assertNull(ProgressStats::celebrationFor($user, $task, beforeCount: 0));
    }

    // ── celebrationFor: perfect day (today-list / planner) ────────────────

    public function test_celebration_fires_perfect_day_when_the_last_open_today_task_is_cleared(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal/record out of reach
        $this->decoyOpenTask($user); // isolates this from a simultaneous full-clear

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
        $this->decoyOpenTask($user); // so completing $task alone doesn't also clear the whole board
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // no today_date at all

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 0);

        $this->assertNull($celebration);
    }

    public function test_perfect_day_wins_over_a_simultaneous_goal_crossing(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        $this->decoyOpenTask($user);

        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    public function test_a_project_task_completed_via_its_planner_plan_fires_perfect_day(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20, 'planner_enabled' => true]);
        $this->decoyOpenTask($user);

        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => '2026-08-16', 'sort_order' => 0, 'source' => 'manual']);

        $task->update(['is_completed' => true, 'completed_at' => now()]);
        $celebration = ProgressStats::celebrationFor($user, $task->fresh(), beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }

    // ── celebrationFor: reaching the goal with no today-set at all ────────

    public function test_reaching_the_goal_with_no_today_set_counts_as_a_perfect_day(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 2]);
        $this->decoyOpenTask($user); // isolates this from full-clear

        $this->completedOn($user, '2026-08-16', 1);
        $task = Task::factory()->for($user)->inbox()->completed()->create(); // crosses the goal, no today flag

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 1);

        $this->assertSame('perfect-day', $celebration['kind']);
        $this->assertDatabaseHas('streak_day_outcomes', [
            'user_id' => $user->id, 'date' => '2026-08-16', 'outcome' => 'perfect', 'reason' => 'goal_empty_day',
        ]);
    }

    public function test_reaching_the_goal_with_no_today_set_does_not_refire_on_a_later_completion_the_same_day(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        Task::factory()->for($user)->inbox()->create(); // decoy: stays open throughout, keeps the board non-empty

        $first = Task::factory()->for($user)->inbox()->completed()->create();
        $firstCelebration = ProgressStats::celebrationFor($user, $first, beforeCount: 0);
        $this->assertSame('perfect-day', $firstCelebration['kind']);

        $second = Task::factory()->for($user)->inbox()->completed()->create();
        $this->assertNull(ProgressStats::celebrationFor($user, $second, beforeCount: 1));
    }

    // ── celebrationFor: full board clear ───────────────────────────────────

    public function test_clearing_the_whole_board_fires_full_clear(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]); // goal/record out of reach

        $task = Task::factory()->for($user)->todos()->completed()->create(['completed_at' => now()]); // the only board task

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 0);

        $this->assertSame('full-clear', $celebration['kind']);
        $this->assertSame('Alles erledigt!', $celebration['label']);
        $this->assertDatabaseHas('streak_day_outcomes', [
            'user_id' => $user->id, 'date' => '2026-08-16', 'outcome' => 'perfect', 'reason' => 'full_clear',
        ]);
    }

    public function test_full_clear_wins_over_a_plain_perfect_day(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        // The last today-flagged task is ALSO the last active board task.
        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('full-clear', $celebration['kind']);
    }

    public function test_completing_a_project_task_never_fires_full_clear(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);

        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'is_completed' => true, 'completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $task, beforeCount: 0);

        $this->assertNull($celebration);
    }

    public function test_is_board_fully_clear_is_vacuously_true_with_no_tasks_at_all(): void
    {
        // Documents why celebrationFor() alone can never spuriously fire
        // full-clear for an untouched account: it's only ever called right
        // after actually completing a real board task, so this vacuous case
        // can't reach it in practice. dailyOutcomeMap()'s own passive read of
        // "today" guards against it explicitly instead — see
        // test_outcome_map_never_marks_an_untouched_day_perfect_just_because_the_board_is_vacuously_empty.
        $user = User::factory()->create();

        $this->assertTrue(ProgressStats::isBoardFullyClear($user));
    }

    // ── celebrationFor: streak record ──────────────────────────────────────

    public function test_celebration_fires_a_streak_record_when_beating_the_all_time_best_streak(): void
    {
        Carbon::setTestNow('2026-08-20 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 20]);
        $this->decoyOpenTask($user);

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
        $this->decoyOpenTask($user);

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
        $this->decoyOpenTask($user);

        $lastOne = Task::factory()->for($user)->todos()->todayOn('2026-08-20')->completed()->create(['completed_at' => now()]);

        $celebration = ProgressStats::celebrationFor($user, $lastOne, beforeCount: 0);

        $this->assertSame('perfect-day', $celebration['kind']);
    }
}
