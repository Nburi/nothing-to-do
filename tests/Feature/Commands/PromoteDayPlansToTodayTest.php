<?php

namespace Tests\Feature\Commands;

use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use App\Services\DayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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

    // ── Regression: a task the user removed from Heute must stay removed ──

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function removedFromTodayScenarios(): array
    {
        return [
            'todo' => ['todos', []],
            'task' => ['tasks', []],
            'todo with hard deadline' => ['todos', ['deadline' => '2026-08-20']],
            'task with hard deadline' => ['tasks', ['deadline' => '2026-08-20']],
            'todo with Wunschtermin' => ['todos', ['due_date' => '2026-08-19']],
            'task with Wunschtermin' => ['tasks', ['due_date' => '2026-08-19']],
        ];
    }

    #[DataProvider('removedFromTodayScenarios')]
    public function test_a_task_removed_from_today_does_not_jump_back_on_later_days(string $list, array $extra): void
    {
        // Day 1: planned for today, the cron promotes it, the user never finishes it.
        Carbon::setTestNow('2026-08-15 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->create(array_merge(['list' => $list], $extra));
        $this->planFor($task, '2026-08-15');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();
        $this->assertTrue($task->fresh()->is_today);

        // Day 2: the user removes it from Heute themselves.
        Carbon::setTestNow('2026-08-16 09:00:00');
        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, false);
        $this->assertFalse($task->fresh()->is_today);

        // The scheduler keeps ticking — minutes, hours and days later.
        foreach (['2026-08-16 09:01:00', '2026-08-16 15:00:00', '2026-08-17 00:01:00', '2026-08-20 08:00:00'] as $moment) {
            Carbon::setTestNow($moment);
            $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

            $fresh = $task->fresh();
            $this->assertFalse($fresh->is_today, "{$list} bounced back into Heute at {$moment}");
            $this->assertNull($fresh->today_date);
        }
    }

    public function test_a_task_removed_from_today_on_its_planned_day_does_not_bounce_back_within_that_day(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-16');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, false);

        Carbon::setTestNow('2026-08-16 08:01:00');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertFalse($task->fresh()->is_today);
    }

    public function test_removal_through_the_simple_and_eisenhower_boards_sticks_too(): void
    {
        Carbon::setTestNow('2026-08-15 08:00:00');
        $user = $this->plannerUser();
        $simple = Task::factory()->for($user)->tasks()->create();
        $eisenhower = Task::factory()->for($user)->todos()->create();
        $this->planFor($simple, '2026-08-15');
        $this->planFor($eisenhower, '2026-08-15');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        Carbon::setTestNow('2026-08-16 09:00:00');
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('setTodaySimple', $simple->id, false)
            ->call('setTodayEisenhower', $eisenhower->id, false);

        Carbon::setTestNow('2026-08-17 09:00:00');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        $this->assertFalse($simple->fresh()->is_today);
        $this->assertFalse($eisenhower->fresh()->is_today);
    }

    public function test_replanning_a_removed_task_for_a_new_day_promotes_it_again_when_that_day_arrives(): void
    {
        Carbon::setTestNow('2026-08-15 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-15');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();

        Carbon::setTestNow('2026-08-16 09:00:00');
        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, false);

        // The user deliberately plans it for Wednesday: that is a fresh decision.
        DayPlanner::moveToDay($user, "task:{$task->id}", '2026-08-19');

        Carbon::setTestNow('2026-08-18 09:00:00');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();
        $this->assertFalse($task->fresh()->is_today, 'Not due yet on the 18th');

        Carbon::setTestNow('2026-08-19 00:05:00');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();
        $this->assertTrue($task->fresh()->is_today);
    }

    public function test_explicitly_placing_a_removed_task_on_today_promotes_it_again(): void
    {
        Carbon::setTestNow('2026-08-16 08:00:00');
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->planFor($task, '2026-08-16');
        $this->artisan('app:promote-day-plans-to-today')->assertSuccessful();
        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, false);

        // A drag onto today in the Planer is an explicit decision, unlike the cron tick.
        DayPlanner::moveToDay($user, "task:{$task->id}", '2026-08-16');

        $this->assertTrue($task->fresh()->is_today);
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
