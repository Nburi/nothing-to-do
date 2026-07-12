<?php

namespace Tests\Feature;

use App\Livewire\Cleanup;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/app/cleanup')->assertRedirect('/login');
    }

    public function test_inbox_queue_only_contains_the_users_active_board_inbox_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $visible = Task::factory()->for($user)->inbox()->create();
        Task::factory()->for($user)->inbox()->completed()->create();
        Task::factory()->for($user)->create(['list' => 'inbox', 'project_id' => $project->id]);
        Task::factory()->for($other)->inbox()->create();

        $component = Livewire::actingAs($user)->test(Cleanup::class);

        $this->assertSame([$visible->id], $component->instance()->inboxQueue->pluck('id')->all());
    }

    public function test_review_queue_contains_every_active_todo_and_task_regardless_of_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $plain = Task::factory()->for($user)->todos()->create();
        $tagged = Task::factory()->for($user)->tasks()->today()->important()->create();
        Task::factory()->for($user)->inbox()->create();
        Task::factory()->for($user)->todos()->completed()->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);

        $component = Livewire::actingAs($user)->test(Cleanup::class);

        $this->assertEqualsCanonicalizing(
            [$plain->id, $tagged->id],
            $component->instance()->reviewQueue->pluck('id')->all(),
        );
    }

    public function test_assign_list_files_an_inbox_task_into_todos_or_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(Cleanup::class)
            ->call('assignList', $task->id, 'todos');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'list' => 'todos']);
    }

    public function test_assign_list_rejects_anything_outside_the_todos_tasks_whitelist(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(Cleanup::class)
            ->call('assignList', $task->id, 'projects');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'list' => 'inbox']);
    }

    public function test_assign_list_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->inbox()->create();

        try {
            Livewire::actingAs($user)
                ->test(Cleanup::class)
                ->call('assignList', $task->id, 'todos');
            $this->fail('Expected a ModelNotFoundException for the foreign task.');
        } catch (ModelNotFoundException) {
            // The foreign task is invisible through the owner relationship.
        }

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'list' => 'inbox']);
    }

    public function test_mark_today_flags_a_todo_or_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(Cleanup::class)
            ->call('markToday', $task->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_today' => true]);
    }

    public function test_mark_today_no_ops_for_a_task_still_in_the_inbox(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(Cleanup::class)
            ->call('markToday', $task->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_today' => false]);
    }

    public function test_important_and_dates_reuse_the_shared_managestasks_trait(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(Cleanup::class)
            ->call('toggleImportant', $task->id)
            ->call('quickSetDates', $task->id, '2026-08-01', null);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_important' => true,
            'deadline' => '2026-08-01 00:00:00',
        ]);
    }
}
