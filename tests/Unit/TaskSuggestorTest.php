<?php

namespace Tests\Unit;

use App\Models\EventCategory;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use App\Services\TaskSuggestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSuggestorTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_one_suggests_clearing_todos_when_any_are_open(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create();
        Task::factory()->for($user)->todos()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1);

        $this->assertSame('todos', $suggestion['kind']);
        $this->assertSame('ToDos erledigen', $suggestion['title']);
        $this->assertSame('2 offen', $suggestion['subtitle']);
    }

    public function test_cycle_one_uses_singular_wording_for_a_single_open_todo(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1);

        $this->assertSame('1 offen', $suggestion['subtitle']);
    }

    public function test_cycle_one_falls_through_to_todays_task_when_no_todos_are_open(): void
    {
        $user = User::factory()->create();
        $today = Task::factory()->for($user)->tasks()->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_the_todos_nudge_never_reappears_after_the_first_cycle(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create(); // still open — would win at cycle 1
        $today = Task::factory()->for($user)->tasks()->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_todays_task_suggestion_follows_board_order(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->today()->create(['title' => 'normal']);
        $important = Task::factory()->for($user)->today()->important()->create(['title' => 'important']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame($important->id, $suggestion['task_id']);
    }

    public function test_completed_and_inbox_tasks_are_never_suggested(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->today()->completed()->create();
        Task::factory()->for($user)->inbox()->create();

        $this->assertNull(TaskSuggestor::suggest($user, cycle: 2, seedKey: 1));
    }

    public function test_fallback_suggests_another_task_from_todos_or_tasks_lists(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->tasks()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 5, seedKey: 1);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($task->id, $suggestion['task_id']);
    }

    public function test_fallback_never_suggests_a_standalone_projects_column_task(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => null]);

        $this->assertNull(TaskSuggestor::suggest($user, cycle: 5, seedKey: 1));
    }

    public function test_fallback_pick_is_stable_for_the_same_session_and_cycle(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->tasks()->count(5)->create();

        $first = TaskSuggestor::suggest($user, cycle: 5, seedKey: 42);
        $second = TaskSuggestor::suggest($user, cycle: 5, seedKey: 42);

        $this->assertSame($first, $second);
    }

    public function test_returns_null_when_there_is_nothing_to_suggest_at_all(): void
    {
        $user = User::factory()->create();

        $this->assertNull(TaskSuggestor::suggest($user, cycle: 1, seedKey: 1));
        $this->assertNull(TaskSuggestor::suggest($user, cycle: 5, seedKey: 1));
    }

    // ── Category task links ──────────────────────────────────────────

    public function test_category_link_to_pinned_tasks_suggests_them_in_pivot_order(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $first = Task::factory()->for($user)->todos()->create(['title' => 'First']);
        $second = Task::factory()->for($user)->todos()->create(['title' => 'Second']);
        $category->pinnedTasks()->attach($second->id, ['sort_order' => 0]);
        $category->pinnedTasks()->attach($first->id, ['sort_order' => 1]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($second->id, $suggestion['task_id']);
    }

    public function test_category_link_to_pinned_tasks_skips_completed_ones(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $done = Task::factory()->for($user)->todos()->completed()->create();
        $open = Task::factory()->for($user)->todos()->create(['title' => 'Still open']);
        $category->pinnedTasks()->attach($done->id, ['sort_order' => 0]);
        $category->pinnedTasks()->attach($open->id, ['sort_order' => 1]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame($open->id, $suggestion['task_id']);
    }

    public function test_category_link_to_text_always_suggests_the_same_text(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 7, seedKey: 99, category: $category);

        $this->assertSame('category_text', $suggestion['kind']);
        $this->assertSame('Zimmer aufräumen', $suggestion['title']);
    }

    public function test_category_link_applies_on_every_cycle_not_just_the_first(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create(); // would win cycle 1 without the link
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Immer das hier']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('category_text', $suggestion['kind']);
    }

    public function test_category_link_falls_through_when_no_category_is_passed(): void
    {
        $user = User::factory()->create();
        $today = Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_linked_source_remaining_count_reflects_each_source_type(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()->for($user)->todos()->create();
        $pinnedCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $pinnedCategory->pinnedTasks()->attach($task->id, ['sort_order' => 0]);
        $this->assertSame(1, TaskSuggestor::linkedSourceRemainingCount($pinnedCategory, $user));

        $textCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'x']);
        $this->assertNull(TaskSuggestor::linkedSourceRemainingCount($textCategory, $user));

        $noneCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => null]);
        $this->assertNull(TaskSuggestor::linkedSourceRemainingCount($noneCategory, $user));
    }

    public function test_linked_source_remaining_count_is_null_when_the_linked_project_was_deleted(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => null]);

        $this->assertNull(TaskSuggestor::linkedSourceRemainingCount($category, $user));
    }

    // ── Event-specific task link (more specific than the category link) ─────

    public function test_event_linked_task_wins_over_a_category_link(): void
    {
        $user = User::factory()->create();
        $eventTask = Task::factory()->for($user)->todos()->create(['title' => 'Testlauf mit Tempowechseln']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Sollte nicht erscheinen']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category, linkedTask: $eventTask);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($eventTask->id, $suggestion['task_id']);
    }

    public function test_event_linked_task_falls_through_to_the_category_link_once_completed(): void
    {
        $user = User::factory()->create();
        $eventTask = Task::factory()->for($user)->todos()->completed()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category, linkedTask: $eventTask);

        $this->assertSame('category_text', $suggestion['kind']);
        $this->assertSame('Zimmer aufräumen', $suggestion['title']);
    }

    private function planForToday(User $user, Task $task, int $order = 0): void
    {
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => now()->toDateString(), 'sort_order' => $order]);
    }

    public function test_planner_day_plan_suggests_the_first_task_in_arranged_order(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $second = Task::factory()->for($user)->tasks()->create(['title' => 'Zweite Aufgabe']);
        $first = Task::factory()->for($user)->tasks()->create(['title' => 'Erste Aufgabe']);
        $this->planForToday($user, $second, 1);
        $this->planForToday($user, $first, 0);

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame('planned', $suggestion['kind']);
        $this->assertSame($first->id, $suggestion['task_id']);
        $this->assertSame('Tagesplan', $suggestion['subtitle']);
    }

    public function test_planner_suggestion_is_skipped_entirely_when_the_feature_is_disabled(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $planned = Task::factory()->for($user)->tasks()->create();
        $today = Task::factory()->for($user)->today()->create();
        TaskDayPlan::create(['task_id' => $planned->id, 'planned_date' => now()->toDateString(), 'sort_order' => 0]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        // Falls straight through to the ordinary "today" tier — the planned task never even
        // considered, since planner_enabled gates the tier off completely.
        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_planner_suggestion_wins_over_the_cycle_one_todos_nudge(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        Task::factory()->for($user)->todos()->create(); // would win cycle 1 without the plan
        $planned = Task::factory()->for($user)->tasks()->create();
        $this->planForToday($user, $planned);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1);

        $this->assertSame('planned', $suggestion['kind']);
        $this->assertSame($planned->id, $suggestion['task_id']);
    }

    public function test_planner_suggestion_wins_over_the_any_cycle_today_task(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        Task::factory()->for($user)->today()->create(); // would otherwise win
        $planned = Task::factory()->for($user)->tasks()->create();
        $this->planForToday($user, $planned);

        $suggestion = TaskSuggestor::suggest($user, cycle: 3, seedKey: 1);

        $this->assertSame('planned', $suggestion['kind']);
        $this->assertSame($planned->id, $suggestion['task_id']);
    }

    public function test_a_category_link_still_outranks_a_planner_suggestion(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $planned = Task::factory()->for($user)->tasks()->create();
        $this->planForToday($user, $planned);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Kategorie gewinnt']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('category_text', $suggestion['kind']);
    }

    public function test_an_event_linked_task_still_outranks_a_planner_suggestion(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $planned = Task::factory()->for($user)->tasks()->create();
        $this->planForToday($user, $planned);
        $eventTask = Task::factory()->for($user)->todos()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, linkedTask: $eventTask);

        $this->assertSame($eventTask->id, $suggestion['task_id']);
    }

    public function test_planner_suggestion_ignores_a_task_planned_for_another_day(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $tomorrowTask = Task::factory()->for($user)->tasks()->create();
        TaskDayPlan::create(['task_id' => $tomorrowTask->id, 'planned_date' => now()->addDay()->toDateString(), 'sort_order' => 0]);
        $today = Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_planner_suggestion_skips_a_meanwhile_completed_task(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $done = Task::factory()->for($user)->tasks()->completed()->create();
        $this->planForToday($user, $done);
        $today = Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame($today->id, $suggestion['task_id']);
    }
}
