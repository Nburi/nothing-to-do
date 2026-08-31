<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Livewire\TaskBoard;

/**
 * The "Kanban" list concept: three columns — Backlog / In Arbeit / Erledigt
 * — over the existing `tasks` table, built from `is_today` and
 * `is_completed` — no new axis data (see App\Services\ListConcepts,
 * TaskBoard::kanbanColumns(), partials/board-kanban.blade.php,
 * PLAN_LIST_CONCEPTS.md §1/§3).
 */
class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    private function kanbanUser(): User
    {
        return User::factory()->create(['list_concept' => 'kanban', 'timezone_offset' => 0]);
    }

    // ── kanbanColumns() — buckets by is_today × is_completed ────────────

    public function test_an_active_not_today_task_lands_in_backlog(): void
    {
        $user = $this->kanbanUser();
        Task::factory()->for($user)->tasks()->create(['title' => 'Waiting']);

        $columns = Livewire::actingAs($user)->test(TaskBoard::class)->get('kanbanColumns');

        $this->assertSame('Waiting', $columns['backlog']->first()->title);
        $this->assertCount(0, $columns['in_progress']);
        $this->assertCount(0, $columns['done']);
    }

    public function test_an_active_today_task_lands_in_in_progress(): void
    {
        $user = $this->kanbanUser();
        Task::factory()->for($user)->tasks()->today()->create(['title' => 'Working on it']);

        $columns = Livewire::actingAs($user)->test(TaskBoard::class)->get('kanbanColumns');

        $this->assertSame('Working on it', $columns['in_progress']->first()->title);
        $this->assertCount(0, $columns['backlog']);
    }

    public function test_a_completed_task_lands_in_done(): void
    {
        $user = $this->kanbanUser();
        Task::factory()->for($user)->tasks()->completed()->create(['title' => 'Finished']);

        $columns = Livewire::actingAs($user)->test(TaskBoard::class)->get('kanbanColumns');

        $this->assertSame('Finished', $columns['done']->first()->title);
        $this->assertCount(0, $columns['backlog']);
        $this->assertCount(0, $columns['in_progress']);
    }

    public function test_a_completed_task_lands_in_done_regardless_of_its_today_flag(): void
    {
        $user = $this->kanbanUser();
        Task::factory()->for($user)->tasks()->today()->completed()->create(['title' => 'Finished while today']);

        $columns = Livewire::actingAs($user)->test(TaskBoard::class)->get('kanbanColumns');

        $this->assertSame('Finished while today', $columns['done']->first()->title);
        $this->assertCount(0, $columns['in_progress']);
    }

    public function test_completed_tasks_outside_the_visibility_window_are_excluded_entirely(): void
    {
        $user = $this->kanbanUser();
        Task::factory()->for($user)->tasks()->completed()->create([
            'title' => 'Long done',
            'completed_at' => now()->subDays(5),
        ]);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('Long done');
    }

    public function test_grouped_tasks_are_excluded_unless_important_or_today(): void
    {
        $user = $this->kanbanUser();
        $group = TaskGroup::factory()->for($user)->create();
        Task::factory()->for($user)->tasks()->create(['group_id' => $group->id, 'title' => 'Plain grouped task']);
        Task::factory()->for($user)->tasks()->important()->create(['group_id' => $group->id, 'title' => 'Important grouped task']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertDontSee('Plain grouped task')
            ->assertSee('Important grouped task');
    }

    public function test_renders_cleanly_with_no_tasks_at_all(): void
    {
        $user = $this->kanbanUser();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSee('Backlog')
            ->assertSee('In Arbeit')
            ->assertSee('Erledigt')
            ->assertOk();
    }

    public function test_only_shows_the_current_users_tasks(): void
    {
        $user = $this->kanbanUser();
        $other = User::factory()->create();
        Task::factory()->for($other)->tasks()->create(['title' => 'Not mine']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('Not mine');
    }

    // ── setKanbanColumn() — the per-card move pill ───────────────────────

    public function test_moving_a_backlog_task_into_in_progress_flags_it_today(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'in_progress');

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertNotNull($task->today_date);
        $this->assertFalse($task->is_completed);
    }

    public function test_moving_an_in_progress_task_back_to_backlog_clears_today(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'backlog');

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertNull($task->today_date);
    }

    public function test_moving_a_task_into_done_completes_it(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'done');

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_moving_a_done_task_back_to_backlog_uncompletes_it_and_clears_today(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->today()->completed()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'backlog');

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
        $this->assertFalse($task->is_today);
        $this->assertNull($task->today_date);
    }

    public function test_a_task_already_in_the_destination_column_is_a_harmless_no_op(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->today()->create();
        $originalTodayDate = $task->today_date->toDateString();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'in_progress');

        $task->refresh();
        $this->assertTrue($task->is_today);
        // todayDateFor() leaves an already-true flag's date untouched.
        $this->assertSame($originalTodayDate, $task->today_date->toDateString());
    }

    public function test_set_kanban_column_ignores_an_unknown_column(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $task->id, 'not-a-real-column');

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertFalse($task->is_completed);
    }

    public function test_set_kanban_column_is_ownership_scoped(): void
    {
        $user = $this->kanbanUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create();

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setKanbanColumn', $foreign->id, 'in_progress');
    }

    // ── reorderKanban() — desktop drag across all three columns ─────────

    public function test_reorder_kanban_persists_sort_order_within_a_column(): void
    {
        $user = $this->kanbanUser();
        $a = Task::factory()->for($user)->tasks()->create(['sort_order' => 0]);
        $b = Task::factory()->for($user)->tasks()->create(['sort_order' => 1]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'backlog', [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_kanban_dropping_into_in_progress_flags_today(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'in_progress', [$task->id]);

        $this->assertTrue($task->fresh()->is_today);
    }

    public function test_reorder_kanban_dropping_into_done_completes_the_task(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'done', [$task->id]);

        $task->refresh();
        $this->assertTrue($task->is_completed);
    }

    public function test_reorder_kanban_within_done_does_not_reopen_already_completed_tasks(): void
    {
        // A batch reorder call touches every id in the target zone, not just
        // the one that actually moved — this proves the toggle guard in
        // applyKanbanColumn() actually guards, rather than re-flipping every
        // already-completed sibling back open on each reorder.
        $user = $this->kanbanUser();
        $a = Task::factory()->for($user)->tasks()->completed()->create(['sort_order' => 0]);
        $b = Task::factory()->for($user)->tasks()->completed()->create(['sort_order' => 1]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'done', [$b->id, $a->id]);

        $this->assertTrue($a->fresh()->is_completed);
        $this->assertTrue($b->fresh()->is_completed);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_kanban_ignores_an_unknown_column(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create(['sort_order' => 5]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'not-a-real-column', [$task->id]);

        $this->assertSame(5, $task->fresh()->sort_order);
    }

    public function test_reorder_kanban_ignores_ids_belonging_to_another_user(): void
    {
        $user = $this->kanbanUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create(['sort_order' => 5]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderKanban', 'done', [$foreign->id]);

        $this->assertSame(5, $foreign->fresh()->sort_order);
        $this->assertFalse($foreign->fresh()->is_completed);
    }

    // ── swipeIntentKanban() — mobile "advance" ───────────────────────────

    public function test_swipe_advance_moves_a_backlog_task_into_in_progress(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentKanban', $task->id, 'advance');

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertFalse($task->is_completed);
    }

    public function test_swipe_advance_moves_an_in_progress_task_into_done(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentKanban', $task->id, 'advance');

        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_swipe_advance_on_an_already_done_task_is_a_no_op(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->completed()->create();
        $completedAt = $task->completed_at;

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentKanban', $task->id, 'advance');

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertEquals($completedAt, $task->completed_at);
    }

    public function test_swipe_intent_kanban_ignores_an_unknown_intent(): void
    {
        $user = $this->kanbanUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentKanban', $task->id, 'edit');

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertFalse($task->is_completed);
    }
}
