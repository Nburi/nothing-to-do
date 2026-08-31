<?php

namespace Tests\Feature;

use App\Livewire\Settings;
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

    public function test_set_list_concept_is_a_no_op_for_an_unavailable_concept(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('setListConcept', 'kanban');

        $this->assertSame('three_things', $user->fresh()->list_concept);
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

        // 'three_things' is currently the only available concept — re-selecting
        // it is still a legitimate, idempotent write and must succeed.
        Livewire::actingAs($user)->test(Settings::class)
            ->call('setListConcept', 'three_things')
            ->assertSet('listConcept', 'three_things');

        $this->assertSame('three_things', $user->fresh()->list_concept);
    }

    public function test_settings_renders_the_list_concept_card(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('Listen-Konzept')
            ->assertSee('Bald verfügbar');
    }
}
