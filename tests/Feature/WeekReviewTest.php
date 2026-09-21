<?php

namespace Tests\Feature;

use App\Livewire\WeekReviewPage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\WeekReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WeekReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Wednesday 2026-09-23 12:00 UTC; this week is Mon 21.9. – Sun 27.9. */
    private function user(array $attributes = []): User
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        return User::factory()->create($attributes + ['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    private function done(User $user, string $at, string $title = 'Erledigt'): Task
    {
        return Task::factory()->for($user)->completed()->create(['title' => $title, 'completed_at' => $at]);
    }

    public function test_the_week_starts_on_monday_and_can_be_moved_by_whole_weeks(): void
    {
        $user = $this->user();

        $this->assertSame('2026-09-21', WeekReview::weekStart($user)->toDateString());
        $this->assertSame('2026-09-14', WeekReview::weekStart($user, -1)->toDateString());
        $this->assertSame('2026-08-24', WeekReview::weekStart($user, -4)->toDateString());
    }

    public function test_the_local_week_follows_the_users_timezone(): void
    {
        // 23:30 UTC on Sunday the 20th is already Monday the 21st at UTC+2.
        Carbon::setTestNow('2026-09-20 23:30:00');
        $user = User::factory()->create(['timezone_offset' => 2, 'timezone_auto_dst' => false]);

        $this->assertSame('2026-09-21', WeekReview::weekStart($user)->toDateString());
    }

    public function test_it_counts_per_day_and_finds_the_strongest_day(): void
    {
        $user = $this->user();
        $this->done($user, '2026-09-21 09:00:00');
        $this->done($user, '2026-09-22 09:00:00');
        $this->done($user, '2026-09-22 10:00:00');
        $this->done($user, '2026-09-22 11:00:00');
        $this->done($user, '2026-09-23 08:00:00');

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame([1, 3, 1, 0, 0, 0, 0], collect($r['days'])->pluck('count')->all());
        $this->assertSame(5, $r['total']);
        $this->assertSame(3, $r['activeDays']);
        $this->assertSame(['label' => 'Dienstag', 'count' => 3], $r['bestDay']);
        $this->assertTrue($r['days'][2]['isToday']);
        $this->assertTrue($r['days'][3]['isFuture']);
        $this->assertTrue($r['isCurrent']);
    }

    public function test_tasks_finished_outside_the_week_are_not_counted_or_listed(): void
    {
        $user = $this->user();
        $this->done($user, '2026-09-20 22:00:00', 'Sonntag davor');
        $this->done($user, '2026-09-21 06:00:00', 'Montag');

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame(1, $r['total']);
        $this->assertSame(['Montag'], $r['completed'][0]['titles']);
    }

    public function test_completions_are_bucketed_by_the_users_local_day(): void
    {
        // Sunday 20th 23:00 UTC is Monday 21st 01:00 at UTC+2 — it belongs to *this* week there.
        $user = $this->user(['timezone_offset' => 2]);
        $this->done($user, '2026-09-20 23:00:00', 'Fast Mitternacht');

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame(1, $r['days'][0]['count']);
        $this->assertSame(['Fast Mitternacht'], $r['completed'][0]['titles']);
    }

    public function test_only_the_users_own_tasks_show_up(): void
    {
        $user = $this->user();
        $this->done(User::factory()->create(), '2026-09-22 09:00:00', 'Fremd');

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame(0, $r['total']);
        $this->assertSame([], $r['completed']);
    }

    public function test_the_title_list_is_capped_but_the_total_stays_exact(): void
    {
        $user = $this->user();
        foreach (range(1, WeekReview::TITLE_LIMIT + 5) as $i) {
            $this->done($user, '2026-09-22 09:00:00', "Aufgabe {$i}");
        }

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame(WeekReview::TITLE_LIMIT + 5, $r['total']);
        $this->assertCount(WeekReview::TITLE_LIMIT, $r['completed'][0]['titles']);
        $this->assertSame(5, $r['completed'][0]['more']);
    }

    public function test_open_lists_soft_and_hard_dated_board_tasks_of_the_week_only(): void
    {
        $user = $this->user();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->todos()->create(['title' => 'Soft in Woche', 'due_date' => '2026-09-22']);
        Task::factory()->for($user)->todos()->create(['title' => 'Hart in Woche', 'deadline' => '2026-09-25']);
        Task::factory()->for($user)->todos()->create(['title' => 'Nächste Woche', 'due_date' => '2026-09-29']);
        Task::factory()->for($user)->todos()->create(['title' => 'Hart raus, soft drin', 'deadline' => '2026-10-10', 'due_date' => '2026-09-23']);
        Task::factory()->for($user)->todos()->completed()->create(['title' => 'Schon fertig', 'due_date' => '2026-09-22']);
        Task::factory()->for($user)->create(['title' => 'Im Projekt', 'list' => 'projects', 'project_id' => $project->id, 'due_date' => '2026-09-22']);

        $r = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertSame(2, $r['openCount']);
        $this->assertSame(['Soft in Woche', 'Hart in Woche'], collect($r['open'])->pluck('title')->all());
    }

    public function test_a_future_week_is_empty_and_says_so(): void
    {
        $user = $this->user();
        Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-30']);

        $r = WeekReview::for($user, WeekReview::weekStart($user, 1));

        $this->assertTrue($r['isFuture']);
        $this->assertSame([], $r['completed']);
        $this->assertSame(0, $r['openCount']);
        $this->assertSame('Diese Woche liegt noch vor dir.', $r['verdict']);
    }

    // ── the verdict sentence ─────────────────────────────────────────

    public function test_a_finished_week_is_compared_with_the_four_weeks_before_it(): void
    {
        $user = $this->user();
        // An old record week far outside the comparison window, so last week is not a record.
        foreach (range(1, 40) as $i) {
            $this->done($user, '2026-07-28 09:00:00');
        }
        // Four earlier weeks with 10 each, then last week (14.-20.9.) with 12 → above average.
        foreach ([-2, -3, -4, -5] as $w) {
            foreach (range(1, 10) as $i) {
                $this->done($user, Carbon::parse('2026-09-22')->addWeeks($w)->format('Y-m-d').' 09:00:00');
            }
        }
        foreach (range(1, 12) as $i) {
            $this->done($user, '2026-09-15 09:00:00');
        }

        $last = WeekReview::for($user, WeekReview::weekStart($user, -1));

        $this->assertSame(10, $last['average']);
        $this->assertStringContainsString('Über deinem Schnitt', $last['verdict']);
    }

    public function test_a_record_week_says_so(): void
    {
        $user = $this->user();
        foreach (range(1, 3) as $i) {
            $this->done($user, '2026-09-08 09:00:00');
        }
        foreach (range(1, 9) as $i) {
            $this->done($user, '2026-09-15 09:00:00');
        }

        $last = WeekReview::for($user, WeekReview::weekStart($user, -1));

        $this->assertSame('Deine stärkste Woche bisher.', $last['verdict']);
    }

    public function test_a_quiet_week_is_called_quiet_not_bad(): void
    {
        $user = $this->user();
        foreach ([-2, -3, -4, -5] as $w) {
            foreach (range(1, 10) as $i) {
                $this->done($user, Carbon::parse('2026-09-22')->addWeeks($w)->format('Y-m-d').' 09:00:00');
            }
        }
        $this->done($user, '2026-09-15 09:00:00');
        $this->done($user, '2026-09-16 09:00:00');

        $last = WeekReview::for($user, WeekReview::weekStart($user, -1));

        $this->assertStringContainsString('Ruhiger als sonst', $last['verdict']);
    }

    public function test_the_running_week_is_never_judged_only_reported(): void
    {
        $user = $this->user();
        foreach (range(1, 20) as $i) {
            $this->done($user, '2026-09-16 09:00:00');
        }
        $this->done($user, '2026-09-22 09:00:00');

        $now = WeekReview::for($user, WeekReview::weekStart($user));

        $this->assertStringContainsString('Bisher 1 erledigt', $now['verdict']);
        $this->assertStringNotContainsString('Ruhiger', $now['verdict']);
    }

    public function test_an_empty_running_week_is_encouraging_an_empty_past_week_is_plain(): void
    {
        $user = $this->user();

        $this->assertStringContainsString('es ist noch Zeit', WeekReview::for($user, WeekReview::weekStart($user))['verdict']);
        $this->assertSame('In dieser Woche wurde nichts erledigt.', WeekReview::for($user, WeekReview::weekStart($user, -1))['verdict']);
    }

    // ── the page ─────────────────────────────────────────────────────

    public function test_the_page_renders_and_pages_through_weeks_but_never_into_the_future(): void
    {
        $user = $this->user();
        $this->done($user, '2026-09-15 09:00:00', 'Letzte Woche erledigt');

        Livewire::actingAs($user)->test(WeekReviewPage::class)
            ->assertSee('Diese Woche')
            ->assertDontSee('Letzte Woche erledigt')
            ->call('previousWeek')
            ->assertSee('Letzte Woche')
            ->assertSee('Letzte Woche erledigt')
            ->call('nextWeek')
            ->call('nextWeek')
            ->assertSet('weekOffset', 0)
            ->call('previousWeek')
            ->call('previousWeek')
            ->assertSet('weekOffset', -2)
            ->call('thisWeek')
            ->assertSet('weekOffset', 0);
    }

    public function test_the_page_route_needs_a_login_and_is_linked_from_fortschritt(): void
    {
        $user = $this->user();

        $this->get(route('review'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('review'))->assertOk()->assertSee('Wochenrückblick');
        $this->actingAs($user)->get(route('progress'))->assertSee(route('review'), false);
    }

    public function test_hiding_the_fortschritt_module_hides_the_review_too(): void
    {
        $user = $this->user(['hidden_modules' => ['progress']]);

        Livewire::actingAs($user)->test(WeekReviewPage::class)->assertRedirect();
    }
}
