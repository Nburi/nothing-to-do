<?php

namespace Tests\Feature;

use App\Livewire\ProjectPage;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_a_board_task_can_be_dragged_onto_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->today()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('assignTaskToProject', $task->id, $project->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'list' => 'projects',
            'is_today' => false,
        ]);
    }

    public function test_a_task_cannot_be_dragged_onto_another_users_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($other)->create();
        $task = Task::factory()->for($user)->todos()->create();

        try {
            Livewire::actingAs($user)
                ->test(TaskBoard::class)
                ->call('assignTaskToProject', $task->id, $project->id);
            $this->fail('Expected a ModelNotFoundException for the foreign project.');
        } catch (ModelNotFoundException $e) {
            // The foreign project is invisible through the owner relationship.
        }

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => null]);
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

    public function test_brainstorm_notes_load_on_mount(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => "# Plan\n\nErste Idee."]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSet('brainstorm', "# Plan\n\nErste Idee.");
    }

    public function test_brainstorm_notes_are_autosaved_on_change(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->set('brainstorm', "## Ideen\n\n- eins\n- zwei")
            ->assertHasNoErrors()
            ->assertDispatched('brainstorm-saved');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'brainstorm' => "## Ideen\n\n- eins\n- zwei",
        ]);
    }

    public function test_clearing_brainstorm_stores_null(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => 'etwas']);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->set('brainstorm', '   ')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'brainstorm' => null]);
    }

    public function test_brainstorm_renders_markdown_to_html(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => '# Heading']);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSeeHtml('<h1>Heading</h1>');
    }

    public function test_brainstorm_strips_unsafe_html_and_links(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'brainstorm' => "<script>alert('x')</script>\n\n[böse](javascript:alert(1))",
        ]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertDontSeeHtml('<script>alert')
            ->assertDontSee('javascript:', false);
    }

    public function test_an_empty_project_opens_brainstorm_in_edit_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => null]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSet('editingBrainstorm', true);
    }

    public function test_a_project_with_notes_opens_brainstorm_in_read_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => 'eine Idee']);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSet('editingBrainstorm', false);
    }

    public function test_finishing_brainstorm_saves_and_leaves_edit_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => null]);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSet('editingBrainstorm', true)
            ->set('brainstorm', '# Erste Idee')
            ->call('stopEditingBrainstorm')
            ->assertHasNoErrors()
            ->assertSet('editingBrainstorm', false);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'brainstorm' => '# Erste Idee']);
    }

    public function test_brainstorm_can_be_reopened_after_finishing_empty(): void
    {
        // Regression: emptying the notes and hitting "Fertig" must not lock the
        // user out — re-entering the editor has to keep working without a reload.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['brainstorm' => 'alte Notiz']);

        Livewire::actingAs($user)
            ->test(ProjectPage::class, ['project' => $project])
            ->assertSet('editingBrainstorm', false)
            ->call('editBrainstorm')
            ->assertSet('editingBrainstorm', true)
            ->set('brainstorm', '')
            ->call('stopEditingBrainstorm')
            ->assertSet('editingBrainstorm', false)
            ->call('editBrainstorm')
            ->assertSet('editingBrainstorm', true);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'brainstorm' => null]);
    }
}
