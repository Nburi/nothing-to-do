<?php

namespace Tests\Feature;

use App\Livewire\Planner;
use App\Livewire\Schedule;
use App\Livewire\SplitTaskSheet;
use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\TaskSession;
use App\Models\User;
use App\Services\DayPlanner;
use App\Services\TaskSuggestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The split sheet, the part marker on cards, and every place a part can be ticked off. */
class SplitTaskSheetTest extends TestCase
{
    use RefreshDatabase;

    private function plannerUser(): User
    {
        return User::factory()->create(['planner_enabled' => true]);
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    private function splitTask(User $user, array $offsets = [0, 2, 4], array $attrs = []): Task
    {
        $task = Task::factory()->for($user)->tasks()->create($attrs + ['title' => 'Aufsatz schreiben']);
        DayPlanner::saveSplit($user, $task, array_map(fn ($o) => $this->day($o), $offsets), 90);

        return $task->fresh();
    }

    public function test_opening_the_sheet_loads_a_task_and_proposes_days_for_a_fresh_split(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create(['duration_minutes' => 120]);

        $component = Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $task->id);

        $component->assertSet('taskId', $task->id);
        $config = $component->instance()->sheetConfig;
        $this->assertCount(DayPlanner::HORIZON_DAYS, $config['days']);
        $this->assertCount(DayPlanner::defaultPartCount(120), $config['selected']);
        $this->assertSame(120, $config['total']);
    }

