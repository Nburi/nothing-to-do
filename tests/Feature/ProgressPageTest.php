<?php

namespace Tests\Feature;

use App\Livewire\Progress;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ProgressPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/app/progress')->assertRedirect('/login');
    }

    public function test_the_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Progress::class)->assertOk()->assertSee('Fortschritt');
    }

    public function test_it_reflects_todays_completions_and_the_configured_goal(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 3]);
        $this->actingAs($user);

        Task::factory()->for($user)->completed()->count(2)->create(['completed_at' => '2026-08-16 09:00:00']);

        $page = Livewire::test(Progress::class)->instance();

        $this->assertSame(2, $page->todayCount());
        $this->assertSame(3, $page->goal());
        $this->assertSame(2, $page->totalCompleted());
    }

    public function test_it_computes_the_current_and_best_streak_from_cleared_today_lists(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        foreach (['2026-08-14', '2026-08-15', '2026-08-16'] as $date) {
            Task::factory()->for($user)->todos()->todayOn($date)->completed()->create(['completed_at' => $date.' 09:00:00']);
        }
        // An older, longer run further back — best streak should find it even
        // though the current streak (above) is shorter.
        foreach (['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04'] as $date) {
            Task::factory()->for($user)->todos()->todayOn($date)->completed()->create(['completed_at' => $date.' 09:00:00']);
        }

        $page = Livewire::test(Progress::class)->instance();

        $this->assertSame(3, $page->currentStreak());
        $this->assertSame(4, $page->bestStreak());
    }

    public function test_a_day_with_completions_but_no_today_list_does_not_count_toward_the_streak(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        // Plenty done today, but never flagged "today" — the new streak
        // basis cares about the today-list specifically, not raw volume.
        Task::factory()->for($user)->inbox()->completed()->count(5)->create(['completed_at' => '2026-08-16 09:00:00']);

        $page = Livewire::test(Progress::class)->instance();

        $this->assertSame(0, $page->currentStreak());
    }

    public function test_perfect_days_count_and_rate_reflect_real_data(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 09:00:00']);
        // A second day with an unfinished today-list — counts toward the rate's denominator, not its numerator.
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create();

        $page = Livewire::test(Progress::class)->instance();

        $this->assertSame(1, $page->perfectDaysCount());
        $this->assertSame(50, $page->perfectDayRate());
    }

    public function test_the_heatmap_spans_twelve_full_weeks(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $this->actingAs(User::factory()->create(['timezone_offset' => 0]));

        $page = Livewire::test(Progress::class)->instance();

        $this->assertCount(84, $page->heatmap());
    }

    public function test_a_user_with_no_completions_sees_an_empty_state_without_errors(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Progress::class)->assertOk();
    }
}
