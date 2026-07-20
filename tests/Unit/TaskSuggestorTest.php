<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
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
}
