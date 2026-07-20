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
}
