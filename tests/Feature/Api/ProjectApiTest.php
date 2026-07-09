<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/projects', ['name' => '  Maturaarbeit  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Maturaarbeit');

        $this->assertDatabaseHas('projects', ['user_id' => $user->id, 'name' => 'Maturaarbeit']);
    }

    public function test_show_includes_tasks_and_done_count(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        Task::factory()->for($user)->completed()->create(['list' => 'projects', 'project_id' => $project->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.tasks'));
        $this->assertSame(1, $response->json('data.done_count'));
    }

    public function test_it_updates_brainstorm_and_external_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/projects/{$project->id}", [
            'brainstorm' => '# Ideas',
            'external_url' => 'https://github.com/acme/repo',
        ])->assertOk();

        $project->refresh();
        $this->assertSame('# Ideas', $project->brainstorm);
        $this->assertSame('https://github.com/acme/repo', $project->external_url);
    }

    public function test_deleting_a_project_releases_active_tasks_to_the_inbox(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $activeTask = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $activeTask->refresh();
        $this->assertNull($activeTask->project_id);
        $this->assertSame('inbox', $activeTask->list);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_a_project_belonging_to_another_user_is_not_reachable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
    }
}
