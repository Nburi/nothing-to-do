<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The `@switch` seam in task-board.blade.php (see App\Services\ListConcepts
 * and CLAUDE.md's "To-Do-Listen-Konzepte") — this is the regression proof
 * that extracting the board markup into partials/board-three-things.blade.php
 * changed nothing observable.
 */
class BoardListConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_listconcept_defaults_to_three_things(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->assertSet('listConcept', 'three_things');
    }

    public function test_the_board_still_renders_a_real_task_via_the_switch_seam(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Karten für Regio-OL drucken']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertSee('Karten für Regio-OL drucken');
    }

    public function test_a_stored_value_for_an_unbuilt_concept_still_renders_the_three_things_board(): void
    {
        // 'simple' is a real catalog key but not available on this branch.
        $user = User::factory()->create(['list_concept' => 'simple']);
        Task::factory()->for($user)->inbox()->create(['title' => 'Karten für Regio-OL drucken']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSet('listConcept', 'three_things')
            ->assertSee('Karten für Regio-OL drucken');
    }

    public function test_a_user_on_kanban_renders_the_kanban_board(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);
        Task::factory()->for($user)->create(['title' => 'Karten für Regio-OL drucken', 'list' => 'todos']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSet('listConcept', 'kanban')
            ->assertSee('Karten für Regio-OL drucken')
            ->assertSee('Backlog')
            ->assertSee('In Arbeit')
            ->assertSee('Erledigt');
    }
}
