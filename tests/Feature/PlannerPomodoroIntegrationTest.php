<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Confirms WorkPlanner::reconcile() actually fires from the Pomodoro
 * lifecycle (PomodoroSessionService::start()/transition()/skipBreak()), not
 * just when someone happens to be looking at the Planer page — see the
 * brainstorming discussion in CLAUDE.md's Kategorie-Aufgaben-Verknüpfung
 * section for why this matters (a stale plan showing during a live session).
 */
class PlannerPomodoroIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_focus_session_reconciles_the_plan_first(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $this->actingAs($user);

        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $block = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('09:00', '09:30')
            ->create(['category_id' => $category->id]);
        $task = Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $block->id);

        $this->assertTrue($block->linkedTasks()->whereKey($task->id)->exists());
    }

    public function test_reconcile_is_a_no_op_when_planner_is_disabled_even_during_a_session(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $this->actingAs($user);

        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $block = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('09:00', '09:30')
            ->create(['category_id' => $category->id]);
        Task::factory()->for($user)->tasks()->dueDate(now()->toDateString())->duration(30)->create();

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $block->id);

        $this->assertSame(0, $block->linkedTasks()->count());
    }
}
