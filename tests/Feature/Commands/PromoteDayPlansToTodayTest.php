<?php

namespace Tests\Feature\Commands;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromoteDayPlansToTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plannerUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['planner_enabled' => true, 'timezone_offset' => 0], $attrs));
    }

    private function planFor(Task $task, string $date): void
    {
        TaskDayPlan::create(['task_id' => $task->id, 'planned_date' => $date, 'sort_order' => 0, 'source' => 'manual']);
    }

    public function test_a_task_planned_for_today_is_promoted(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-16');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $task->refresh();
        $this->assertTrue($task->is_today);
        $this->assertSame('2026-08-16', $task->today_date->toDateString());
    }

    public function test_a_task_planned_for_a_past_day_that_never_got_promoted_is_caught_up(): void
    {
        // e.g. the server was down, or (in local dev) nothing ran the scheduler for a
        // few days — the "<=", not "=" catch-up shape every other command here uses.
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-13');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertTrue($task->fresh()->is_today);
    }

    public function test_a_task_planned_for_a_future_day_is_not_promoted(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-17');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_running_the_command_twice_does_not_restamp_an_already_promoted_task(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-16');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();
        $firstStamp = $task->fresh()->today_date->toDateString();

        Carbon::setTestNow('2026-08-16 08:05:00');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertSame($firstStamp, $task->fresh()->today_date->toDateString());
    }

    public function test_a_project_owned_tasks_day_plan_is_never_promoted(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        $this->planFor($task, '2026-08-16');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_users_with_the_planner_disabled_are_skipped(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser(['planner_enabled' => false]);
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-16');

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_each_users_day_plans_are_promoted_independently_in_one_run(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $userA = $this->plannerUser();
        $userB = $this->plannerUser();
        $taskA = Task::factory()->for($userA)->tasks()->create();
        $taskB = Task::factory()->for($userB)->tasks()->create();
        $this->planFor($taskA, '2026-08-16');
        $this->planFor($taskB, '2026-08-17'); // not yet due

        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertTrue($taskA->fresh()->is_today);
        $this->assertFalse($taskB->fresh()->is_today);
    }
}
