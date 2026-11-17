<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 4.4, the Sunday review screen (tests A48–A50): the page in both layouts with its empty
 * states (A48), the quiet line on the board on Sunday and Monday only (A49), English (A50).
 */
class SundayReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    private function at(string $local): void
    {
        $this->travelTo(CarbonImmutable::parse($local, 'Europe/Zurich'));
    }

    private function deepWork(): void
    {
        $category = EventCategory::factory()->for($this->user)->pomodoro()->create();
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $category->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    private function task(array $attributes): Task
    {
        return Task::factory()->for($this->user)->create(['list' => 'tasks', ...$attributes]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/board/review')->assertRedirect('/login');
    }

    public function test_a_full_week_shows_both_halves(): void
    {
        $this->at('2026-11-22 20:00');
        $this->deepWork();
        $this->task(['title' => 'Folien fertig', 'is_completed' => true, 'completed_at' => now()->subDays(3)]);
        $this->task(['title' => 'Bewerbung abschicken', 'soft_deadline' => '2026-11-20']);
        $this->task(['title' => 'Bericht abgeben', 'hard_deadline' => '2026-11-26', 'size' => Size::Long]);

        $this->actingAs($this->user)->get('/board/review')
            ->assertOk()
            ->assertSee(['Deine Woche', 'Sonntag, 22. November', 'Woche 16.–22. November'])
            ->assertSee('1 erledigt. Deine erste Woche mit erledigten Sachen.')
            ->assertSee(['Diese Woche', 'Nächste Woche', 'Geschafft', 'Folien fertig'])
            ->assertSee(['Geht in die nächste Woche mit', 'Bewerbung abschicken', 'bis Fr 20.11.'])
            ->assertSee(['Fällig', 'Bericht abgeben', 'fix Do 26.11.'])
            ->assertSee(['von 20 Sessions sind schon verplant.', 'Platz in Pomodoro-Blöcken', 'Woche anpassen', 'Zurück zum Board'])
            ->assertDontSee('data-review-tight', false)
            // Both layouts in one render: Werkbank top bar and the Stapel tabs.
            ->assertSee(['Ideen', 'Kalender', 'Später', 'Inbox']);
    }

    public function test_an_empty_week_without_blocks_has_calm_empty_states(): void
    {
        $this->at('2026-11-22 20:00');

        $this->actingAs($this->user)->get('/board/review')
            ->assertOk()
            ->assertSee(['Eine stille Woche. Nichts erledigt, nichts verloren.', 'Diese Woche ist nichts erledigt.'])
            ->assertSee(['Noch keine Arbeitsblöcke.', 'Woche planen'])
            ->assertDontSee(['Geschafft', 'Geht in die nächste Woche mit', 'Fällig']);
    }

    public function test_an_overloaded_day_is_named_calmly_and_links_to_the_crunch_screen(): void
    {
        $this->at('2026-11-22 20:00');
        $this->deepWork();
        $this->task(['title' => 'Bericht', 'hard_deadline' => '2026-11-24', 'size' => Size::Block, 'duration_minutes' => 400]);

        $this->actingAs($this->user)->get('/board/review')
            ->assertOk()
            ->assertSee('data-review-tight', false)
            ->assertSee(['Dienstag ist voller als Platz ist', 'Die Frühwarnung meldet sich rechtzeitig.'])
            ->assertSee(route('shell.board', ['crunch' => 1]), false);
    }

    public function test_on_monday_it_looks_back_at_last_week(): void
    {
        $this->at('2026-11-23 08:00');

        $this->actingAs($this->user)->get('/board/review')
            ->assertOk()
            ->assertSee(['Deine letzte Woche', 'Letzte Woche', 'Woche 16.–22. November'])
            ->assertDontSee('Nächste Woche');
    }

    public function test_the_board_shows_the_quiet_line_on_sunday_and_monday_only(): void
    {
        $this->actingAs($this->user);

        $this->at('2026-11-22 18:00');
        $this->get('/board')->assertOk()->assertSee('Sonntag. Deine Woche in Ruhe ansehen')->assertSee(route('shell.review'), false);

        $this->at('2026-11-23 18:00');
        $this->get('/board')->assertOk()->assertSee('Deine letzte Woche in Ruhe ansehen');

        $this->at('2026-11-24 18:00');
        $this->get('/board')->assertOk()->assertDontSee('data-review-line', false);
    }

    public function test_the_line_follows_the_users_local_date(): void
    {
        // Sunday 23:30 UTC is already Monday 00:30 in Zurich.
        $this->travelTo(CarbonImmutable::parse('2026-11-22 23:30', 'UTC'));
        $this->actingAs($this->user)->get('/board')->assertSee('Deine letzte Woche in Ruhe ansehen');

        // Saturday 23:30 UTC is Sunday 00:30 in Zurich.
        $this->travelTo(CarbonImmutable::parse('2026-11-21 23:30', 'UTC'));
        $this->get('/board')->assertSee('Sonntag. Deine Woche in Ruhe ansehen');
    }

    public function test_it_speaks_english(): void
    {
        $this->at('2026-11-22 20:00');
        app()->setLocale('en'); // no per-user language setting yet; the app locale decides

        $this->actingAs($this->user)->get('/board/review')
            ->assertOk()
            ->assertSee(['Your week', 'A quiet week. Nothing done, nothing lost.', 'Next week', 'No work blocks yet.']);
    }
}
