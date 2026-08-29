<?php

namespace Tests\Feature;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use App\Services\DayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayPlannerTest extends TestCase
{
    use RefreshDatabase;

    private function plannerUser(): User
    {
        return User::factory()->create(['planner_enabled' => true]);
    }

    /** A Pomodoro-enabled block on the given date, contributing to that day's capacity. */
    private function workBlock(User $user, string $date, string $start, string $end): ScheduleEvent
    {
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        return ScheduleEvent::factory()->for($user)->on($date)->at($start, $end)->create(['category_id' => $category->id]);
    }

    public function test_board_backlog_and_conflicts_are_empty_when_planner_is_disabled(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->create();

        $this->assertCount(0, DayPlanner::board($user));
        $this->assertCount(0, DayPlanner::backlog($user));
        $this->assertCount(0, DayPlanner::conflicts($user));
    }

    public function test_board_has_one_entry_per_horizon_day_even_with_nothing_planned(): void
    {
        $user = $this->plannerUser();

        $this->assertCount(DayPlanner::HORIZON_DAYS, DayPlanner::board($user));
    }

    public function test_assign_day_places_a_task_and_stamps_it_manual(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();

        DayPlanner::assignDay($user, $date, ["task:{$task->id}"]);

        $plan = TaskDayPlan::where('task_id', $task->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame($date, $plan->planned_date->toDateString());
        $this->assertSame('manual', $plan->source);

        $day = DayPlanner::board($user)->get($date);
        $this->assertSame([$task->id], $day['tasks']->pluck('id')->all());
    }

    public function test_assign_day_persists_the_given_order(): void
    {
        $user = $this->plannerUser();
        $first = Task::factory()->for($user)->tasks()->create();
        $second = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();

        DayPlanner::assignDay($user, $date, ["task:{$second->id}", "task:{$first->id}"]);

        $ids = DayPlanner::board($user)->get($date)['tasks']->pluck('id')->all();
        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_assign_day_relocates_a_task_already_planned_for_another_day(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $dayA = now()->toDateString();
        $dayB = now()->addDay()->toDateString();

        DayPlanner::assignDay($user, $dayA, ["task:{$task->id}"]);
        DayPlanner::assignDay($user, $dayB, ["task:{$task->id}"]);

        $this->assertSame(1, TaskDayPlan::where('task_id', $task->id)->count());
        $this->assertCount(0, DayPlanner::board($user)->get($dayA)['tasks']);
        $this->assertCount(1, DayPlanner::board($user)->get($dayB)['tasks']);
    }

    public function test_assign_day_silently_drops_a_foreign_task_id(): void
    {
        $user = $this->plannerUser();
        $stranger = Task::factory()->create();

        DayPlanner::assignDay($user, now()->toDateString(), ["task:{$stranger->id}"]);

        $this->assertSame(0, TaskDayPlan::count());
    }

    public function test_assign_day_promotes_an_agenda_token_into_a_real_task(): void
    {
        $user = $this->plannerUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->duration(20)
            ->create(['date' => now()->addDays(3)->toDateString(), 'subject' => 'Bio', 'title' => 'Zellatmung']);

        DayPlanner::assignDay($user, now()->toDateString(), ["agenda:{$entry->id}"]);

        $task = Task::where('agenda_entry_id', $entry->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Bio: Zellatmung', $task->title);
        $this->assertSame(20, $task->duration_minutes);
        $this->assertTrue(TaskDayPlan::where('task_id', $task->id)->exists());
    }

    public function test_assign_day_reuses_an_already_promoted_homework_task(): void
    {
        $user = $this->plannerUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => now()->toDateString()]);
        $existingTask = Task::factory()->for($user)->tasks()->create(['agenda_entry_id' => $entry->id]);

        DayPlanner::assignDay($user, now()->toDateString(), ["agenda:{$entry->id}"]);

        $this->assertSame(1, Task::where('agenda_entry_id', $entry->id)->count());
        $this->assertSame($existingTask->id, TaskDayPlan::first()->task_id);
    }

    public function test_unassign_task_releases_it_back_to_the_backlog(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::assignDay($user, now()->toDateString(), ["task:{$task->id}"]);

        DayPlanner::unassignTask($task);

        $this->assertSame(0, TaskDayPlan::count());
        $this->assertTrue(DayPlanner::backlog($user)->pluck('id')->contains($task->id));
    }

    public function test_move_to_day_appends_after_a_days_existing_order(): void
    {
        $user = $this->plannerUser();
        $first = Task::factory()->for($user)->tasks()->create();
        $second = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();
        DayPlanner::assignDay($user, $date, ["task:{$first->id}"]);

        DayPlanner::moveToDay($user, "task:{$second->id}", $date);

        $ids = DayPlanner::board($user)->get($date)['tasks']->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], $ids);
    }

    public function test_backlog_excludes_a_task_already_planned_for_a_day(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::assignDay($user, now()->toDateString(), ["task:{$task->id}"]);

        $this->assertFalse(DayPlanner::backlog($user)->pluck('id')->contains($task->id));
    }

    public function test_backlog_includes_undated_tasks(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->todos()->create();

        $item = DayPlanner::backlog($user)->firstWhere('id', $task->id);
        $this->assertNotNull($item);
        $this->assertNull($item['deadlineOffset']);
    }

    public function test_backlog_never_includes_inbox_tasks(): void
    {
        $user = $this->plannerUser();
        Task::factory()->for($user)->inbox()->create();

        $this->assertCount(0, DayPlanner::backlog($user));
    }

    public function test_backlog_never_includes_exam_entries(): void
    {
        $user = $this->plannerUser();
        AgendaEntry::factory()->for($user)->exam()->create(['date' => now()->toDateString()]);

        $this->assertCount(0, DayPlanner::backlog($user));
    }

    public function test_conflicts_lists_a_task_whose_effective_deadline_has_passed(): void
    {
        $user = $this->plannerUser();
        // A hard deadline yesterday is already past its raw date, let alone the buffer.
        $task = Task::factory()->for($user)->tasks()->create(['deadline' => now()->subDay()->toDateString()]);

        $conflicts = DayPlanner::conflicts($user);

        $this->assertCount(1, $conflicts);
        $this->assertSame($task->id, $conflicts->first()['id']);
    }

    public function test_conflicts_excludes_a_task_still_within_its_effective_deadline(): void
    {
        $user = $this->plannerUser();
        Task::factory()->for($user)->tasks()->dueDate(now()->addDays(5)->toDateString())->create();

        $this->assertCount(0, DayPlanner::conflicts($user));
    }

    public function test_placing_an_overdue_task_anywhere_clears_the_conflict(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create(['deadline' => now()->subDay()->toDateString()]);

        DayPlanner::assignDay($user, now()->toDateString(), ["task:{$task->id}"]);

        $this->assertCount(0, DayPlanner::conflicts($user));
    }

    public function test_capacity_only_counts_pomodoro_enabled_blocks(): void
    {
        $user = $this->plannerUser();
        $plainCategory = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('09:00', '10:00')
            ->create(['category_id' => $plainCategory->id]);

        $day = DayPlanner::board($user)->get(now()->toDateString());
        $this->assertSame(0, $day['capacityTotal']);
    }

    public function test_a_day_with_no_blocks_still_accepts_a_placement(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $date = now()->toDateString();

        DayPlanner::assignDay($user, $date, ["task:{$task->id}"]);

        $day = DayPlanner::board($user)->get($date);
        $this->assertSame(0, $day['capacityTotal']);
        $this->assertCount(1, $day['tasks']);
    }

    public function test_auto_fill_backlog_places_a_dated_task_into_a_day_with_room(): void
    {
        $user = $this->plannerUser();
        $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        DayPlanner::autoFillBacklog($user);

        $plan = TaskDayPlan::where('task_id', $task->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame('auto', $plan->source);
    }

    public function test_auto_fill_backlog_never_touches_an_existing_manual_placement(): void
    {
        $user = $this->plannerUser();
        $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $manual = Task::factory()->for($user)->tasks()->create();
        DayPlanner::assignDay($user, now()->addDays(2)->toDateString(), ["task:{$manual->id}"]);

        DayPlanner::autoFillBacklog($user);

        $plan = TaskDayPlan::where('task_id', $manual->id)->first();
        $this->assertSame(now()->addDays(2)->toDateString(), $plan->planned_date->toDateString());
        $this->assertSame('manual', $plan->source);
    }

    public function test_auto_fill_backlog_never_places_a_task_after_its_own_deadline(): void
    {
        $user = $this->plannerUser();
        $this->workBlock($user, now()->addDays(5)->toDateString(), '09:00', '10:00');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        DayPlanner::autoFillBacklog($user);

        $this->assertFalse(TaskDayPlan::where('task_id', $task->id)->exists());
    }

    /**
     * Mirrors the old block-planner's own critical scoring test: urgency
     * must be measured from today, so a task due today wins the only slot
     * over an equally-sized task due in 10 days.
     */
    public function test_auto_fill_backlog_prefers_the_more_urgent_task_for_a_single_slot(): void
    {
        $user = $this->plannerUser();
        $this->workBlock($user, now()->toDateString(), '09:00', '09:30'); // 30 min, fits exactly one

        $urgent = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(25)->create();
        Task::factory()->for($user)->tasks()->dueDate(now()->addDays(10)->toDateString())->duration(25)->create();

        DayPlanner::autoFillBacklog($user);

        $this->assertSame([$urgent->id], DayPlanner::board($user)->get(now()->toDateString())['tasks']->pluck('id')->all());
    }
}
