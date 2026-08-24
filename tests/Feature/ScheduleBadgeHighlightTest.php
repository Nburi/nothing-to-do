<?php

namespace Tests\Feature;

use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Zeitplan" header badge's signature moment: its ?event= link doesn't
 * just navigate to the Zeitplan, the exact block it was showing arrives
 * already highlighted (see Schedule::$highlightEventId, HeaderBadges::scheduleBadge()).
 * A full HTTP GET (not Livewire::test()) is needed here — the query string
 * is read from the real request in Schedule::mount(), which Livewire::test()
 * never populates.
 */
class ScheduleBadgeHighlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_with_an_owned_event_id_highlights_that_block(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->on('2026-08-24')->create();

        $response = $this->actingAs($user)->get('/app/schedule?event='.$event->id);

        $response->assertOk();
        $response->assertSee('badge-jump-highlight', false);
    }

    public function test_it_jumps_to_the_events_own_date_even_outside_the_current_week(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->on('2026-09-14')->create();

        $response = $this->actingAs($user)->get('/app/schedule?event='.$event->id);

        $response->assertOk();
        $response->assertSee('badge-jump-highlight', false);
    }

    public function test_a_foreign_events_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();
        $foreignEvent = ScheduleEvent::factory()->for(User::factory())->on('2026-08-24')->create();

        $response = $this->actingAs($user)->get('/app/schedule?event='.$foreignEvent->id);

        $response->assertOk();
        $response->assertDontSee('badge-jump-highlight', false);
    }

    public function test_a_nonexistent_event_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/schedule?event=999999');

        $response->assertOk();
        $response->assertDontSee('badge-jump-highlight', false);
    }

    public function test_a_non_numeric_event_param_is_silently_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/schedule?event=not-a-number');

        $response->assertOk();
        $response->assertDontSee('badge-jump-highlight', false);
    }
}
