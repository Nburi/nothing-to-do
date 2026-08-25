<?php

namespace Tests\Feature;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\WorkPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkPlannerTest extends TestCase
{
    use RefreshDatabase;

    private function plannerUser(): User
    {
        return User::factory()->create(['planner_enabled' => true]);
    }

    private function workBlock(User $user, string $date, string $start, string $end): ScheduleEvent
    {
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        return ScheduleEvent::factory()->for($user)->on($date)->at($start, $end)->create(['category_id' => $category->id]);
    }

    public function test_reconcile_does_nothing_when_planner_is_disabled(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    public function test_reconcile_places_a_single_eligible_task_into_a_work_block(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);

        $link = $block->linkedTasks()->first();
        $this->assertNotNull($link);
        $this->assertSame($task->id, $link->id);
        $this->assertSame('auto', $link->pivot->source);
    }

    public function test_only_pomodoro_enabled_categories_are_eligible_targets(): void
    {
        $user = $this->plannerUser();
        $plainCategory = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        $block = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('09:00', '10:00')
            ->create(['category_id' => $plainCategory->id]);
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    public function test_inbox_tasks_are_never_eligible(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        Task::factory()->for($user)->inbox()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    public function test_exams_are_never_planned(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '10:00');
        AgendaEntry::factory()->for($user)->exam()->duration(30)->create(['date' => now()->toDateString()]);

        WorkPlanner::reconcile($user);

        $this->assertSame(0, $block->linkedTasks()->count());
    }

    /**
     * The critical scoring test: urgency must be measured from *today*, not
     * from whichever block is being filled. A task due today must win the
     * only slot in today's block over a task due in 10 days, even though
     * both are the same size (equal fit) — if the urgency formula's sign
     * were flipped, the far-off task would win instead.
     */
    public function test_a_more_urgent_task_wins_the_only_slot_over_a_less_urgent_one_of_the_same_size(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30'); // 30 min, fits exactly one

        $urgent = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(25)->create();
        Task::factory()->for($user)->tasks()->dueDate(now()->addDays(10)->toDateString())->duration(25)->create();

        WorkPlanner::reconcile($user);

        $linked = $block->linkedTasks()->pluck('tasks.id')->all();
        $this->assertSame([$urgent->id], $linked);
    }

    public function test_manual_placements_are_never_touched_by_reconcile(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $manual = Task::factory()->for($user)->tasks()->create();
        $block->linkedTasks()->attach($manual->id, ['sort_order' => 0, 'source' => 'manual']);

        // Something else eligible exists, but the block has no room left for it.
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);

        $link = $block->linkedTasks()->first();
        $this->assertSame($manual->id, $link->id);
        $this->assertSame('manual', $link->pivot->source);
        $this->assertSame(1, $block->linkedTasks()->count());
    }

    public function test_regenerate_discards_manual_placements(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $manual = Task::factory()->for($user)->tasks()->create();
        $block->linkedTasks()->attach($manual->id, ['sort_order' => 0, 'source' => 'manual']);

        $urgent = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::regenerate($user);

        $linked = $block->linkedTasks()->pluck('tasks.id')->all();
        $this->assertSame([$urgent->id], $linked);
    }

    public function test_homework_is_promoted_to_a_task_when_placed(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        // Homework's effective deadline is buffered 2 days earlier than its
        // real date (see WorkPlanner::DEADLINE_BUFFER_DAYS) — due today would
        // already be past that buffer and correctly unplaceable, so this uses
        // a date far enough out to still land in today's block.
        $entry = AgendaEntry::factory()->for($user)->homework()->duration(25)
            ->create(['date' => now()->addDays(3)->toDateString(), 'subject' => 'Bio', 'title' => 'Zellatmung']);

        WorkPlanner::reconcile($user);

        $task = Task::where('agenda_entry_id', $entry->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Bio: Zellatmung', $task->title);
        $this->assertSame(25, $task->duration_minutes);
        $this->assertTrue($block->linkedTasks()->whereKey($task->id)->exists());
    }

    public function test_an_already_promoted_homework_task_is_not_listed_twice(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => now()->toDateString()]);
        $existingTask = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(25)
            ->create(['agenda_entry_id' => $entry->id]);

