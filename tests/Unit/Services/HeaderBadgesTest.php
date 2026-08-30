<?php

namespace Tests\Unit\Services;

use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\HeaderBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HeaderBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── preferenceRowsFor ────────────────────────────────────────────

    public function test_a_never_customised_user_defaults_to_streak_and_agenda_enabled_only(): void
    {
        $user = User::factory()->create(['header_badges' => null]);

        $rows = collect(HeaderBadges::preferenceRowsFor($user))->keyBy('key');

        $this->assertTrue($rows['streak']['enabled']);
        $this->assertTrue($rows['agenda']['enabled']);
        $this->assertFalse($rows['today']['enabled']);
        $this->assertFalse($rows['schedule']['enabled']);
        $this->assertFalse($rows['goal']['enabled']);
        $this->assertFalse($rows['emergency']['enabled']);
        $this->assertFalse($rows['crafts']['enabled']);
    }

    public function test_every_catalog_key_appears_exactly_once_for_a_default_user(): void
    {
        $user = User::factory()->create(['header_badges' => null]);

        $keys = collect(HeaderBadges::preferenceRowsFor($user))->pluck('key');

        $this->assertSame(array_keys(HeaderBadges::CATALOG), $keys->all());
    }

    public function test_a_stored_preference_is_used_verbatim_in_its_own_order(): void
    {
        $user = User::factory()->create(['header_badges' => [
            ['key' => 'schedule', 'enabled' => true],
            ['key' => 'streak', 'enabled' => false],
        ]]);

        $rows = HeaderBadges::preferenceRowsFor($user);

        $this->assertSame('schedule', $rows[0]['key']);
        $this->assertTrue($rows[0]['enabled']);
        $this->assertSame('streak', $rows[1]['key']);
        $this->assertFalse($rows[1]['enabled']);
    }

    public function test_a_catalog_key_missing_from_a_customised_list_is_appended_disabled(): void
    {
        $user = User::factory()->create(['header_badges' => [
            ['key' => 'streak', 'enabled' => true],
        ]]);

        $rows = collect(HeaderBadges::preferenceRowsFor($user))->keyBy('key');

        $this->assertTrue($rows['streak']['enabled']);
        // Never silently reactivated inside an already-customised list, even
        // though 'agenda' is on by default for a never-customised user.
        $this->assertFalse($rows['agenda']['enabled']);
        $this->assertFalse($rows['today']['enabled']);
    }

    public function test_an_unknown_stored_key_is_dropped_without_crashing(): void
    {
        $user = User::factory()->create(['header_badges' => [
            ['key' => 'some-removed-badge', 'enabled' => true],
            ['key' => 'streak', 'enabled' => true],
        ]]);

        $keys = collect(HeaderBadges::preferenceRowsFor($user))->pluck('key');

        $this->assertNotContains('some-removed-badge', $keys);
        $this->assertContains('streak', $keys);
    }

    // ── visibleFor: zero-state hiding + ordering ───────────────────────

    public function test_visible_for_is_empty_for_a_default_user_with_nothing_to_show(): void
    {
        $user = User::factory()->create(['header_badges' => null]);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_visible_for_respects_the_stored_order(): void
    {
        Carbon::setTestNow('2026-08-24 10:00:00');
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'header_badges' => [
                ['key' => 'agenda', 'enabled' => true],
                ['key' => 'streak', 'enabled' => true],
            ],
        ]);
        AgendaEntry::factory()->for($user)->homework()->create();
        $this->seedStreakOfOneDay($user, '2026-08-24');

        $keys = collect(HeaderBadges::visibleFor($user))->pluck('key');

        $this->assertSame(['agenda', 'streak'], $keys->all());
    }

    // ── streak ──────────────────────────────────────────────────────

    public function test_streak_badge_is_hidden_when_there_is_no_streak(): void
    {
        $user = $this->userWith(['streak']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_streak_badge_shows_the_current_streak_and_its_tier(): void
    {
        Carbon::setTestNow('2026-08-24 18:00:00');
        $user = $this->userWith(['streak']);
        $this->seedStreakOfOneDay($user, '2026-08-24');
        $this->seedStreakOfOneDay($user, '2026-08-23');
        $this->seedStreakOfOneDay($user, '2026-08-22');

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('3', $badge['text']);
        $this->assertSame(2, $badge['tier']); // streakTier(3) === 2
    }

    // ── agenda ──────────────────────────────────────────────────────

    public function test_agenda_badge_is_hidden_when_nothing_is_open(): void
    {
        $user = $this->userWith(['agenda']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_agenda_badge_counts_open_homework_and_exams_together(): void
    {
        $user = $this->userWith(['agenda']);
        AgendaEntry::factory()->for($user)->homework()->create();
        AgendaEntry::factory()->for($user)->exam()->create();
        AgendaEntry::factory()->for($user)->homework()->done()->create(); // already done — excluded

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('2', $badge['text']);
    }

    // ── today ───────────────────────────────────────────────────────

    public function test_today_badge_is_hidden_when_nothing_is_flagged_today(): void
    {
        $user = $this->userWith(['today']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_today_badge_counts_only_active_onboard_today_tasks(): void
    {
        $user = $this->userWith(['today']);
        Task::factory()->for($user)->today()->create();
        Task::factory()->for($user)->today()->create();
        Task::factory()->for($user)->today()->completed()->create(); // completed — excluded
        Task::factory()->for($user)->inbox()->create(); // never flagged today — excluded

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('2', $badge['text']);
        $this->assertStringContainsString('tab=today', $badge['href']);
    }

    // ── schedule ────────────────────────────────────────────────────

    public function test_schedule_badge_is_hidden_when_nothing_is_scheduled_today(): void
    {
        $user = $this->userWith(['schedule']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_schedule_badge_prefers_the_currently_active_event(): void
    {
        Carbon::setTestNow('2026-08-24 14:15:00');
        $user = $this->userWith(['schedule'], ['timezone_offset' => 0]);
        $active = ScheduleEvent::factory()->for($user)->on('2026-08-24')->at('14:00', '15:00')->create();
        ScheduleEvent::factory()->for($user)->on('2026-08-24')->at('16:00', '17:00')->create();

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('14:00', $badge['text']);
        $this->assertStringContainsString('event='.$active->id, $badge['href']);
        $this->assertStringContainsString('Jetzt:', $badge['title']);
    }

    public function test_schedule_badge_falls_back_to_the_next_upcoming_event_when_none_is_active(): void
    {
        Carbon::setTestNow('2026-08-24 10:00:00');
        $user = $this->userWith(['schedule'], ['timezone_offset' => 0]);
        ScheduleEvent::factory()->for($user)->on('2026-08-24')->at('09:00', '09:30')->create(); // already past
        $next = ScheduleEvent::factory()->for($user)->on('2026-08-24')->at('14:00', '15:00')->create();

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('14:00', $badge['text']);
        $this->assertStringContainsString((string) $next->id, $badge['href']);
        $this->assertStringNotContainsString('Jetzt:', $badge['title']);
    }

    // ── goal ────────────────────────────────────────────────────────

    public function test_goal_badge_is_hidden_when_nothing_is_completed_today(): void
    {
        $user = $this->userWith(['goal']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_goal_badge_shows_todays_completed_count_against_the_daily_goal(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        $user = $this->userWith(['goal'], ['timezone_offset' => 0, 'daily_task_goal' => 5]);
        Task::factory()->for($user)->completed()->create(['completed_at' => now()]);
        Task::factory()->for($user)->completed()->create(['completed_at' => now()]);

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('2/5', $badge['text']);
    }

    // ── emergency ───────────────────────────────────────────────────

    public function test_emergency_badge_is_hidden_when_emergency_mode_is_not_active(): void
    {
        $user = $this->userWith(['emergency']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_emergency_badge_shows_the_active_projects_progress(): void
    {
        $user = $this->userWith(['emergency']);
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->tasks()->create(['project_id' => $project->id]);
        Task::factory()->for($user)->tasks()->completed()->create(['project_id' => $project->id]);
        $user->update(['emergency_project_id' => $project->id]);

        $badge = HeaderBadges::visibleFor($user->fresh())[0];

        $this->assertSame('1/2', $badge['text']);
    }

    // ── crafts ──────────────────────────────────────────────────────

    public function test_crafts_badge_is_hidden_when_there_are_no_open_ideas(): void
    {
        $user = $this->userWith(['crafts']);

        $this->assertSame([], HeaderBadges::visibleFor($user));
    }

    public function test_crafts_badge_counts_open_ideas_and_excludes_done_ones(): void
    {
        $user = $this->userWith(['crafts']);
        CraftIdea::factory()->for($user)->create();
        CraftIdea::factory()->for($user)->create();
        CraftIdea::factory()->for($user)->done()->create();

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertSame('2', $badge['text']);
    }

    public function test_crafts_badge_names_the_oldest_open_idea_in_its_title(): void
    {
        $user = $this->userWith(['crafts']);
        $first = CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus bauen']);
        CraftIdea::factory()->for($user)->create(['title' => 'Regal streichen']);

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertStringContainsString('„Vogelhaus bauen“', $badge['title']);
        $this->assertStringContainsString('1 weitere Idee', $badge['title']);
    }

    public function test_crafts_badge_title_names_the_single_open_idea_without_a_weitere_suffix(): void
    {
        $user = $this->userWith(['crafts']);
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus bauen']);

        $badge = HeaderBadges::visibleFor($user)[0];

        $this->assertStringContainsString('„Vogelhaus bauen“ — Bastelideen ansehen', $badge['title']);
    }

    // ── helpers ─────────────────────────────────────────────────────

    private function userWith(array $enabledKeys, array $attributes = []): User
    {
        return User::factory()->create([
            ...$attributes,
            'header_badges' => collect($enabledKeys)
                ->map(fn (string $key) => ['key' => $key, 'enabled' => true])
                ->all(),
        ]);
    }

    private function seedStreakOfOneDay(User $user, string $date): void
    {
        Task::factory()->for($user)->todos()->todayOn($date)->create([
            'is_completed' => true,
            'completed_at' => $date.' 12:00:00',
        ]);
    }
}
