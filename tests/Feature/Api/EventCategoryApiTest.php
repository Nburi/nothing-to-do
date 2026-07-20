<?php

namespace Tests\Feature\Api;

use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_lists_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/event-categories', ['name' => 'Lesen', 'color' => 'forest'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lesen');

        $this->getJson('/api/event-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_renames_and_recolours_a_category(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/event-categories/{$category->id}", [
            'name' => 'Mobility',
            'color' => 'signal',
        ])->assertOk();

        $category->refresh();
        $this->assertSame('Mobility', $category->name);
        $this->assertSame('signal', $category->color);
    }

    public function test_it_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/event-categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('event_categories', ['id' => $category->id]);
    }

    public function test_a_category_belonging_to_another_user_cannot_be_modified(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = EventCategory::factory()->for($owner)->create();
        Sanctum::actingAs($other);

        $this->patchJson("/api/event-categories/{$category->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->assertDatabaseMissing('event_categories', ['id' => $category->id, 'name' => 'Hijacked']);
    }
}
