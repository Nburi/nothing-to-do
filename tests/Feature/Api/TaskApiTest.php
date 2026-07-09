<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_task_in_the_inbox_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', ['title' => '  Vokabeln lernen  ']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Vokabeln lernen')
            ->assertJsonPath('data.list', 'inbox');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Vokabeln lernen', 'list' => 'inbox']);
    }

    public function test_it_creates_a_task_directly_inside_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', ['title' => 'Kickoff planen', 'project_id' => $project->id]);

        $response->assertCreated()
            ->assertJsonPath('data.list', 'projects')
            ->assertJsonPath('data.project_id', $project->id);
    }

    public function test_a_blank_title_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', ['title' => '   '])->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_index_excludes_project_tasks_and_completed_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->todos()->create(['title' => 'BOARD-VISIBLE']);
        Task::factory()->for($user)->completed()->todos()->create(['title' => 'DONE']);
        Task::factory()->for($user)->create(['title' => 'IN-PROJECT', 'list' => 'projects', 'project_id' => $project->id]);

        $response = $this->getJson('/api/tasks?list=todos');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('BOARD-VISIBLE'));
        $this->assertFalse($titles->contains('DONE'));
        $this->assertFalse($titles->contains('IN-PROJECT'));
    }

    public function test_index_can_filter_by_project_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['title' => 'PROJECT-TASK', 'list' => 'projects', 'project_id' => $project->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/tasks?project_id={$project->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('PROJECT-TASK', $response->json('data.0.title'));
    }

    public function test_it_toggles_completion_via_patch(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_it_sets_today_focus_only_for_todos_and_tasks(): void
    {
        $user = User::factory()->create();
        $inboxTask = Task::factory()->for($user)->inbox()->create();
        $todoTask = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$inboxTask->id}", ['is_today' => true]);
        $this->assertFalse($inboxTask->fresh()->is_today);

        $this->patchJson("/api/tasks/{$todoTask->id}", ['is_today' => true]);
        $this->assertTrue($todoTask->fresh()->is_today);
    }

    public function test_it_assigns_a_task_to_a_project_and_can_release_it_back_to_inbox(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->today()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['project_id' => $project->id])->assertOk();
        $task->refresh();
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('projects', $task->list);
        $this->assertFalse($task->is_today);

        $this->patchJson("/api/tasks/{$task->id}", ['project_id' => null])->assertOk();
        $task->refresh();
        $this->assertNull($task->project_id);
        $this->assertSame('inbox', $task->list);
    }

    public function test_it_rejects_assigning_a_task_to_another_users_project(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for(User::factory()->create())->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['project_id' => $otherProject->id])
            ->assertUnprocessable();
    }

    public function test_it_deletes_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_reorder_persists_list_today_and_order(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks/reorder', [
            'list' => 'todos',
            'today' => true,
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertTrue($b->fresh()->is_today);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }
}