    public function test_the_sheet_refuses_a_foreign_task_an_inbox_task_and_a_user_without_planner(): void
    {
        $user = $this->plannerUser();
        $foreign = Task::factory()->for($this->plannerUser())->tasks()->create();
        $inbox = Task::factory()->for($user)->create(['list' => 'inbox']);
        $off = User::factory()->create(['planner_enabled' => false]);
        $offTask = Task::factory()->for($off)->tasks()->create();

        Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $foreign->id)->assertSet('taskId', null);
        Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $inbox->id)->assertSet('taskId', null);
        Livewire::actingAs($off)->test(SplitTaskSheet::class)->call('open', $offTask->id)->assertSet('taskId', null);
    }

    public function test_saving_creates_the_parts_closes_the_sheet_and_tells_the_page_to_refresh(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(SplitTaskSheet::class)
            ->call('open', $task->id)
            ->call('save', [$this->day(1), $this->day(3)], 90)
            ->assertSet('taskId', null)
            ->assertDispatched('plan-changed');

        $this->assertSame(2, TaskSession::where('task_id', $task->id)->count());
    }

    public function test_saving_a_single_day_shows_a_friendly_error_and_keeps_the_sheet_open(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(SplitTaskSheet::class)
            ->call('open', $task->id)
            ->call('save', [$this->day(1)], 60)
            ->assertSet('taskId', $task->id)
            ->assertHasErrors('split');

        $this->assertSame(0, TaskSession::count());
    }

    public function test_saving_days_outside_the_horizon_shows_a_friendly_error(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(SplitTaskSheet::class)
            ->call('open', $task->id)
            ->call('save', [$this->day(40), $this->day(41)], 60)
            ->assertHasErrors('split');

        $this->assertSame(0, TaskSession::count());
    }

    public function test_suggest_returns_dates_for_the_open_task(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $dates = Livewire::actingAs($user)->test(SplitTaskSheet::class)
            ->call('open', $task->id)
            ->instance()->suggest(3, 90);

        $this->assertCount(3, $dates);
    }

    public function test_an_already_split_task_opens_with_its_open_parts_selected_and_its_parts_listed(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user);
        DayPlanner::completeSession($user, TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first()->id);

        $component = Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $task->id);

        $config = $component->instance()->sheetConfig;
        $this->assertSame([$this->day(2), $this->day(4)], $config['selected']);
        $this->assertSame(1, $config['doneCount']);
        $component->assertSee('Bisherige Teile')->assertSee('Aufteilung ändern');
    }

    public function test_toggling_a_part_in_the_sheet_ticks_and_reopens_it(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user);
        $part = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        $component = Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $task->id);
        $component->call('togglePart', $part->id)->assertDispatched('plan-changed');
        $this->assertNotNull($part->fresh()->done_at);

        $component->call('togglePart', $part->id);
        $this->assertNull($part->fresh()->done_at);
    }

    public function test_toggling_a_part_of_someone_elses_task_does_nothing(): void
    {
        $user = $this->plannerUser();
        $other = $this->plannerUser();
        $task = $this->splitTask($other);
        $part = TaskSession::where('task_id', $task->id)->first();
        $mine = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $mine->id)->call('togglePart', $part->id);

        $this->assertNull($part->fresh()->done_at);
    }

    public function test_unsplit_removes_every_part_and_closes(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user);

        Livewire::actingAs($user)->test(SplitTaskSheet::class)->call('open', $task->id)->call('unsplit')->assertSet('taskId', null);

        $this->assertSame(0, TaskSession::count());
        $this->assertSame(0, TaskDayPlan::count());
    }

    public function test_the_board_card_shows_the_part_marker_and_the_tick_once_a_part_is_due(): void
    {
        $user = $this->plannerUser();
        $this->splitTask($user, [0, 3]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSee('Teil 1/2')
            ->assertSee('Teil erledigt');
    }

    public function test_the_board_card_has_no_tick_before_the_part_is_due(): void
    {
        $user = $this->plannerUser();
        $this->splitTask($user, [2, 3]);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->assertSee('Teil 1/2')
            ->assertDontSee('Teil erledigt');
    }

    public function test_the_board_offers_to_finish_the_task_once_every_part_is_ticked(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user, [0, 1]);
        foreach (TaskSession::where('task_id', $task->id)->get() as $part) {
            DayPlanner::completeSession($user, $part->id);
        }

        Livewire::actingAs($user)->test(TaskBoard::class)->assertSee('Aufgabe abhaken?');
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_no_part_marker_without_the_planner(): void
    {
        $user = $this->plannerUser();
        $this->splitTask($user, [0, 3]);
        $user->update(['planner_enabled' => false]);

        Livewire::actingAs($user->fresh())->test(TaskBoard::class)->assertDontSee('Teil 1/2');
    }

    public function test_ticking_a_part_from_the_board_card_works_and_is_scoped_to_the_owner(): void
    {
        $user = $this->plannerUser();
        $other = $this->plannerUser();
        $task = $this->splitTask($user, [0, 3]);
        $theirs = $this->splitTask($other, [0, 3]);
        $part = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();
        $foreignPart = TaskSession::where('task_id', $theirs->id)->first();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('completeTaskPart', $foreignPart->id)
            ->call('completeTaskPart', $part->id);

        $this->assertNull($foreignPart->fresh()->done_at);
        $this->assertNotNull($part->fresh()->done_at);
    }

    public function test_the_edit_sheet_links_to_the_split_sheet_only_with_the_planner_on(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('startEdit', $task->id)->assertSee('Auf mehrere Tage aufteilen');

        $user->update(['planner_enabled' => false]);
        Livewire::actingAs($user->fresh())->test(TaskBoard::class)->call('startEdit', $task->id)->assertDontSee('Auf mehrere Tage aufteilen');
    }

    public function test_the_planer_shows_part_chips_and_can_tick_and_remove_a_part(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user, [0, 1, 2]);
        $parts = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get();

        $component = Livewire::actingAs($user)->test(Planner::class)->assertSee('Teil 1/3')->assertSee('Teil 2/3');

        $component->call('completeSession', $parts[0]->id);
        $this->assertNotNull($parts[0]->fresh()->done_at);

        $component->call('removeSession', $parts[2]->id);
        $this->assertNull(TaskSession::find($parts[2]->id));
        $this->assertSame(2, TaskSession::where('task_id', $task->id)->count());
    }

    public function test_the_planer_moves_a_part_with_a_session_token(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user, [1, 3]);
        $part = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        Livewire::actingAs($user)->test(Planner::class)->call('assignDay', $this->day(6), ["session:{$part->id}"]);

        $this->assertSame($this->day(6), $part->fresh()->planned_date->toDateString());
    }

    public function test_the_zeitplan_strip_lists_each_part_and_ticks_only_that_part(): void
    {
        $user = $this->plannerUser();
        $task = $this->splitTask($user, [0, 1]);
        $part = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        $component = Livewire::actingAs($user)->test(Schedule::class)->assertSee('Teil 1/2');
        $component->call('toggleDeadlinePartDone', $part->id);

        $this->assertNotNull($part->fresh()->done_at);
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_the_focus_suggestion_names_the_part(): void
    {
        $user = $this->plannerUser();
        $this->splitTask($user, [0, 2]);

        $suggestion = TaskSuggestor::suggest($user, 1, 1);

        $this->assertSame('planned', $suggestion['kind']);
        $this->assertSame('Tagesplan · Teil 1/2', $suggestion['subtitle']);
    }
}
