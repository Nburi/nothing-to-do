<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_flagged_today_appears_in_today_and_todos_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $board = Livewire::test(TaskBoard::class)->instance();

        $this->assertTrue($board->today()->contains('id', $task->id));
        $this->assertTrue($board->todosToday()->contains('id', $task->id));
    }

    public function test_a_task_not_flagged_today_stays_out_of_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['is_today' => false]);

        $board = Livewire::test(TaskBoard::class)->instance();

        $this->assertFalse($board->today()->contains('id', $task->id));
        $this->assertTrue($board->todosRest()->contains('id', $task->id));
    }

    public function test_set_today_toggles_the_flag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->tasks()->create(['is_today' => false]);

        Livewire::test(TaskBoard::class)->call('setToday', $task->id, true);
        $this->assertTrue($task->refresh()->is_today);

        Livewire::test(TaskBoard::class)->call('setToday', $task->id, false);
        $this->assertFalse($task->refresh()->is_today);
    }

    public function test_swipe_today_and_untoday_toggle_the_flag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['is_today' => false]);

        Livewire::test(TaskBoard::class)->call('swipeIntent', $task->id, 'today');
        $this->assertTrue($task->refresh()->is_today);

        Livewire::test(TaskBoard::class)->call('swipeIntent', $task->id, 'untoday');
        $this->assertFalse($task->refresh()->is_today);
    }
}
