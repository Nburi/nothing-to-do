<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiNonNumericIdTest extends TestCase
{
    use RefreshDatabase;

    public static function resources(): array
    {
        return array_combine(
            $r = ['tasks', 'projects', 'task-groups', 'agenda-entries', 'schedule-events', 'event-categories', 'event-templates'],
            array_map(fn ($x) => [$x], $r),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('resources')]
    public function test_a_non_numeric_id_is_a_404_never_a_500(string $resource): void
    {
        config(['logging.default' => 'null']);
        Sanctum::actingAs(User::factory()->create());

        foreach (['getJson', 'patchJson', 'deleteJson'] as $verb) {
            $status = $this->$verb("/api/{$resource}/abc")->getStatusCode();

            $this->assertContains($status, [404, 405], "{$verb} /api/{$resource}/abc → {$status}");
        }
    }

    public function test_numeric_ids_still_work_and_the_reorder_route_is_not_swallowed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = Task::factory()->for($user)->todos()->create();
        $project = Project::factory()->for($user)->create();

        $this->getJson("/api/tasks/{$task->id}")->assertOk();
        $this->getJson("/api/projects/{$project->id}")->assertOk();
        $this->postJson('/api/tasks/reorder', ['list' => 'todos', 'ids' => [$task->id]])->assertStatus(200);
    }

    public function test_the_web_pages_bound_by_id_still_resolve(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('project.show', $project))->assertOk();
        $this->actingAs($user)->get('/app/projects/abc')->assertNotFound();
    }
}
