<?php

namespace Tests\Feature;

use App\Livewire\PrepareTomorrow;
use App\Livewire\ProjectPage;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every one of the (many) places that write is_today must also stamp/preserve/
 * clear today_date correctly — see Task::todayDateFor(). Verified per site
 * rather than trusting the shared helper alone, since a call site can still
 * wire it in wrong (wrong target date, or skip it in one branch).
 */
class TodayDateStampingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── TaskBoard::setToday ──────────────────────────────────────────────

    public function test_set_today_stamps_todays_date_when_entering(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, true);

        $this->assertSame('2026-08-18', $task->fresh()->today_date->toDateString());
    }

    public function test_set_today_true_on_an_already_today_task_preserves_its_date(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        // Re-flagging something already today (e.g. re-saving via a UI path)
        // must not silently move it onto today's date.
        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, true);

        $this->assertSame('2026-08-14', $task->fresh()->today_date->toDateString());
    }

    public function test_set_today_false_clears_the_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('setToday', $task->id, false);

        $this->assertNull($task->fresh()->today_date);
    }

    // ── TaskBoard::reorder (drag & drop) ─────────────────────────────────

    public function test_reorder_into_today_stamps_new_entries_but_preserves_old_leftovers(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $fresh = Task::factory()->for($user)->todos()->create();
        $leftover = Task::factory()->for($user)->todos()->todayOn('2026-08-10')->create();

        // Both end up in the Today zone in one drag — mirrors dragging a new
        // card in alongside one that was already sitting there.
        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorder', 'todos', true, [$fresh->id, $leftover->id]);

        $this->assertSame('2026-08-18', $fresh->fresh()->today_date->toDateString());
        $this->assertSame('2026-08-10', $leftover->fresh()->today_date->toDateString());
    }

    public function test_reorder_out_of_today_clears_the_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('reorder', 'todos', false, [$task->id]);

        $this->assertNull($task->fresh()->today_date);
    }

    // ── TaskBoard::swipeIntent ────────────────────────────────────────────

    public function test_swipe_today_stamps_the_date(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('swipeIntent', $task->id, 'today');

        $this->assertSame('2026-08-18', $task->fresh()->today_date->toDateString());
    }

    public function test_swipe_untoday_clears_the_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('swipeIntent', $task->id, 'untoday');

        $this->assertNull($task->fresh()->today_date);
    }

    // ── Leaving Today via project assignment / edit sheet ────────────────

    public function test_dragging_onto_a_project_card_clears_the_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('assignTaskToProject', $task->id, $project->id);

        $this->assertNull($task->fresh()->today_date);
    }

    public function test_project_pages_assign_to_project_clears_the_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(ProjectPage::class, ['project' => $project])
            ->call('assignToProject', $task->id);

        $this->assertNull($task->fresh()->today_date);
    }

    public function test_edit_sheet_moving_a_task_to_the_inbox_clears_the_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editTitle', $task->title)
            ->set('editList', 'inbox')
            ->call('saveEdit');

        $this->assertNull($task->fresh()->today_date);
    }

    public function test_edit_sheet_assigning_a_project_clears_the_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editTitle', $task->title)
            ->set('editProjectId', $project->id)
            ->call('saveEdit');

        $this->assertNull($task->fresh()->today_date);
    }

    // ── PrepareTomorrow::markToday ───────────────────────────────────────

    public function test_mark_today_stamps_tomorrow_in_evening_mode(): void
    {
        Carbon::setTestNow('2026-08-18 20:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'prepare_time_of_day' => 'evening']);
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(PrepareTomorrow::class)->call('markToday', $task->id);

        // Evening prep flags tasks for TOMORROW, not the still-running today —
        // the whole reason today_date exists rather than reusing "now".
        $this->assertSame('2026-08-19', $task->fresh()->today_date->toDateString());
    }

    public function test_mark_today_stamps_today_in_morning_mode(): void
    {
        Carbon::setTestNow('2026-08-18 08:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'prepare_time_of_day' => 'morning']);
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(PrepareTomorrow::class)->call('markToday', $task->id);

        $this->assertSame('2026-08-18', $task->fresh()->today_date->toDateString());
    }

    public function test_mark_today_preserves_an_existing_date(): void
    {
        Carbon::setTestNow('2026-08-18 20:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'prepare_time_of_day' => 'evening']);
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-10')->create();

        Livewire::actingAs($user)->test(PrepareTomorrow::class)->call('markToday', $task->id);

        $this->assertSame('2026-08-10', $task->fresh()->today_date->toDateString());
    }

    // ── API: PATCH /api/tasks/{id} ───────────────────────────────────────

    public function test_api_update_stamps_todays_date_when_entering(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['is_today' => true])->assertOk();

        $this->assertSame('2026-08-18', $task->fresh()->today_date->toDateString());
    }

    public function test_api_update_preserves_the_date_when_already_today(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['is_today' => true])->assertOk();

        $this->assertSame('2026-08-14', $task->fresh()->today_date->toDateString());
    }

    public function test_api_update_clears_the_date_when_assigning_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->todayOn('2026-08-14')->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['project_id' => $project->id])->assertOk();

        $this->assertNull($task->fresh()->today_date);
    }

    // ── API: POST /api/tasks/reorder ─────────────────────────────────────

    public function test_api_reorder_into_today_stamps_new_entries_but_preserves_old_leftovers(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $user = User::factory()->create(['timezone_offset' => 0]);
        $fresh = Task::factory()->for($user)->todos()->create();
        $leftover = Task::factory()->for($user)->todos()->todayOn('2026-08-10')->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks/reorder', [
            'list' => 'todos',
            'today' => true,
            'ids' => [$fresh->id, $leftover->id],
        ])->assertOk();

        $this->assertSame('2026-08-18', $fresh->fresh()->today_date->toDateString());
        $this->assertSame('2026-08-10', $leftover->fresh()->today_date->toDateString());
    }
}
