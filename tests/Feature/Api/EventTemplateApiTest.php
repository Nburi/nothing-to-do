<?php

namespace Tests\Feature\Api;

use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_templates(): void
    {
        $user = User::factory()->create();
        EventTemplate::factory()->for($user)->create(['name' => 'Zahnarzt']);
        Sanctum::actingAs($user);

        $this->getJson('/api/event-templates')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Zahnarzt');
    }

    public function test_applying_a_template_creates_a_concrete_event(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->for($user)->create([
            'name' => 'Lauftraining', 'duration' => 60, 'default_start' => '17:00',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/event-templates/{$template->id}/apply", ['date' => '2026-07-10']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Lauftraining')
            ->assertJsonPath('data.start_time', '17:00')
            ->assertJsonPath('data.end_time', '18:00');

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'title' => 'Lauftraining',
        ]);
        $this->assertSame('2026-07-10', $user->scheduleEvents()->first()->date->toDateString());
    }

    public function test_it_deletes_a_template(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/event-templates/{$template->id}")->assertNoContent();
        $this->assertDatabaseMissing('event_templates', ['id' => $template->id]);
    }
}
