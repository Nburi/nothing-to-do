<?php

namespace Tests\Feature\Commands;

use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Deleted tasks are kept for 30 days (for "Rückgängig"), then removed for good. */
class PurgeDeletedTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-12-01 12:00:00');
        $this->travelTo($this->now);
    }

    private function deletedAgo(\DateInterval|string $ago, ?User $owner = null, array $extra = []): Task
    {
        $task = Task::factory()->for($owner ?? User::factory()->create())->create($extra);
        $task->delete();
        Task::withTrashed()->whereKey($task->id)->update(['deleted_at' => $this->now->sub(is_string($ago) ? \DateInterval::createFromDateString($ago) : $ago)]);

        return $task;
    }

    public function test_it_purges_tasks_deleted_more_than_30_days_ago(): void
    {
        $old = $this->deletedAgo('31 days');
        $oldMinute = $this->deletedAgo('30 days 1 minute');

        $this->artisan('app:purge-deleted')->assertSuccessful()->expectsOutputToContain('Purged 2 task(s)');

        $this->assertDatabaseMissing('tasks', ['id' => $old->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $oldMinute->id]);
    }

    public function test_the_boundary_day_is_kept_until_it_is_really_older_than_30_days(): void
    {
        $exactly30 = $this->deletedAgo('30 days');
        $almost30 = $this->deletedAgo('29 days 23 hours 59 minutes');
        $yesterday = $this->deletedAgo('1 day');

        $this->artisan('app:purge-deleted')->assertSuccessful()->expectsOutputToContain('Purged 0 task(s)');

        foreach ([$exactly30, $almost30, $yesterday] as $task) {
            $this->assertNotNull(Task::onlyTrashed()->find($task->id), "task {$task->id} must still be restorable");
        }

        // A minute later the exactly-30-days one is older than 30 days.
        $this->travelTo($this->now->addMinute());
        $this->artisan('app:purge-deleted')->assertSuccessful()->expectsOutputToContain('Purged 1 task(s)');
        $this->assertDatabaseMissing('tasks', ['id' => $exactly30->id]);
        $this->assertNotNull(Task::onlyTrashed()->find($almost30->id));
    }

    public function test_it_never_touches_tasks_that_are_not_deleted_however_old(): void
    {
        $user = User::factory()->create();
        $open = Task::factory()->for($user)->create(['created_at' => $this->now->subYears(2)]);
        $done = Task::factory()->for($user)->create(['is_completed' => true, 'completed_at' => $this->now->subYear(), 'created_at' => $this->now->subYears(2)]);
        $trash = $this->deletedAgo('90 days', $user);

        $this->artisan('app:purge-deleted')->assertSuccessful();

        $this->assertNotNull(Task::find($open->id));
        $this->assertNotNull(Task::find($done->id));
        $this->assertDatabaseMissing('tasks', ['id' => $trash->id]);
        $this->assertSame(2, Task::forUser($user)->count());
    }

    public function test_it_removes_the_day_plan_of_a_purged_task_and_leaves_the_rest_alone(): void
    {
        $user = User::factory()->create();
        $gone = $this->deletedAgo('40 days', $user);
        $kept = Task::factory()->for($user)->create();
        TaskDayPlan::create(['task_id' => $gone->id, 'planned_date' => $this->now->toDateString(), 'sort_order' => 0]);
        TaskDayPlan::create(['task_id' => $kept->id, 'planned_date' => $this->now->toDateString(), 'sort_order' => 1]);

        $this->artisan('app:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('task_day_plans', ['task_id' => $gone->id]);
        $this->assertDatabaseHas('task_day_plans', ['task_id' => $kept->id]);
    }

    public function test_the_task_a_purged_task_was_waiting_on_or_repeated_from_survives(): void
    {
        $user = User::factory()->create();
        $parent = Task::factory()->for($user)->create();
        $child = $this->deletedAgo('45 days', $user, ['repeated_from_id' => $parent->id]);
        $kept = Task::factory()->for($user)->create(['repeated_from_id' => $child->id]);

        $this->artisan('app:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('tasks', ['id' => $child->id]);
        $this->assertNotNull(Task::find($parent->id));
        $this->assertNull($kept->fresh()->repeated_from_id, 'the link is let go, the task stays');
    }

    public function test_the_dry_run_only_counts(): void
    {
        $old = $this->deletedAgo('60 days');

        $this->artisan('app:purge-deleted', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Would purge 1 task(s)');

        $this->assertNotNull(Task::onlyTrashed()->find($old->id));
    }

    public function test_it_runs_with_nothing_to_do_and_is_scheduled_daily(): void
    {
        $this->artisan('app:purge-deleted')->assertSuccessful()->expectsOutputToContain('Purged 0 task(s)');

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'app:purge-deleted'));
        $this->assertNotNull($event, 'app:purge-deleted must be in the scheduler');
        $this->assertSame('30 3 * * *', $event->expression);
    }
}
