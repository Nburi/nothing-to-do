<?php

namespace Tests\Feature;

use App\Livewire\ProjectPage;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_from_the_board(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->set('newProjectName', '  Maturaarbeit  ')
            ->call('addProject')
            ->assertHasNoErrors()
            ->assertSet('newProjectName', '');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Maturaarbeit',
        ]);
    }

    public function test_a_blank_project_name_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->set('newProjectName', '   ')
            ->call('addProject')
            ->assertHasErrors(['newProjectName' => 'required']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_tasks_are_not_rendered_as_board_task_cards(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $boardTask = Task::factory()->for($user)->todos()->create(['title' => 'BOARD-VISIBLE-TASK']);
        $projectTask = Task::factory()->for($user)->create([
            'title' => 'PROJECT-HIDDEN-TASK',
            'list' => 'projects',
            'project_id' => $project->id,
        ]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            // The board task is a draggable card; the project task is not.
            ->assertSeeHtml('wire:key="task-'.$boardTask->id.'"')
            ->assertDontSeeHtml('wire:key="task-'.$projectTask->id.'"');
    }

    public function test_project_tasks_do_not_count_toward_the_board_lists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Task::factory()->for($user)->todos()->create();
        Task::factory()->for($user)->create([
            'list' => 'projects',
            'project_id' => $project->id,
        ]);

        $component = Livewire::actingAs($user)->test(TaskBoard::class);

        $this->assertSame(1, $component->instance()->counts['todos']);
        $this->assertSame(1, $component->instance()->counts['projects']);
    }

    public function test_tasks_can_be_added_inside_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->set('newTitle', 'Kapitel 1 schreiben')
            ->call('addTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'list' => 'projects',
            'title' => 'Kapitel 1 schreiben',
        ]);
    }

    public function test_an_inbox_task_can_be_pulled_into_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->call('assignToProject', $task->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'list' => 'projects',
        ]);
    }

    public function test_a_task_can_be_released_back_to_the_inbox(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects',
            'project_id' => $project->id,
        ]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->call('removeFromProject', $task->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => null,
            'list' => 'inbox',
        ]);
    }

    public function test_deleting_a_project_returns_active_tasks_to_the_inbox(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $active = Task::factory()->for($user)->create([
            'list' => 'projects',
            'project_id' => $project->id,
        ]);
        $done = Task::factory()->for($user)->completed()->create([
            'list' => 'projects',
            'project_id' => $project->id,
        ]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->call('deleteProject')
            ->assertRedirect(route('app'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        // The active task is freed back to the inbox.
        $this->assertDatabaseHas('tasks', [
            'id' => $active->id,
            'project_id' => null,
            'list' => 'inbox',
        ]);

        // The completed task stays released (null-on-delete) but is hidden anyway.
        $this->assertDatabaseHas('tasks', ['id' => $done->id, 'project_id' => null]);
    }

    public function test_a_project_can_be_renamed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Alt']);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->set('projectName', 'Neu')
            ->call('saveRename')
            ->assertHasNoErrors()
            ->assertSet('renaming', false);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Neu']);
    }

    public function test_a_user_cannot_open_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('project.show', $project))
            ->assertNotFound();
    }

    public function test_project_tasks_cannot_be_flagged_today(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects',
            'project_id' => $project->id,
        ]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('setToday', $task->id, true);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_today' => false]);
    }
}
