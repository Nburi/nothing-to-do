<?php

namespace Tests\Feature;

use App\Livewire\Planner;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskDayPlan;
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

    public function test_board_computed_reflects_a_placed_task(): void
    {
        $user = $this->actingUser();
        $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $task = Task::factory()->for($user)->tasks()->create();
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => now()->toDateString(), 'sort_order' => 0]);

        $day = Livewire::test(Planner::class)->instance()->board->get(now()->toDateString());

        $this->assertSame(30, $day['capacityTotal']);
        $this->assertSame([$task->id], $day['tasks']->pluck('id')->all());
    }

    public function test_assign_day_persists_the_order_sent_by_the_client(): void
    {
        $user = $this->actingUser();
        $a = Task::factory()->for($user)->tasks()->create();
        $b = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();

        Livewire::test(Planner::class)->call('assignDay', $date, ["task:{$b->id}", "task:{$a->id}"]);

        $ids = TaskDayPlan::where('planned_date', $date)->orderBy('sort_order')->pluck('task_id')->all();
        $this->assertSame([$b->id, $a->id], $ids);
    }

    public function test_assign_day_silently_drops_a_foreign_task_token(): void
    {
        $this->actingUser();
        $stranger = Task::factory()->create();

        Livewire::test(Planner::class)
            ->call('assignDay', now()->toDateString(), ["task:{$stranger->id}"])
            ->assertHasNoErrors();

        $this->assertSame(0, TaskDayPlan::count());
    }

    public function test_unassign_task_removes_its_plan(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->tasks()->create();
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => now()->toDateString(), 'sort_order' => 0]);

        Livewire::test(Planner::class)->call('unassignTask', $task->id);

        $this->assertSame(0, TaskDayPlan::count());
    }

    public function test_unassign_task_on_a_foreign_task_is_rejected(): void
    {
        $this->actingUser();
        $foreign = Task::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Planner::class)->call('unassignTask', $foreign->id);
    }

    public function test_move_to_day_appends_to_an_existing_days_order(): void
    {
        $user = $this->actingUser();
        $first = Task::factory()->for($user)->tasks()->create();
        $second = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();
        TaskDayPlan::create(['task_id' => $first->id, 'planned_date' => $date, 'sort_order' => 0]);

        Livewire::test(Planner::class)->call('moveToDay', "task:{$second->id}", $date);

        $ids = TaskDayPlan::where('planned_date', $date)->orderBy('sort_order')->pluck('task_id')->all();
        $this->assertSame([$first->id, $second->id], $ids);
    }

    public function test_auto_fill_backlog_places_an_eligible_task(): void
    {
        $user = $this->actingUser();
        $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        Livewire::test(Planner::class)->call('autoFillBacklog');

        $this->assertTrue(TaskDayPlan::where('task_id', $task->id)->exists());
    }
}
