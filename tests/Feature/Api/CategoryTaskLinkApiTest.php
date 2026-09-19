<?php

namespace Tests\Feature\Api;

use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTaskLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private function category(User $user, array $attributes = []): EventCategory
    {
        return EventCategory::factory()->for($user)->pomodoro()->create($attributes);
    }

    public function test_a_category_reports_no_link_by_default(): void
    {
        $user = User::factory()->create();
        $this->category($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/event-categories')
            ->assertOk()
            ->assertJsonPath('data.0.task_source', null)
            ->assertJsonPath('data.0.task_source_label', null)
            ->assertJsonPath('data.0.pinned_task_ids', []);
    }

    public function test_it_links_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Wettkampf']);
        $category = $this->category($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'project', 'project_id' => $project->id])
            ->assertOk()
            ->assertJsonPath('data.task_source', 'project')
            ->assertJsonPath('data.linked_project_id', $project->id)
            ->assertJsonPath('data.task_source_label', 'Wettkampf');
    }

    public function test_it_links_a_group_a_text_and_the_generic_homework_nudge(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $category = $this->category($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'group', 'group_id' => $group->id])
            ->assertOk()->assertJsonPath('data.linked_group_id', $group->id);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'text', 'text' => '  Zimmer aufräumen '])
            ->assertOk()
            ->assertJsonPath('data.task_source', 'text')
            ->assertJsonPath('data.linked_text', 'Zimmer aufräumen')
            // Switching source never leaves the previous target behind.
            ->assertJsonPath('data.linked_group_id', null);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'agenda_generic'])
            ->assertOk()
            ->assertJsonPath('data.task_source', 'agenda_generic')
            ->assertJsonPath('data.linked_text', null);
    }

    public function test_it_pins_tasks_in_the_given_order_and_replaces_them_on_relink(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        $c = Task::factory()->for($user)->todos()->create();
        $category = $this->category($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'tasks', 'task_ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.pinned_task_ids', [$c->id, $a->id, $b->id])
            ->assertJsonPath('data.task_source_label', '3 Aufgaben');

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'tasks', 'task_ids' => [$b->id]])
            ->assertOk()
            ->assertJsonPath('data.pinned_task_ids', [$b->id]);
    }

    public function test_a_classmates_agenda_entry_is_a_valid_target_but_an_invisible_one_is_not(): void
    {
        $user = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($user, $classmate)->create();
        $visible = AgendaEntry::factory()->for($classmate)->create(['agenda_space_id' => $space->id]);
        $hidden = AgendaEntry::factory()->for($classmate)->create(['agenda_space_id' => null]);
        $category = $this->category($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'agenda_entry', 'agenda_entry_id' => $visible->id])
            ->assertOk()->assertJsonPath('data.linked_agenda_entry_id', $visible->id);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'agenda_entry', 'agenda_entry_id' => $hidden->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agenda_entry_id');
    }

    public function test_foreign_targets_and_missing_parameters_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = $this->category($user);
        Sanctum::actingAs($user);
        $url = "/api/event-categories/{$category->id}/task-link";

        $this->putJson($url, ['source' => 'project', 'project_id' => Project::factory()->for($other)->create()->id])->assertUnprocessable();
        $this->putJson($url, ['source' => 'group', 'group_id' => TaskGroup::factory()->for($other)->create()->id])->assertUnprocessable();
        $this->putJson($url, ['source' => 'tasks', 'task_ids' => [Task::factory()->for($other)->todos()->create()->id]])->assertUnprocessable();
        $this->putJson($url, ['source' => 'project'])->assertUnprocessable();
        $this->putJson($url, ['source' => 'text'])->assertUnprocessable();
        $this->putJson($url, ['source' => 'nonsense'])->assertUnprocessable();

        $this->assertNull($category->fresh()->task_source);
    }

    public function test_a_category_without_pomodoro_cannot_be_linked(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        Sanctum::actingAs($user);

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'agenda_generic'])->assertUnprocessable();
    }

    public function test_someone_elses_category_is_a_404(): void
    {
        $category = $this->category(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/event-categories/{$category->id}/task-link", ['source' => 'agenda_generic'])->assertNotFound();
        $this->deleteJson("/api/event-categories/{$category->id}/task-link")->assertNotFound();
    }

    public function test_the_link_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $category = $this->category($user, ['task_source' => 'tasks']);
        $category->pinnedTasks()->attach($task->id, ['sort_order' => 0]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/event-categories/{$category->id}/task-link")
            ->assertOk()
            ->assertJsonPath('data.task_source', null)
            ->assertJsonPath('data.pinned_task_ids', []);

        $this->assertDatabaseCount('category_task_links', 0);
    }
}
