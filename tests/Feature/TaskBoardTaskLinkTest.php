<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The schedule-event task link's signature moment lands here: a second tap on
 * a linked block's icon (ManagesSchedule::navigateToLinkedTask()) sends the
 * user to /app?task={id}, and TaskBoard::mount() is what turns that into the
 * task's edit sheet actually being open on arrival. A full HTTP GET (not
 * Livewire::test()) is needed — mount() reads the real request's query
 * string, which Livewire::test() never populates (see ScheduleBadgeHighlightTest,
 * the same pattern for the Zeitplan's own ?event= deep link).
 */
class TaskBoardTaskLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_with_an_owned_task_id_opens_its_edit_sheet(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create(['title' => 'Testlauf mit Tempowechseln']);

        $response = $this->actingAs($user)->get('/app?task='.$task->id);

        $response->assertOk();
        $response->assertSee('Aufgabe bearbeiten');
        $response->assertSee('Testlauf mit Tempowechseln');
    }

    public function test_a_foreign_task_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();

        $response = $this->actingAs($user)->get('/app?task='.$foreignTask->id);

        $response->assertOk();
        $response->assertDontSee('Aufgabe bearbeiten');
    }

    public function test_a_nonexistent_task_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app?task=999999');

        $response->assertOk();
        $response->assertDontSee('Aufgabe bearbeiten');
    }

    public function test_a_non_numeric_task_param_is_silently_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app?task=not-a-number');

        $response->assertOk();
        $response->assertDontSee('Aufgabe bearbeiten');
    }

    public function test_visiting_without_a_task_param_behaves_normally(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create();

        $response = $this->actingAs($user)->get('/app');

        $response->assertOk();
        $response->assertDontSee('Aufgabe bearbeiten');
    }
}
