<?php

namespace Tests\Feature;

use App\Livewire\EmergencyMode;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskSuggestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmergencyModeTest extends TestCase
{
    use RefreshDatabase;

    // ── Picking & arranging ─────────────────────────────────────────

    public function test_selecting_a_project_defaults_uncategorized_active_tasks_to_the_tasks_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        $done = Task::factory()->for($user)->completed()->create(['list' => 'projects', 'project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->assertSet('selectedProjectId', $project->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'emergency_list' => 'tasks']);
        // Completed tasks are never part of the sequence, so they're left untouched.
        $this->assertDatabaseHas('tasks', ['id' => $done->id, 'emergency_list' => null]);
    }

    public function test_selecting_a_project_does_not_reset_an_already_categorized_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'emergency_list' => 'inbox',
        ]);

        Livewire::actingAs($user)->test(EmergencyMode::class)->call('selectProject', $project->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'emergency_list' => 'inbox']);
    }

    public function test_a_user_cannot_select_another_users_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignProject = Project::factory()->for($other)->create();

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $foreignProject->id)
            ->assertSet('selectedProjectId', null);
    }

    public function test_a_new_step_can_be_added_while_arranging(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->set('newTitle', 'Kontaktformular verkabeln')
            ->call('addTask')
            ->assertHasNoErrors()
            ->assertSet('newTitle', '');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'list' => 'projects',
            'emergency_list' => 'tasks',
            'title' => 'Kontaktformular verkabeln',
        ]);
    }

    public function test_new_steps_are_appended_after_the_existing_sequence(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'sort_order' => 5]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->set('newTitle', 'Neuer Schritt')
            ->call('addTask');

        $this->assertDatabaseHas('tasks', ['title' => 'Neuer Schritt', 'sort_order' => 6]);
    }

    public function test_a_task_can_be_reassigned_to_a_different_board_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'emergency_list' => 'tasks',
        ]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->call('setTaskList', $task->id, 'inbox');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'emergency_list' => 'inbox']);
    }

    public function test_an_invalid_column_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'emergency_list' => 'tasks',
        ]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->call('setTaskList', $task->id, 'projects');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'emergency_list' => 'tasks']);
    }

    public function test_a_task_outside_the_selected_project_cannot_be_recategorized(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $taskInB = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $projectB->id, 'emergency_list' => 'tasks',
        ]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $projectA->id)
            ->call('setTaskList', $taskInB->id, 'inbox');

        $this->assertDatabaseHas('tasks', ['id' => $taskInB->id, 'emergency_list' => 'tasks']);
    }

    public function test_reordering_persists_the_new_sequence(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $first = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'sort_order' => 0]);
        $second = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'sort_order' => 1]);
        $third = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'sort_order' => 2]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->call('reorderTasks', [$third->id, $first->id, $second->id]);

        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'sort_order' => 2]);
    }

    public function test_the_query_string_preselects_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Website Relaunch']);

        $this->actingAs($user)
            ->get('/app/emergency?project='.$project->id)
            ->assertOk()
            ->assertSee('Website Relaunch');
    }

    public function test_mount_falls_back_to_the_currently_active_emergency_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Website Relaunch']);
        $user->update(['emergency_project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->assertSet('selectedProjectId', $project->id);
    }

    public function test_back_to_picker_deselects_without_ending_emergency_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('backToPicker')
            ->assertSet('selectedProjectId', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'emergency_project_id' => $project->id]);
    }

    // ── Starting & ending ───────────────────────────────────────────

    public function test_starting_emergency_mode_marks_the_project_active_and_redirects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->call('start')
            ->assertRedirect(route('app'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'emergency_project_id' => $project->id]);
    }

    public function test_ending_emergency_mode_clears_the_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(EmergencyMode::class)
            ->call('selectProject', $project->id)
            ->call('end')
            ->assertRedirect(route('app'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'emergency_project_id' => null]);
    }

    public function test_the_dashboard_can_also_end_emergency_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('endEmergencyMode');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'emergency_project_id' => null]);
    }

    // ── Dashboard filtering ─────────────────────────────────────────

    public function test_dashboard_columns_include_the_emergency_projects_tasks_in_sequence(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $first = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'emergency_list' => 'todos', 'sort_order' => 0, 'title' => 'Erstes',
        ]);
        $second = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'emergency_list' => 'todos', 'sort_order' => 1, 'title' => 'Zweites',
        ]);

        $ordered = Livewire::actingAs($user)->test(TaskBoard::class)->instance()->emergencyTasksFor('todos');

        $this->assertSame([$first->id, $second->id], $ordered->pluck('id')->all());
    }

    public function test_dashboard_separates_important_tasks_from_the_collapsed_rest(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $important = Task::factory()->for($user)->todos()->important()->create(['title' => 'Zahnarzttermin']);
        // A plain, unrelated to-do: not important, not part of the emergency
        // project — it should collapse behind the "N weitere" disclosure
        // rather than clutter the emergency-focused view.
        Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->assertSeeHtml('wire:key="task-'.$important->id.'"')
            ->assertSee('1 weitere Aufgabe ausgeblendet');
    }

    public function test_dashboard_is_unfiltered_when_emergency_mode_is_not_active(): void
    {
        $user = User::factory()->create();
        $ordinary = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->assertSeeHtml('wire:key="task-'.$ordinary->id.'"');
    }

    // ── Focus-timer suggestion ──────────────────────────────────────

    public function test_the_next_emergency_step_is_suggested_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $next = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'sort_order' => 0, 'title' => 'Als Nächstes',
        ]);
        Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'sort_order' => 1, 'title' => 'Später',
        ]);
        // A same-cycle "today" task exists too, but the emergency sequence still wins.
        Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user->fresh(), 1, 1);

        $this->assertSame('emergency', $suggestion['kind']);
        $this->assertSame($next->id, $suggestion['task_id']);
        $this->assertSame($project->name, $suggestion['subtitle']);
    }

    public function test_suggestion_falls_back_once_the_emergency_project_is_finished(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        Task::factory()->for($user)->completed()->create(['list' => 'projects', 'project_id' => $project->id]);
        $today = Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user->fresh(), 2, 1);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($today->id, $suggestion['task_id']);
    }
}
