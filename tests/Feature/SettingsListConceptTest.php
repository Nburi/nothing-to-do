<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsListConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_never_customised_user_shows_three_things_as_current(): void
    {
        $user = User::factory()->create();

        $rows = collect(Livewire::actingAs($user)->test(Settings::class)->get('listConceptRows'))->keyBy('key');

        $this->assertTrue($rows['three_things']['current']);
    }

    public function test_set_list_concept_is_a_no_op_for_a_garbage_key(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('setListConcept', 'not-a-real-concept');

        $this->assertSame('three_things', $user->fresh()->list_concept);
    }

    public function test_set_list_concept_persists_an_available_choice(): void
    {
        $user = User::factory()->create();

        // Re-selecting the current choice is still a legitimate, idempotent
        // write and must succeed.
        Livewire::actingAs($user)->test(Settings::class)
            ->call('setListConcept', 'three_things')
            ->assertSet('listConcept', 'three_things');

        $this->assertSame('three_things', $user->fresh()->list_concept);
    }

    public function test_set_list_concept_persists_simple(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('setListConcept', 'simple')
            ->assertSet('listConcept', 'simple');

        $this->assertSame('simple', $user->fresh()->list_concept);
    }

    public function test_set_list_concept_persists_eisenhower(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('setListConcept', 'eisenhower')
            ->assertSet('listConcept', 'eisenhower');

        $this->assertSame('eisenhower', $user->fresh()->list_concept);
    }

    public function test_set_list_concept_persists_kanban(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('setListConcept', 'kanban')
            ->assertSet('listConcept', 'kanban');

        $this->assertSame('kanban', $user->fresh()->list_concept);
    }

    public function test_settings_renders_the_list_concept_card(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('Listen-Konzept')
            ->assertSee('Eisenhower-Matrix')
            ->assertSee('Kanban');
    }

    public function test_simple_row_previews_the_users_own_real_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Regio-OL Karten drucken']);

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('Regio-OL Karten drucken');
    }

    public function test_eisenhower_row_previews_the_users_own_real_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'Regio-OL Karten drucken', 'list' => 'tasks']);

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('Regio-OL Karten drucken');
    }

    public function test_kanban_row_shows_a_real_data_preview_thumbnail(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'Karten für Regio-OL drucken', 'list' => 'todos']);

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('Backlog')
            ->assertSee('In Arbeit')
            ->assertSee('Karten für Regio-OL drucken');
    }
}
