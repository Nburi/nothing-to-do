<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The 'celebrate' browser event is the hook the Alpine overlay listens for
 * (see the 'celebration' store in app.js) — these tests only cover that the
 * two real completion call sites (ManagesTasks::toggleComplete,
 * Schedule::toggleDeadlineTaskDone) dispatch it exactly when
 * ProgressStats::celebrationFor() says to. The threshold math itself is
 * covered exhaustively in tests/Unit/Services/ProgressStatsTest.php.
 */
class TaskCelebrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_completing_a_task_that_reaches_the_daily_goal_celebrates(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)
            ->call('toggleComplete', $task->id)
            ->assertDispatched('celebrate');
    }

    public function test_completing_a_task_that_stays_below_the_goal_does_not_celebrate(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 5]);
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)
            ->call('toggleComplete', $task->id)
            ->assertNotDispatched('celebrate');
    }

    public function test_un_completing_a_task_never_celebrates(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->completed()->create();

        Livewire::test(TaskBoard::class)
            ->call('toggleComplete', $task->id) // false -> true is already done; this flips true -> false
            ->assertNotDispatched('celebrate');
    }

    public function test_ticking_a_task_off_from_the_zeitplan_deadline_strip_also_celebrates(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['deadline' => '2026-08-16']);

        Livewire::test(Schedule::class)
            ->call('toggleDeadlineTaskDone', $task->id)
            ->assertDispatched('celebrate');
    }

    public function test_a_second_task_the_same_day_does_not_refire_the_same_goal_celebration(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'daily_task_goal' => 1]);
        $this->actingAs($user);

        $first = Task::factory()->for($user)->todos()->create();
        $second = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)->call('toggleComplete', $first->id)->assertDispatched('celebrate');
        Livewire::test(TaskBoard::class)->call('toggleComplete', $second->id)->assertNotDispatched('celebrate');
    }
}
