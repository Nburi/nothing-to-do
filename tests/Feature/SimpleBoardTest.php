<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Simple" list concept: one flat, undivided list over the existing
 * `tasks` table (see App\Services\ListConcepts, TaskBoard::simpleTasks(),
 * partials/board-simple.blade.php, PLAN_LIST_CONCEPTS.md §1/§3).
 */
class SimpleBoardTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(): User
    {
        return User::factory()->create(['list_concept' => 'simple']);
    }

    // ── simpleTasks() — merges every list, ignores `list` for display ──

    public function test_simple_tasks_merges_every_board_list_together(): void
    {
        $user = $this->simpleUser();
        Task::factory()->for($user)->inbox()->create(['title' => 'Inbox item']);
        Task::factory()->for($user)->todos()->create(['title' => 'Todo item']);
        Task::factory()->for($user)->tasks()->create(['title' => 'Task item']);
        Task::factory()->for($user)->create(['list' => 'projects', 'title' => 'Standalone project-list item']);

        $component = Livewire::actingAs($user)->test(TaskBoard::class);

        $component->assertSee('Inbox item')
            ->assertSee('Todo item')
            ->assertSee('Task item')
            ->assertSee('Standalone project-list item');
    }

    public function test_simple_tasks_excludes_tasks_assigned_to_a_real_project(): void
    {
        $user = $this->simpleUser();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id, 'title' => 'In a real project']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('In a real project');
    }

    public function test_simple_tasks_excludes_grouped_tasks_unless_important_or_today(): void
    {
        $user = $this->simpleUser();
        $group = TaskGroup::factory()->for($user)->create();
        Task::factory()->for($user)->todos()->create(['group_id' => $group->id, 'title' => 'Plain grouped task']);
        Task::factory()->for($user)->todos()->important()->create(['group_id' => $group->id, 'title' => 'Important grouped task']);
        Task::factory()->for($user)->todos()->today()->create(['group_id' => $group->id, 'title' => 'Today-flagged grouped task']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertDontSee('Plain grouped task')
            ->assertSee('Important grouped task')
            ->assertSee('Today-flagged grouped task');
    }

    public function test_simple_tasks_excludes_completed_tasks_outside_the_visibility_window(): void
    {
        $user = $this->simpleUser();
        Task::factory()->for($user)->tasks()->completed()->create([
            'title' => 'Long-done task',
            'completed_at' => now()->subDays(5),
        ]);
        Task::factory()->for($user)->tasks()->completed()->create(['title' => 'Just-finished task']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertDontSee('Long-done task')
            ->assertSee('Just-finished task');
    }

    public function test_simple_tasks_only_shows_the_current_users_tasks(): void
    {
        $user = $this->simpleUser();
        $other = User::factory()->create();
        Task::factory()->for($other)->tasks()->create(['title' => 'Not mine']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('Not mine');
    }

    // ── reorderSimple() — persists order, never touches list/is_today ──

    public function test_reorder_simple_persists_sort_order(): void
    {
        $user = $this->simpleUser();
        $a = Task::factory()->for($user)->tasks()->create(['sort_order' => 0]);
        $b = Task::factory()->for($user)->tasks()->create(['sort_order' => 1]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderSimple', [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_simple_never_changes_list_or_today(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->inbox()->create();
        // is_today=true + list=inbox is never produced by any write path in
        // this app (setTodaySimple() itself fixes the list up first — see
        // its own test) — written directly here purely to prove
        // reorderSimple() doesn't second-guess whatever it finds either way.
        $task->update(['is_today' => true, 'today_date' => now()->toDateString()]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderSimple', [$task->id]);

        $task->refresh();
        $this->assertSame('inbox', $task->list);
        $this->assertTrue($task->is_today);
    }

    public function test_reorder_simple_ignores_ids_belonging_to_another_user(): void
    {
        $user = $this->simpleUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create(['sort_order' => 5]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderSimple', [$foreign->id]);

        $this->assertSame(5, $foreign->fresh()->sort_order);
    }

    // ── setTodaySimple() — no isInbox() guard, fixes up the list on entry ──

    public function test_set_today_simple_flags_a_task_regardless_of_list(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $task->id, true);

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertNotNull($task->today_date);
    }

    public function test_set_today_simple_moves_an_inbox_task_to_tasks_when_flagging_it(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $task->id, true);

        $this->assertSame('tasks', $task->fresh()->list);
    }

    public function test_set_today_simple_leaves_a_non_inbox_list_untouched(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $task->id, true);

        $this->assertSame('todos', $task->fresh()->list);
    }

    public function test_set_today_simple_can_unflag_without_touching_list(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->todos()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $task->id, false);

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertNull($task->today_date);
        $this->assertSame('todos', $task->list);
    }

    public function test_set_today_simple_is_ownership_scoped(): void
    {
        $user = $this->simpleUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $foreign->id, true);
    }

    // ── swipeIntentSimple() — mobile swipe, routes through setTodaySimple() ──

    public function test_swipe_intent_simple_today_flags_an_inbox_task_and_moves_its_list(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentSimple', $task->id, 'today');

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertSame('tasks', $task->list);
    }

    public function test_swipe_intent_simple_untoday_clears_the_flag(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->todos()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentSimple', $task->id, 'untoday');

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_swipe_intent_simple_ignores_an_unknown_intent(): void
    {
        $user = $this->simpleUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentSimple', $task->id, 'todos');

        $this->assertSame('tasks', $task->fresh()->list);
        $this->assertFalse($task->fresh()->is_today);
    }
}
