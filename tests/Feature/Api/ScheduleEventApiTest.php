<?php

namespace Tests\Feature\Api;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_termin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/schedule-events', [
            'kind' => 'appointment',
            'title' => 'Zahnarzt',
            'color' => 'signal',
            'date' => '2026-07-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Zahnarzt');
        $this->assertDatabaseHas('schedule_events', ['user_id' => $user->id, 'title' => 'Zahnarzt']);
    }

    public function test_it_creates_a_category_block(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/schedule-events', [
            'kind' => 'category',
            'category_id' => $category->id,
            'date' => '2026-07-10',
            'start_time' => '17:00',
            'end_time' => '18:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'category')
            ->assertJsonPath('data.title', 'Training');
    }

    public function test_index_defaults_to_today_and_materializes_recurring_series(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $user->eventTemplates()->create([
            'name' => 'Schule',
            'color' => 'contour',
            'duration' => 60,
            'default_start' => '08:00',
            'is_recurring' => true,
            'recurrence' => (string) now()->dayOfWeekIso,
        ]);

        $response = $this->getJson('/api/schedule-events');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->pluck('title')->contains('Schule'));
    }

    public function test_update_moves_and_resizes_an_event(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/schedule-events/{$event->id}", [
            'start_time' => '10:00',
            'end_time' => '11:30',
        ])->assertOk();

        $event->refresh();
        $this->assertSame('10:00', $event->start_time);
        $this->assertSame('11:30', $event->end_time);
    }

    public function test_update_rejects_end_before_start(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/schedule-events/{$event->id}", [
            'start_time' => '10:00',
            'end_time' => '09:00',
        ])->assertStatus(422);
    }

    public function test_moving_a_recurring_occurrence_to_another_day_tombstones_the_original(): void
    {
        $user = User::factory()->create();
        $template = $user->eventTemplates()->create([
            'name' => 'Schule', 'color' => 'contour', 'duration' => 60,
            'default_start' => '08:00', 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
        ]);
        $event = $user->scheduleEvents()->create([
            'template_id' => $template->id, 'title' => 'Schule', 'color' => 'contour',
            'date' => '2026-07-06', 'start_time' => '08:00', 'end_time' => '09:00',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/schedule-events/{$event->id}", ['date' => '2026-07-07'])->assertOk();

        $event->refresh();
        $this->assertNull($event->template_id);
        $this->assertSame('2026-07-07', $event->date->toDateString());

        $tombstone = ScheduleEvent::where('template_id', $template->id)->where('is_cancelled', true)->first();
        $this->assertNotNull($tombstone);
        $this->assertSame('2026-07-06', $tombstone->date->toDateString());
    }

    public function test_delete_cancels_a_recurring_occurrence_but_removes_a_one_off(): void
    {
        $user = User::factory()->create();
        $oneOff = ScheduleEvent::factory()->for($user)->create();
        $template = $user->eventTemplates()->create([
            'name' => 'Schule', 'color' => 'contour', 'duration' => 60,
            'default_start' => '08:00', 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
        ]);
        $recurring = $user->scheduleEvents()->create([
            'template_id' => $template->id, 'title' => 'Schule', 'color' => 'contour',
            'date' => '2026-07-06', 'start_time' => '08:00', 'end_time' => '09:00',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/schedule-events/{$oneOff->id}")->assertNoContent();
        $this->assertDatabaseMissing('schedule_events', ['id' => $oneOff->id]);

        $this->deleteJson("/api/schedule-events/{$recurring->id}")->assertNoContent();
        $this->assertDatabaseHas('schedule_events', ['id' => $recurring->id, 'is_cancelled' => true]);
    }

    public function test_start_focus_requires_a_pomodoro_enabled_category(): void
    {
        $user = User::factory()->create();
        $plainCategory = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $plainCategory->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/start-focus")->assertStatus(422);
        $this->assertNull($event->fresh()->pomodoro_started_at);
    }

    public function test_start_and_stop_focus_timer(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/start-focus")->assertOk();
        $event->refresh();
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);

        $this->postJson("/api/schedule-events/{$event->id}/stop-focus")->assertOk();
        $event->refresh();
        $this->assertNull($event->pomodoro_started_at);
        $this->assertNull($event->pomodoro_phase);
    }

    public function test_continue_focus_manually_advances_a_frozen_session(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->create([
            'category_id' => $category->id,
            'pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/continue-focus")->assertOk();

        $event->refresh();
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_continue_focus_requires_a_started_session(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/continue-focus")->assertStatus(422);
    }

    public function test_skip_focus_break_jumps_to_the_next_work_cycle(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->create([
            'category_id' => $category->id,
            'pomodoro_phase' => 'short_break', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/skip-focus-break")->assertOk();

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
    }

    public function test_skip_focus_break_rejects_when_the_upcoming_phase_is_not_a_break(): void
    {
        $user = User::factory()->create(['pomodoro_work' => 25]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->create([
            'category_id' => $category->id,
            'pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/schedule-events/{$event->id}/skip-focus-break")->assertStatus(422);
    }

    public function test_focus_endpoint_returns_null_session_when_nothing_is_running(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/schedule-events/focus')
            ->assertOk()
            ->assertJson(['focus_session' => null, 'phase' => null, 'suggestion' => null]);
    }
}
