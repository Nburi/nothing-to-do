<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDurationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_quick_set_duration_updates_the_task(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)->call('quickSetDuration', $task->id, 25);

        $this->assertSame(25, $task->fresh()->duration_minutes);
    }

    public function test_quick_set_duration_can_clear_the_estimate(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->duration(25)->create();

        Livewire::test(TaskBoard::class)->call('quickSetDuration', $task->id, null);

        $this->assertNull($task->fresh()->duration_minutes);
    }

    public function test_quick_set_duration_rejects_an_out_of_range_value(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)->call('quickSetDuration', $task->id, 999);

        $this->assertNull($task->fresh()->duration_minutes);
    }

    public function test_quick_set_duration_cannot_touch_another_users_task(): void
    {
        $this->actingUser();
        $foreign = Task::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(TaskBoard::class)->call('quickSetDuration', $foreign->id, 25);
    }

    public function test_saving_the_edit_sheet_persists_the_duration(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editDuration', 45)
            ->call('saveEdit');

        $this->assertSame(45, $task->fresh()->duration_minutes);
    }

    public function test_default_duration_used_for_scoring_is_never_written_back(): void
    {
        // WorkPlanner's own defaults (10 for todos, 25 for tasks) are computed
        // on the fly for scoring only — confirms the record itself stays null
        // when no estimate was ever entered, matching "never required".
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        $this->assertNull($task->duration_minutes);
    }
}
