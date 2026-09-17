<?php

namespace Tests\Feature;

use App\Livewire\DayPreview;
use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DayPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/app/today')->assertRedirect('/login');
    }

    public function test_the_page_renders_and_marks_it_seen(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        $this->assertFalse($user->fresh()->hasSeenDayPreviewToday());

        Livewire::test(DayPreview::class)->assertOk();

        $this->assertTrue($user->fresh()->hasSeenDayPreviewToday());
    }

    public function test_the_seen_flag_is_time_of_day_independent(): void
    {
        // 14:00, well past any "morning" window — the plan's own GERATEN line:
        // the dot must still clear on the day's first visit, whatever the hour.
        Carbon::setTestNow('2026-09-17 14:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        Livewire::test(DayPreview::class);

        $this->assertTrue($user->fresh()->hasSeenDayPreviewToday());
    }

    public function test_emergency_mode_replaces_the_grid_with_a_roadmap(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['project_id' => $project->id, 'is_completed' => true, 'completed_at' => now()]);
        Task::factory()->for($user)->create(['project_id' => $project->id, 'title' => 'Nächster Schritt', 'sort_order' => 0]);
        $user->update(['emergency_project_id' => $project->id]);
        $this->actingAs($user->fresh());

        Livewire::test(DayPreview::class)
            ->assertSee('Notfallmodus aktiv')
            ->assertSee($project->name)
            ->assertSee('Nächster Schritt')
            ->assertDontSee('Tagesziel');
    }

    public function test_shows_a_prepare_nudge_and_a_craft_idea_when_nothing_is_flagged_for_today(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus bauen']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)
            ->assertSee('Jetzt vorbereiten')
            ->assertSee('Vogelhaus bauen');
    }

    public function test_hides_the_craft_idea_once_something_is_flagged_for_today(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($user)->today()->create();
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus bauen']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)
            ->assertDontSee('Jetzt vorbereiten')
            ->assertDontSee('Vogelhaus bauen');
    }

    public function test_due_items_are_bucketed_overdue_today_and_soon_and_cap_the_list(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($user)->create(['title' => 'Überfällige Aufgabe', 'deadline' => '2026-09-15']);
        Task::factory()->for($user)->create(['title' => 'Heutige Aufgabe', 'deadline' => '2026-09-17']);
        Task::factory()->for($user)->count(4)->sequence(
            ['title' => 'Bald A', 'due_date' => '2026-09-18'],
            ['title' => 'Bald B', 'due_date' => '2026-09-19'],
            ['title' => 'Bald C', 'due_date' => '2026-09-20'],
            ['title' => 'Bald D', 'due_date' => '2026-09-21'],
        )->create();
        $this->actingAs($user);

        $page = Livewire::test(DayPreview::class);

        $due = $page->instance()->due();
        $this->assertSame(6, $due['count']);
        $this->assertSame('overdue', $due['items'][0]['bucket']);
        $this->assertSame('today', $due['items'][1]['bucket']);
        $this->assertTrue($due['hasMore']);
        $this->assertSame(3, $due['moreCount']);
        $page->assertSee('6')->assertSee('+3 weitere');
    }

    public function test_hides_the_schedule_card_when_the_module_is_hidden(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'hidden_modules' => ['schedule']]);
        ScheduleEvent::factory()->for($user)->on('2026-09-17')->create(['title' => 'Schule']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)->assertDontSee('Zeitplan heute');
    }

    public function test_hides_the_agenda_tile_and_drops_homework_from_due_when_the_module_is_hidden(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'hidden_modules' => ['agenda']]);
        AgendaEntry::factory()->for($user)->homework()->create(['title' => 'Verstecktes Hausaufgabe', 'date' => '2026-09-17']);
        $this->actingAs($user);

        $page = Livewire::test(DayPreview::class);

        $this->assertNull($page->instance()->agenda());
        $page->assertDontSee('Verstecktes Hausaufgabe');
    }

    public function test_every_tile_is_a_real_link_to_its_source_page(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($user)->create(['deadline' => '2026-09-17']);
        AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-09-17']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)
            ->assertSeeHtml(route('app'))
            ->assertSeeHtml(route('agenda'))
            ->assertSeeHtml(route('progress'));
    }

    public function test_shows_todays_schedule_blocks(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        ScheduleEvent::factory()->for($user)->on('2026-09-17')->at('08:00', '12:00')->create(['title' => 'Schule']);
        ScheduleEvent::factory()->for($user)->on('2026-09-18')->create(['title' => 'Morgen erst']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)
            ->assertSee('Zeitplan heute')
            ->assertSee('Schule')
            ->assertDontSee('Morgen erst');
    }

    public function test_reads_is_today_flat_regardless_of_list(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        // Deliberately violates the normal "no is_today task sits in inbox" shape —
        // proves the read is concept-agnostic (flat over is_today) rather than list-aware.
        Task::factory()->for($user)->create(['list' => 'inbox', 'is_today' => true, 'title' => 'Flach gelesen']);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)->assertSee('Flach gelesen');
    }

    public function test_never_shows_another_users_data(): void
    {
        Carbon::setTestNow('2026-09-17 09:00:00');
        $other = User::factory()->create(['timezone_offset' => 0]);
        Task::factory()->for($other)->today()->create(['title' => 'Fremde Aufgabe']);
        ScheduleEvent::factory()->for($other)->on('2026-09-17')->create(['title' => 'Fremder Termin']);

        $user = User::factory()->create(['timezone_offset' => 0]);
        $this->actingAs($user);

        Livewire::test(DayPreview::class)
            ->assertDontSee('Fremde Aufgabe')
            ->assertDontSee('Fremder Termin');
    }
}
