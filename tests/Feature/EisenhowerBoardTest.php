<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Eisenhower" list concept: four quadrants over the existing `tasks`
 * table, built from `is_important` and `Task::isUrgent()` — no new axis data
 * (see App\Services\ListConcepts, TaskBoard::eisenhowerQuadrants(),
 * partials/board-eisenhower.blade.php, PLAN_LIST_CONCEPTS.md §1/§3).
 */
class EisenhowerBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function eisenhowerUser(): User
    {
        return User::factory()->create(['list_concept' => 'eisenhower', 'timezone_offset' => 0]);
    }

    // ── eisenhowerQuadrants() — buckets by is_important × isUrgent() ───

    public function test_important_and_urgent_lands_in_the_crisis_quadrant(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->important()->dueDate(now()->toDateString())->create(['title' => 'Crisis task']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Crisis task', $quadrants['important_urgent']->first()->title);
        $this->assertCount(0, $quadrants['important_not_urgent']);
        $this->assertCount(0, $quadrants['not_important_urgent']);
        $this->assertCount(0, $quadrants['not_important_not_urgent']);
    }

    public function test_important_and_not_urgent_lands_in_that_quadrant(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->important()->create(['title' => 'Someday important']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Someday important', $quadrants['important_not_urgent']->first()->title);
    }

    public function test_not_important_and_urgent_lands_in_that_quadrant(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->create(['title' => 'Small fire']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Small fire', $quadrants['not_important_urgent']->first()->title);
    }

    public function test_not_important_and_not_urgent_lands_in_that_quadrant(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->create(['title' => 'Whenever']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Whenever', $quadrants['not_important_not_urgent']->first()->title);
    }

    public function test_a_far_off_deadline_does_not_count_as_urgent(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->deadline(now()->addDays(30)->toDateString())->create(['title' => 'Far off']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Far off', $quadrants['not_important_not_urgent']->first()->title);
    }

    public function test_completed_tasks_are_returned_separately_not_in_any_quadrant(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->important()->completed()->create(['title' => 'Just finished']);

        $quadrants = Livewire::actingAs($user)->test(TaskBoard::class)->get('eisenhowerQuadrants');

        $this->assertSame('Just finished', $quadrants['done']->first()->title);
        $this->assertCount(0, $quadrants['important_urgent']);
        $this->assertCount(0, $quadrants['important_not_urgent']);
    }

    public function test_completed_tasks_outside_the_visibility_window_are_excluded_entirely(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->completed()->create([
            'title' => 'Long done',
            'completed_at' => now()->subDays(5),
        ]);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('Long done');
    }

    public function test_grouped_tasks_are_excluded_unless_important_or_today(): void
    {
        $user = $this->eisenhowerUser();
        $group = TaskGroup::factory()->for($user)->create();
        Task::factory()->for($user)->tasks()->create(['group_id' => $group->id, 'title' => 'Plain grouped task']);
        Task::factory()->for($user)->tasks()->important()->create(['group_id' => $group->id, 'title' => 'Important grouped task']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertDontSee('Plain grouped task')
            ->assertSee('Important grouped task');
    }

    public function test_renders_cleanly_with_no_tasks_at_all(): void
    {
        $user = $this->eisenhowerUser();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSee('Nichts brennt gerade.')
            ->assertSee('Der ruhigste Ort im Haus')
            ->assertOk();
    }

    public function test_only_shows_the_current_users_tasks(): void
    {
        $user = $this->eisenhowerUser();
        $other = User::factory()->create();
        Task::factory()->for($other)->tasks()->create(['title' => 'Not mine']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertDontSee('Not mine');
    }

    // ── Task::isUrgencyLocked() ─────────────────────────────────────────

    public function test_a_task_with_a_hard_deadline_is_urgency_locked(): void
    {
        $task = Task::factory()->make(['deadline' => now()->toDateString()]);

        $this->assertTrue($task->isUrgencyLocked());
    }

    public function test_a_task_with_only_a_soft_due_date_is_not_urgency_locked(): void
    {
        $task = Task::factory()->make(['due_date' => now()->toDateString()]);

        $this->assertFalse($task->isUrgencyLocked());
    }

    public function test_a_task_with_no_date_at_all_is_not_urgency_locked(): void
    {
        $task = Task::factory()->make();

        $this->assertFalse($task->isUrgencyLocked());
    }

    // ── reorderEisenhower() — persists order + importance, due_date only ──

    public function test_reorder_eisenhower_persists_sort_order(): void
    {
        $user = $this->eisenhowerUser();
        $a = Task::factory()->for($user)->tasks()->create(['sort_order' => 0]);
        $b = Task::factory()->for($user)->tasks()->create(['sort_order' => 1]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, false, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_eisenhower_sets_importance_to_match_the_destination_row(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', true, false, [$task->id]);

        $this->assertTrue($task->fresh()->is_important);
    }

    public function test_reorder_eisenhower_clearing_importance(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->important()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, false, [$task->id]);

        $this->assertFalse($task->fresh()->is_important);
    }

    public function test_reorder_eisenhower_moving_into_dringend_sets_a_due_date_within_the_urgency_window(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, true, [$task->id]);

        $task->refresh();
        $this->assertNotNull($task->due_date);
        $this->assertTrue($task->isUrgent());
        $this->assertNull($task->deadline);
    }

    public function test_reorder_eisenhower_moving_out_of_dringend_clears_the_due_date(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, false, [$task->id]);

        $this->assertNull($task->fresh()->due_date);
    }

    public function test_reorder_eisenhower_never_touches_due_date_for_an_already_urgent_task(): void
    {
        $user = $this->eisenhowerUser();
        $date = now()->toDateString();
        $task = Task::factory()->for($user)->tasks()->dueDate($date)->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, true, [$task->id]);

        $this->assertSame($date, $task->fresh()->due_date->toDateString());
    }

    public function test_reorder_eisenhower_never_touches_a_hard_deadline_regardless_of_the_urgent_flag(): void
    {
        $user = $this->eisenhowerUser();
        $deadline = now()->addDays(30)->toDateString();
        $task = Task::factory()->for($user)->tasks()->deadline($deadline)->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, true, [$task->id]);

        $task->refresh();
        $this->assertSame($deadline, $task->deadline->toDateString());
        // Locked by the hard deadline — still not urgent, the drop could not
        // actually move it (the client is expected to refuse this drop up
        // front; this proves the server-side guard independently).
        $this->assertFalse($task->isUrgent());
        $this->assertNull($task->due_date);
    }

    public function test_reorder_eisenhower_ignores_ids_belonging_to_another_user(): void
    {
        $user = $this->eisenhowerUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create(['sort_order' => 5]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', true, true, [$foreign->id]);

        $this->assertSame(5, $foreign->fresh()->sort_order);
        $this->assertFalse($foreign->fresh()->is_important);
    }

    // ── setTodayEisenhower() — no isInbox() guard, fixes up the list on entry ──

    public function test_set_today_eisenhower_flags_a_task_regardless_of_list(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodayEisenhower', $task->id, true);

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertNotNull($task->today_date);
    }

    public function test_set_today_eisenhower_moves_an_inbox_task_to_tasks_when_flagging_it(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodayEisenhower', $task->id, true);

        $this->assertSame('tasks', $task->fresh()->list);
    }

    public function test_set_today_eisenhower_leaves_a_non_inbox_list_untouched(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodayEisenhower', $task->id, true);

        $this->assertSame('todos', $task->fresh()->list);
    }

    public function test_set_today_eisenhower_can_unflag(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->todos()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodayEisenhower', $task->id, false);

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertNull($task->today_date);
    }

    public function test_set_today_eisenhower_is_ownership_scoped(): void
    {
        $user = $this->eisenhowerUser();
        $other = User::factory()->create();
        $foreign = Task::factory()->for($other)->tasks()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodayEisenhower', $foreign->id, true);
    }

    // ── swipeIntentEisenhower() ──────────────────────────────────────────

    public function test_swipe_intent_eisenhower_today_flags_an_inbox_task_and_moves_its_list(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentEisenhower', $task->id, 'today');

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertSame('tasks', $task->list);
    }

    public function test_swipe_intent_eisenhower_untoday_clears_the_flag(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->todos()->today()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentEisenhower', $task->id, 'untoday');

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_swipe_intent_eisenhower_ignores_an_unknown_intent(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('swipeIntentEisenhower', $task->id, 'edit');

        $this->assertFalse($task->fresh()->is_today);
    }

    // ── "Krisenring" — trackEisenhowerCrisisEntries() dispatch ──────────

    public function test_a_task_entering_the_crisis_quadrant_dispatches_the_ring_event(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', true, true, [$task->id])
            ->assertDispatched('eisenhower-crisis');
    }

    public function test_a_task_already_present_on_first_load_does_not_dispatch(): void
    {
        $user = $this->eisenhowerUser();
        Task::factory()->for($user)->tasks()->important()->dueDate(now()->toDateString())->create();

        // mount() seeds eisenhowerCrisisSeenIds from what's already there —
        // the very first render must not pulse for the starting state.
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertNotDispatched('eisenhower-crisis');
    }

    public function test_reordering_within_the_crisis_quadrant_does_not_redispatch(): void
    {
        $user = $this->eisenhowerUser();
        $a = Task::factory()->for($user)->tasks()->important()->dueDate(now()->toDateString())->create();
        $b = Task::factory()->for($user)->tasks()->important()->dueDate(now()->toDateString())->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', true, true, [$b->id, $a->id])
            ->assertNotDispatched('eisenhower-crisis');
    }

    public function test_leaving_the_crisis_quadrant_does_not_dispatch(): void
    {
        $user = $this->eisenhowerUser();
        $task = Task::factory()->for($user)->tasks()->important()->dueDate(now()->toDateString())->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, false, [$task->id])
            ->assertNotDispatched('eisenhower-crisis');
    }
}
