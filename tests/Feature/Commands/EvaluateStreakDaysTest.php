<?php

namespace Tests\Feature\Commands;

use App\Models\StreakDayOutcome;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EvaluateStreakDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_first_run_only_evaluates_yesterday_not_the_whole_history(): void
    {
        Carbon::setTestNow('2026-08-16 00:30:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-01')->completed()->create(['completed_at' => '2026-08-01 09:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 09:00:00']);

        $this->artisan('app:evaluate-streak-days')->assertSuccessful();

        $this->assertDatabaseHas('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-15', 'outcome' => 'perfect']);
        $this->assertDatabaseMissing('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-01']);
        $this->assertSame('2026-08-15', $user->fresh()->streak_last_evaluated_date->toDateString());
    }

    public function test_a_later_run_only_walks_forward_from_where_it_left_off(): void
    {
        Carbon::setTestNow('2026-08-16 00:30:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'streak_last_evaluated_date' => '2026-08-14']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 09:00:00']);

        $this->artisan('app:evaluate-streak-days')->assertSuccessful();

        $this->assertDatabaseHas('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-15', 'outcome' => 'perfect']);
        $this->assertSame('2026-08-15', $user->fresh()->streak_last_evaluated_date->toDateString());
    }

    public function test_several_missed_days_are_all_walked_in_one_run(): void
    {
        Carbon::setTestNow('2026-08-16 00:30:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'streak_last_evaluated_date' => '2026-08-10']);
        // 2026-08-11 through 2026-08-15 are all genuinely empty days.

        $this->artisan('app:evaluate-streak-days')->assertSuccessful();

        $this->assertDatabaseHas('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-11', 'outcome' => 'frozen']);
        $this->assertDatabaseHas('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-12', 'outcome' => 'frozen']);
        // The weekly freeze budget (2) is spent after the 11th and 12th — the
        // 13th onward stays undecided, a genuine break.
        $this->assertDatabaseMissing('streak_day_outcomes', ['user_id' => $user->id, 'date' => '2026-08-13']);
        $this->assertSame('2026-08-15', $user->fresh()->streak_last_evaluated_date->toDateString());
    }

    public function test_running_twice_the_same_day_does_not_duplicate_or_error(): void
    {
        Carbon::setTestNow('2026-08-16 00:30:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 09:00:00']);

        $this->artisan('app:evaluate-streak-days')->assertSuccessful();
        $this->artisan('app:evaluate-streak-days')->assertSuccessful();

        $this->assertSame(1, StreakDayOutcome::query()->forUser($user)->count());
    }

    public function test_a_day_already_recorded_live_is_not_touched_again(): void
    {
        Carbon::setTestNow('2026-08-16 00:30:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        // Nothing on 2026-08-15 at all — would otherwise freeze — but it was
        // already recorded 'perfect' live (e.g. via a full board clear).
        \App\Services\ProgressStats::recordOutcome($user, Carbon::parse('2026-08-15'), 'perfect', 'full_clear');

        $this->artisan('app:evaluate-streak-days')->assertSuccessful();

        $this->assertSame(
            'perfect',
            StreakDayOutcome::query()->forUser($user)->whereDate('date', '2026-08-15')->first()->outcome,
        );
    }
}
