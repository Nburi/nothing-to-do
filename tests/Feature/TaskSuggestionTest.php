<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TaskSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pomodoroUser(): User
    {
        return User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
    }

    public function test_no_suggestion_without_a_focus_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->taskSuggestion());
    }

    public function test_bereit_state_previews_the_first_cycles_suggestion(): void
    {
        $user = $this->pomodoroUser();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 13:56');

        Task::factory()->for($user)->todos()->create();

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $suggestion = Livewire::test(TaskBoard::class)->instance()->taskSuggestion();

        $this->assertSame('todos', $suggestion['kind']);
    }

    public function test_no_suggestion_during_a_break_even_with_a_valid_candidate(): void
    {
        $user = $this->pomodoroUser();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:26:00'); // 26' elapsed on a 25' rhythm → short break

        Task::factory()->for($user)->today()->create();

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')
            ->create(['pomodoro_started_at' => '2026-06-26 14:00:00']);

        $instance = Livewire::test(TaskBoard::class)->instance();

        $this->assertSame('short_break', $instance->focusPhase()['phase']);
        $this->assertNull($instance->taskSuggestion());
    }

    public function test_suggestion_reflects_the_running_work_cycle(): void
    {
        $user = $this->pomodoroUser();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:30:00'); // 30' elapsed → second work cycle

        $today = Task::factory()->for($user)->today()->create();

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')
            ->create(['pomodoro_started_at' => '2026-06-26 14:00:00']);

        $instance = Livewire::test(TaskBoard::class)->instance();

        $this->assertSame(2, $instance->focusPhase()['cycle']);
        $suggestion = $instance->taskSuggestion();
        $this->assertSame('task', $suggestion['kind']);
        $this->assertSame($today->id, $suggestion['task_id']);
    }
}
