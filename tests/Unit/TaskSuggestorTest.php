<?php

namespace Tests\Unit;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
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

    public function test_fallback_suggests_a_projects_next_task_when_todos_and_today_are_empty(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $next = Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'title' => 'Kapitel 1', 'sort_order' => 0]);
        Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'title' => 'Kapitel 2', 'sort_order' => 1]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 5, seedKey: 1);

        $this->assertSame('project', $suggestion['kind']);
        $this->assertSame($project->id, $suggestion['project_id']);
        $this->assertSame('Kapitel 1', $suggestion['subtitle']);
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

    public function test_category_link_to_project_suggests_its_next_task_over_a_today_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'title' => 'Kapitel 1', 'sort_order' => 0]);
        Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'title' => 'Kapitel 2', 'sort_order' => 1]);
        Task::factory()->for($user)->today()->create(); // would otherwise win
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 3, seedKey: 1, category: $category);

        $this->assertSame('project', $suggestion['kind']);
        $this->assertSame($project->id, $suggestion['project_id']);
        $this->assertSame('Kapitel 1', $suggestion['subtitle']);
    }

    public function test_category_link_to_group_suggests_its_next_task(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create(['name' => 'Referat']);
        Task::factory()->for($user)->for($group, 'group')->create(['list' => 'todos', 'title' => 'Folien bauen', 'sort_order' => 0]);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'group', 'linked_group_id' => $group->id]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('category_group', $suggestion['kind']);
        $this->assertSame($group->id, $suggestion['group_id']);
        $this->assertSame('Folien bauen', $suggestion['subtitle']);
    }

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

    public function test_category_link_to_agenda_entry_suggests_it_until_done(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['subject' => 'Mathematik', 'title' => 'Übungsblatt 3']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'agenda_entry', 'linked_agenda_entry_id' => $entry->id]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);
        $this->assertSame('category_agenda', $suggestion['kind']);
        $this->assertSame($entry->id, $suggestion['agenda_entry_id']);
        $this->assertSame('Mathematik', $suggestion['subtitle']);

        $entry->toggleDoneFor($user);

        $this->assertNull(TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category));
    }

    public function test_category_link_to_agenda_generic_counts_open_homework_only(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->homework()->create();
        AgendaEntry::factory()->for($user)->homework()->create();
        AgendaEntry::factory()->for($user)->exam()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'agenda_generic']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('agenda_generic', $suggestion['kind']);
        $this->assertSame('2 offen', $suggestion['subtitle']);
    }

    public function test_category_link_to_agenda_generic_falls_through_once_homework_is_clear(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->homework()->done()->create();
        $today = Task::factory()->for($user)->tasks()->today()->create(); // list=tasks, not todos, so it doesn't also win via the cycle-1 tier
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'agenda_generic']);

        $suggestion = TaskSuggestor::suggest($user, cycle: 1, seedKey: 1, category: $category);

        $this->assertSame($today->id, $suggestion['task_id']);
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

    public function test_category_link_falls_through_to_generic_logic_once_its_source_is_empty(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(); // no tasks in it
        $today = Task::factory()->for($user)->today()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1, category: $category);

        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_category_link_falls_through_when_no_category_is_passed(): void
    {
        $user = User::factory()->create();
        $today = Task::factory()->for($user)->today()->create();

        $suggestion = TaskSuggestor::suggest($user, cycle: 2, seedKey: 1);

        $this->assertSame($today->id, $suggestion['task_id']);
    }

    public function test_emergency_mode_still_outranks_a_category_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $emergencyTask = Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'sort_order' => 0]);
        $user->update(['emergency_project_id' => $project->id]);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Sollte nicht erscheinen']);

        $suggestion = TaskSuggestor::suggest($user->fresh(), cycle: 1, seedKey: 1, category: $category);

        $this->assertSame('emergency', $suggestion['kind']);
        $this->assertSame($emergencyTask->id, $suggestion['task_id']);
    }

    public function test_linked_source_remaining_count_reflects_each_source_type(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['list' => 'projects']);
        $projectCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        $this->assertSame(1, TaskSuggestor::linkedSourceRemainingCount($projectCategory, $user));

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
}
