<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BoardTodayFocusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_planned_for_today_appears_in_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $planned = Task::factory()->for($user)->todos()->create([
            'is_today' => false,
            'planned_for' => Carbon::today()->toDateString(),
        ]);

        $board = Livewire::test(TaskBoard::class)->instance();

        $this->assertTrue($board->today()->contains('id', $planned->id));
        $this->assertTrue($board->todosToday()->contains('id', $planned->id));
    }

    public function test_a_task_planned_for_tomorrow_is_not_in_today_yet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $future = Task::factory()->for($user)->todos()->create([
            'is_today' => false,
            'planned_for' => Carbon::tomorrow()->toDateString(),
        ]);

        $board = Livewire::test(TaskBoard::class)->instance();

        $this->assertFalse($board->today()->contains('id', $future->id));   // not in Today yet
        $this->assertTrue($board->todosRest()->contains('id', $future->id)); // but still on the board
    }

    public function test_leaving_today_clears_the_planned_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->tasks()->create([
            'is_today' => true,
            'planned_for' => Carbon::today()->toDateString(),
        ]);

        Livewire::test(TaskBoard::class)->call('setToday', $task->id, false);

        $task->refresh();
        $this->assertFalse($task->is_today);
        $this->assertNull($task->planned_for);
    }

    public function test_swiping_untoday_clears_the_planned_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create([
            'is_today' => true,
            'planned_for' => Carbon::today()->toDateString(),
        ]);

        Livewire::test(TaskBoard::class)->call('swipeIntent', $task->id, 'untoday');

        $this->assertNull($task->refresh()->planned_for);
    }

    public function test_morning_nudge_only_shows_inside_the_morning_window(): void
    {
        $user = User::factory()->create(['brief_when' => 'morning', 'brief_time' => '07:00']);
        $this->actingAs($user);

        Carbon::setTestNow('2026-06-26 08:00');
        $this->assertTrue(Livewire::test(TaskBoard::class)->instance()->showBriefNudge());

        Carbon::setTestNow('2026-06-26 18:00'); // afternoon — past the morning window
        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showBriefNudge());

        Carbon::setTestNow();
    }

    public function test_evening_nudge_shows_from_the_configured_time(): void
    {
        $user = User::factory()->create(['brief_when' => 'evening', 'brief_time' => '19:00']);
        $this->actingAs($user);

        Carbon::setTestNow('2026-06-26 17:00');
        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showBriefNudge());

        Carbon::setTestNow('2026-06-26 20:30');
        $this->assertTrue(Livewire::test(TaskBoard::class)->instance()->showBriefNudge());

        Carbon::setTestNow();
    }
}