        WorkPlanner::reconcile($user);

        $this->assertSame(1, $block->linkedTasks()->count());
        $this->assertSame($existingTask->id, $block->linkedTasks()->first()->id);
    }

    public function test_conflicts_lists_a_dated_task_with_no_timely_placement(): void
    {
        $user = $this->plannerUser();
        // Block is too small to ever fit the task.
        $this->workBlock($user, now()->toDateString(), '09:00', '09:10');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        WorkPlanner::reconcile($user);
        $conflicts = WorkPlanner::conflicts($user);

        $this->assertCount(1, $conflicts);
        $this->assertSame('task', $conflicts->first()['type']);
        $this->assertSame($task->id, $conflicts->first()['id']);
    }

    public function test_a_manual_placement_resolves_a_conflict_even_though_the_algorithm_would_not_have_chosen_it(): void
    {
        $user = $this->plannerUser();
        $tinyBlock = $this->workBlock($user, now()->toDateString(), '09:00', '09:10');
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        // Manually forced in despite not fitting cleanly — the user's own call.
        $tinyBlock->linkedTasks()->attach($task->id, ['sort_order' => 0, 'source' => 'manual']);

        $conflicts = WorkPlanner::conflicts($user);

        $this->assertCount(0, $conflicts);
    }

    /**
     * The repair pass: a less-urgent-but-perfectly-fitting task can win a
     * block's only slot in the first greedy pass, starving out a genuinely
     * more urgent task that can't be deferred (its deadline is today, so no
     * later block can ever take it). The repair pass must notice this and
     * relocate the less-urgent occupant to a later block it still meets its
     * own deadline in, freeing the slot for the urgent one.
     */
    public function test_repair_pass_rescues_a_starved_urgent_task_by_relocating_a_less_urgent_one(): void
    {
        $user = $this->plannerUser();
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $block1 = $this->workBlock($user, $today, '09:00', '09:40');       // 40 min
        $block2 = $this->workBlock($user, $tomorrow, '09:00', '10:00');    // 60 min

        $urgent = Task::factory()->for($user)->tasks()->dueDate($today)->duration(20)->create();
        $lessUrgent = Task::factory()->for($user)->tasks()->dueDate(now()->addDays(3)->toDateString())->duration(40)->create();

        WorkPlanner::reconcile($user);

        $this->assertTrue($block1->linkedTasks()->whereKey($urgent->id)->exists(), 'urgent task should end up in block 1');
        $this->assertTrue($block2->linkedTasks()->whereKey($lessUrgent->id)->exists(), 'bumped task should be relocated to block 2');
        $this->assertCount(0, WorkPlanner::conflicts($user));
    }

    public function test_undated_backlog_only_fills_leftover_space_after_dated_items(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:40'); // 40 min

        $dated = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(25)->create();
        $filler = Task::factory()->for($user)->todos()->duration(15)->create(); // no deadline/due_date

        WorkPlanner::reconcile($user);

        $linked = $block->linkedTasks()->pluck('tasks.id')->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$dated->id, $filler->id], $linked);
    }

    public function test_project_and_group_tasks_are_eligible_when_dated(): void
    {
        $user = $this->plannerUser();
        $block = $this->workBlock($user, now()->toDateString(), '09:00', '09:30');
        $project = \App\Models\Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->dueDate(now()->toDateString())->duration(30)
            ->create(['list' => 'projects', 'project_id' => $project->id]);

        WorkPlanner::reconcile($user);

        $this->assertTrue($block->linkedTasks()->whereKey($task->id)->exists());
    }
}
