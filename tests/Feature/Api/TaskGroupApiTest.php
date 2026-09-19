<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskGroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/task-groups')->assertUnauthorized();
    }

    public function test_a_group_is_created_together_with_its_tasks(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->tasks()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/task-groups', ['name' => '  Referat  ', 'task_ids' => [$a->id, $b->id]])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Referat');

        $groupId = $response->json('data.id');
        $this->assertSame($groupId, $a->fresh()->group_id);
        $this->assertSame($groupId, $b->fresh()->group_id);
        // Filing into a group never changes the task's list.
        $this->assertSame('todos', $a->fresh()->list);
        $this->assertCount(2, $response->json('data.tasks'));
    }

    public function test_an_empty_group_or_someone_elses_task_or_a_project_task_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreign = Task::factory()->for(User::factory()->create())->todos()->create();
        $project = Project::factory()->for($user)->create();
        $inProject = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/task-groups', ['name' => 'X', 'task_ids' => []])->assertUnprocessable();
        $this->postJson('/api/task-groups', ['name' => 'X', 'task_ids' => [$foreign->id]])->assertUnprocessable();
        $this->postJson('/api/task-groups', ['name' => 'X', 'task_ids' => [$inProject->id]])->assertUnprocessable();
        $this->assertDatabaseCount('task_groups', 0);
    }

    public function test_index_and_show_report_counts_and_are_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        Task::factory()->for($user)->todos()->completed()->create(['group_id' => $group->id]);
        $foreign = TaskGroup::factory()->for(User::factory()->create())->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/task-groups')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active_count', 1)
            ->assertJsonPath('data.0.done_count', 1);

        $this->getJson("/api/task-groups/{$group->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.tasks');

        $this->getJson("/api/task-groups/{$foreign->id}")->assertNotFound();
    }

    public function test_it_renames_a_group_and_never_someone_elses(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $foreign = TaskGroup::factory()->for(User::factory()->create())->create(['name' => 'Fremd']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/task-groups/{$group->id}", ['name' => 'Neu'])->assertOk()->assertJsonPath('data.name', 'Neu');
        $this->patchJson("/api/task-groups/{$foreign->id}", ['name' => 'Gehackt'])->assertNotFound();
        $this->assertSame('Fremd', $foreign->fresh()->name);
    }

    public function test_deleting_a_group_releases_its_tasks_instead_of_deleting_them(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/task-groups/{$group->id}")->assertNoContent();

        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
        $this->assertNull($task->fresh()->group_id);
    }

    public function test_a_task_can_be_filed_into_and_released_from_a_group_through_the_task_endpoint(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $keeper = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $second = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $loose = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$loose->id}", ['group_id' => $group->id])
            ->assertOk()
            ->assertJsonPath('data.group_id', $group->id);

        $this->patchJson("/api/tasks/{$loose->id}", ['group_id' => null])
            ->assertOk()
            ->assertJsonPath('data.group_id', null);
        $this->assertDatabaseHas('task_groups', ['id' => $group->id]);

        // Releasing down to a single task dissolves the group, like everywhere else.
        $this->patchJson("/api/tasks/{$second->id}", ['group_id' => null])->assertOk();
        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
        $this->assertNull($keeper->fresh()->group_id);
    }

    public function test_filing_a_task_into_a_foreign_group_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreign = TaskGroup::factory()->for(User::factory()->create())->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['group_id' => $foreign->id])->assertUnprocessable();
        $this->postJson('/api/tasks', ['title' => 'X', 'group_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_a_task_can_be_created_straight_into_a_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', ['title' => 'Folien', 'group_id' => $group->id, 'list' => 'todos'])
            ->assertCreated()
            ->assertJsonPath('data.group_id', $group->id)
            ->assertJsonPath('data.list', 'todos');
    }

    public function test_moving_a_project_task_into_a_group_puts_it_back_on_a_board_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['group_id' => $group->id])
            ->assertOk()
            ->assertJsonPath('data.project_id', null)
            ->assertJsonPath('data.list', 'inbox');
    }
}
