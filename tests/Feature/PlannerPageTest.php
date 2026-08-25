<?php

namespace Tests\Feature;

use App\Livewire\Planner;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(bool $plannerEnabled = true): User
    {
        $user = User::factory()->create(['planner_enabled' => $plannerEnabled]);
        $this->actingAs($user);

        return $user;
    }

    private function workBlock(User $user, string $date, string $start, string $end): ScheduleEvent
    {
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        return ScheduleEvent::factory()->for($user)->on($date)->at($start, $end)->create(['category_id' => $category->id]);
    }

    public function test_visiting_the_page_while_disabled_redirects_to_the_board(): void
    {
        $this->actingUser(plannerEnabled: false);

        Livewire::test(Planner::class)->assertRedirect(route('app'));
    }

    public function test_visiting_the_page_while_enabled_succeeds(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)->assertOk();
    }

    public function test_blocks_are_grouped_by_day_with_linked_tasks(): void
    {
        $user = $this->actingUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        $component = Livewire::test(Planner::class);

        $day = $component->instance()->blocks->get(now()->toDateString());
        $this->assertNotNull($day);
        $this->assertTrue($day->first()->is($block));
        $this->assertSame($task->id, $day->first()->linkedTasks->first()->id);
    }

    public function test_reorder_block_stamps_every_id_as_manual_and_moves_a_task_between_blocks(): void
    {
        $user = $this->actingUser();
        $blockA = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        $blockB = $this->workBlock($user, now()->toDateString(), '11:00', '12:00');
        $task = Task::factory()->for($user)->tasks()->create();
        $blockA->linkedTasks()->attach($task->id, ['sort_order' => 0, 'source' => 'auto']);

        Livewire::test(Planner::class)->call('reorderBlock', $blockB->id, [$task->id]);

        $this->assertSame(0, $blockA->linkedTasks()->count());
        $link = $blockB->linkedTasks()->first();
        $this->assertSame($task->id, $link->id);
        $this->assertSame('manual', $link->pivot->source);
    }

    public function test_reorder_block_silently_drops_a_foreign_task_id(): void
    {
        $user = $this->actingUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        $stranger = Task::factory()->create(); // belongs to a different user

        Livewire::test(Planner::class)->call('reorderBlock', $block->id, [$stranger->id])->assertHasNoErrors();

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    public function test_reorder_block_on_a_foreign_block_is_rejected(): void
    {
        $this->actingUser();
        $foreignBlock = ScheduleEvent::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Planner::class)->call('reorderBlock', $foreignBlock->id, []);
    }

    public function test_unassign_task_removes_its_link(): void
    {
        $user = $this->actingUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        $task = Task::factory()->for($user)->tasks()->create();
        $block->linkedTasks()->attach($task->id, ['sort_order' => 0, 'source' => 'manual']);

        Livewire::test(Planner::class)->call('unassignTask', $task->id);

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    public function test_regenerate_discards_a_manual_placement_and_replans(): void
    {
        $user = $this->actingUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $stale = Task::factory()->for($user)->tasks()->create();
        $block->linkedTasks()->attach($stale->id, ['sort_order' => 0, 'source' => 'manual']);
        $urgent = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        Livewire::test(Planner::class)->call('regenerate');

        $linked = $block->linkedTasks()->pluck('tasks.id')->all();
        $this->assertSame([$urgent->id], $linked);
    }

    /** Confirms the ManagesSchedule trait's moveEvent() — pulled in purely for this — actually works on a Planner-owned block. */
    public function test_a_work_block_can_be_moved_via_the_inherited_move_event_method(): void
    {
        $user = $this->actingUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');

        Livewire::test(Planner::class)->call('moveEvent', $block->id, '14:00');

        $block->refresh();
        $this->assertSame('14:00', $block->start_time);
        $this->assertSame('15:00', $block->end_time);
    }
}
