<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Livewire\WeekPlan;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Weg-/Pufferzeit: the minutes before and after an entry during which you are
 * travelling or otherwise not available.
 */
class ScheduleBufferTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    // ── The entry itself ─────────────────────────────────────────────

    public function test_an_entry_without_travel_time_behaves_exactly_as_before(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create();

        $this->assertSame(0, (int) $event->buffer_before);
        $this->assertSame($event->startMinutes(), $event->occupiedStartMinutes());
        $this->assertSame($event->endMinutes(), $event->occupiedEndMinutes());
        $this->assertSame('08:00', $event->departureTime());
    }

    public function test_the_footprint_is_wider_than_the_entry(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('17:30', '19:00')
            ->create(['buffer_before' => 20, 'buffer_after' => 15]);

        $this->assertSame(17 * 60 + 10, $event->occupiedStartMinutes());
        $this->assertSame(19 * 60 + 15, $event->occupiedEndMinutes());
        $this->assertSame('17:10', $event->departureTime());
        // The entry's own duration is untouched — the Pomodoro timer, the focus
        // strip and the Planer's capacity all still read that, and nothing is
        // worked on while travelling.
        $this->assertSame(90, $event->durationMinutes());
    }

    public function test_the_footprint_is_clamped_to_the_day(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('00:10', '23:50')
            ->create(['buffer_before' => 60, 'buffer_after' => 60]);

        $this->assertSame(0, $event->occupiedStartMinutes());
        $this->assertSame(1440, $event->occupiedEndMinutes());
    }

    // ── The form ─────────────────────────────────────────────────────

    public function test_the_event_form_saves_the_travel_time(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('openEventForm', '2026-09-21')
            ->set('eventTitle', 'Training')
            ->set('eventStart', '17:30')
            ->set('eventEnd', '19:00')
            ->set('eventBufferBefore', 20)
            ->set('eventBufferAfter', 15)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->sole();
        $this->assertSame(20, (int) $event->buffer_before);
        $this->assertSame(15, (int) $event->buffer_after);
    }

    public function test_the_event_form_seeds_the_travel_time_when_editing(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->create(['buffer_before' => 25, 'buffer_after' => 5]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->assertSet('eventBufferBefore', 25)
            ->assertSet('eventBufferAfter', 5);
    }

    public function test_a_negative_or_absurd_travel_time_is_refused(): void
    {
        $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('openEventForm', '2026-09-21')
            ->set('eventTitle', 'Training')
            ->set('eventBufferBefore', -5)
            ->call('saveEventForm')
            ->assertHasErrors('eventBufferBefore');

        Livewire::test(Schedule::class)
            ->call('openEventForm', '2026-09-21')
            ->set('eventTitle', 'Training')
            ->set('eventBufferAfter', 999)
            ->call('saveEventForm')
            ->assertHasErrors('eventBufferAfter');
    }

    public function test_the_form_resets_the_travel_time_between_entries(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->create(['buffer_before' => 40]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->assertSet('eventBufferBefore', 40)
            ->call('openEventForm', '2026-09-21')
            ->assertSet('eventBufferBefore', 0);
    }

    // ── Recurring blocks carry it ────────────────────────────────────

    public function test_a_recurring_block_stores_its_travel_time_on_the_template(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('openEventForm', '2026-09-23')  // a Wednesday
            ->set('eventTitle', 'Training')
            ->set('eventStart', '17:30')
            ->set('eventEnd', '19:00')
            ->set('eventBufferBefore', 20)
            ->set('eventRecurring', true)
            ->set('eventDays', [3])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $template = EventTemplate::forUser($user)->sole();
        $this->assertSame(20, (int) $template->buffer_before);
    }

    public function test_materialising_a_series_carries_the_travel_time_onto_every_occurrence(): void
    {
        $user = $this->actingUser();
        EventTemplate::factory()->for($user)->create([
            'name' => 'Training',
            'duration' => 90,
            'default_start' => '17:30',
            'is_recurring' => true,
            'recurrence' => '1,3',
            'buffer_before' => 20,
            'buffer_after' => 10,
        ]);

        ScheduleEvent::materializeRange($user, Carbon::parse('2026-09-21'), Carbon::parse('2026-09-27'));

        $occurrences = ScheduleEvent::forUser($user)->get();
        $this->assertCount(2, $occurrences);
        $occurrences->each(function (ScheduleEvent $occurrence) {
            $this->assertSame(20, (int) $occurrence->buffer_before);
            $this->assertSame(10, (int) $occurrence->buffer_after);
        });
    }

    public function test_editing_a_weekplan_block_propagates_the_travel_time_to_future_occurrences(): void
    {
        // Monday, so the Wednesday occurrence below is strictly in the future —
        // refreshMaterializedOccurrences() deliberately never rewrites today or
        // the past, and deletes an occurrence on a weekday the series dropped.
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = $this->actingUser();
        $template = EventTemplate::factory()->for($user)->create([
            'name' => 'Training',
            'duration' => 90,
            'default_start' => '17:30',
            'is_recurring' => true,
            'recurrence' => '3',
        ]);
        $future = ScheduleEvent::factory()->for($user)
            ->on('2026-09-23')
            ->at('17:30', '19:00')
            ->create(['template_id' => $template->id, 'title' => 'Training']);

        Livewire::test(WeekPlan::class)
            ->call('startEditEvent', $template->id)
            ->set('eventBufferBefore', 25)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertSame(25, (int) $template->fresh()->buffer_before);
        $this->assertSame(25, (int) $future->fresh()->buffer_before);
    }

    public function test_applying_a_template_carries_its_travel_time_onto_the_placed_copy(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->for($user)->create([
            'name' => 'Zahnarzt',
            'duration' => 45,
            'default_start' => '10:00',
            'is_recurring' => false,
            'buffer_before' => 30,
            'buffer_after' => 30,
        ]);

        Livewire::test(Schedule::class)->call('applyTemplate', $template->id, '2026-09-21');

        $event = ScheduleEvent::forUser($user)->sole();
        $this->assertSame(30, (int) $event->buffer_before);
        $this->assertSame(30, (int) $event->buffer_after);
    }

    // ── Rendering ────────────────────────────────────────────────────

    public function test_the_travel_time_renders_as_a_hatched_band_with_a_departure_time(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('17:30', '19:00')->create(['title' => 'Training', 'buffer_before' => 20, 'buffer_after' => 15]);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        $response->assertSee('tl-buf tl-buf-before', false);
        $response->assertSee('tl-buf tl-buf-after', false);
        // Never colour-only: the band names itself and says when to leave.
        $response->assertSee('los um 17:10', false);
        $response->assertSee('zurück um 19:15', false);
    }

    public function test_an_entry_without_travel_time_renders_no_band_at_all(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('17:30', '19:00')->create(['title' => 'Training']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertDontSee('tl-buf', false);
    }

    // ── The push counts it ───────────────────────────────────────────

    public function test_the_upcoming_push_fires_early_enough_to_actually_leave(): void
    {
        // 17:30 start, 20 minutes of travel: the heads-up is due at 17:05, not 17:25.
        Carbon::setTestNow('2026-07-10 17:05:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('17:30', '19:00')
            ->create(['title' => 'Training', 'buffer_before' => 20]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->withArgs(
                fn (User $u, array $payload) => str_contains($payload['body'], 'Los in 5 Minuten')
                    && str_contains($payload['body'], 'beginnt um 17:30')
            );
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_upcoming_at);
    }

    public function test_without_travel_time_the_upcoming_push_is_unchanged(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')
            ->create(['title' => 'Training']);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->withArgs(
                fn (User $u, array $payload) => str_contains($payload['body'], 'beginnt in 5 Minuten')
            );
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();
    }

    public function test_a_travel_time_does_not_make_the_push_fire_before_it_is_due(): void
    {
        // Due at 17:05; it is 16:30.
        Carbon::setTestNow('2026-07-10 16:30:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('17:30', '19:00')
            ->create(['title' => 'Training', 'buffer_before' => 20]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_upcoming_at);
    }

    // ── API ──────────────────────────────────────────────────────────

    public function test_the_api_reports_the_travel_time(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->create(['buffer_before' => 20, 'buffer_after' => 10]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/schedule-events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.buffer_before', 20)
            ->assertJsonPath('data.buffer_after', 10);
    }
}
