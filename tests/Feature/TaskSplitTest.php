<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\StreakDayOutcome;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\TaskSession;
use App\Models\User;
use App\Services\DayPlanner;
use App\Services\ProgressStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Aufteilen": one task spread over several calendar days (task_sessions). */
class TaskSplitTest extends TestCase
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

    private function workBlock(User $user, string $date, string $start = '09:00', string $end = '11:00'): void
    {
        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        ScheduleEvent::factory()->for($user)->on($date)->at($start, $end)->create(['category_id' => $category->id]);
    }

    private function split(User $user, Task $task, array $offsets, int $total = 90): void
    {
        $this->assertTrue(DayPlanner::saveSplit($user, $task, array_map(fn ($o) => $this->day($o), $offsets), $total));
    }

    public function test_split_creates_one_part_per_day_with_evenly_divided_minutes(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $this->split($user, $task, [1, 3, 5], 90);

        $parts = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get();
        $this->assertCount(3, $parts);
        $this->assertSame([30, 30, 30], $parts->pluck('minutes')->all());
        $this->assertSame([$this->day(1), $this->day(3), $this->day(5)], $parts->map(fn ($p) => $p->planned_date->toDateString())->all());
    }

    public function test_minutes_are_rounded_to_five(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $this->split($user, $task, [1, 2, 3], 100); // 33.3 -> 35

        $this->assertSame([35, 35, 35], TaskSession::where('task_id', $task->id)->pluck('minutes')->all());
    }

    public function test_the_task_day_plan_mirrors_the_earliest_open_part(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $this->split($user, $task, [4, 2, 6]);

        $this->assertSame(1, TaskDayPlan::where('task_id', $task->id)->count());
        $this->assertSame($this->day(2), TaskDayPlan::where('task_id', $task->id)->first()->planned_date->toDateString());
    }

    public function test_split_needs_at_least_two_parts(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $this->assertFalse(DayPlanner::saveSplit($user, $task, [$this->day(1)], 60));
        $this->assertSame(0, TaskSession::count());
    }

    public function test_split_refuses_days_outside_the_horizon_and_the_past(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $this->assertFalse(DayPlanner::saveSplit($user, $task, [$this->day(-1), $this->day(DayPlanner::HORIZON_DAYS)], 60));
        $this->assertSame(0, TaskSession::count());
    }

    public function test_split_is_refused_when_the_planner_is_off_or_the_task_is_foreign_or_inbox(): void
    {
        $off = User::factory()->create(['planner_enabled' => false]);
        $user = $this->plannerUser();
        $mine = Task::factory()->for($off)->tasks()->create();
        $foreign = Task::factory()->for($user)->tasks()->create();
        $inbox = Task::factory()->for($user)->create(['list' => 'inbox']);

        $this->assertFalse(DayPlanner::saveSplit($off, $mine, [$this->day(1), $this->day(2)], 60));
        $this->assertFalse(DayPlanner::saveSplit($off, $foreign, [$this->day(1), $this->day(2)], 60));
        $this->assertFalse(DayPlanner::saveSplit($user, $inbox, [$this->day(1), $this->day(2)], 60));
        $this->assertSame(0, TaskSession::count());
    }

    public function test_board_shows_each_part_on_its_own_day_and_the_task_leaves_the_backlog(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create(['title' => 'Aufsatz']);

        $this->split($user, $task, [1, 3]);

        $board = DayPlanner::board($user);
        $first = $board->get($this->day(1))['tasks']->first();
        $second = $board->get($this->day(3))['tasks']->first();

        $this->assertSame('session', $first['type']);
        $this->assertSame('Teil 1/2', $first['partLabel']);
        $this->assertSame('Teil 2/2', $second['partLabel']);
        $this->assertSame('Aufsatz', $first['title']);
        $this->assertFalse(DayPlanner::backlog($user)->contains('id', $task->id));
    }

    public function test_a_part_counts_against_the_days_capacity_with_its_own_minutes(): void
    {
        $user = $this->plannerUser();
        $this->workBlock($user, $this->day(1));
        $task = Task::factory()->for($user)->tasks()->create();

        $this->split($user, $task, [1, 2], 80);

        $this->assertSame(40, DayPlanner::board($user)->get($this->day(1))['capacityUsed']);
    }

    public function test_dragging_a_part_to_another_day_moves_only_that_part_and_moves_the_anchor(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [2, 4]);
        $first = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        DayPlanner::assignDay($user, $this->day(6), ["session:{$first->id}"]);

        $dates = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get()->map(fn ($p) => $p->planned_date->toDateString())->all();
        $this->assertSame([$this->day(4), $this->day(6)], $dates);
        $this->assertSame($this->day(4), TaskDayPlan::where('task_id', $task->id)->first()->planned_date->toDateString());
    }

    public function test_a_part_cannot_land_on_a_day_that_already_has_another_part_of_the_same_task(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [2, 4]);
        $first = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        DayPlanner::moveToDay($user, "session:{$first->id}", $this->day(4));

        $this->assertSame($this->day(2), $first->fresh()->planned_date->toDateString());
    }

    public function test_a_foreign_part_id_is_ignored(): void
    {
        $user = $this->plannerUser();
        $other = $this->plannerUser();
        $task = Task::factory()->for($other)->tasks()->create();
        $this->split($other, $task, [2, 4]);
        $part = TaskSession::where('task_id', $task->id)->first();

        DayPlanner::assignDay($user, $this->day(6), ["session:{$part->id}"]);
        DayPlanner::removeSession($user, $part->id);
        $this->assertNull(DayPlanner::completeSession($user, $part->id));

        $this->assertSame(2, TaskSession::where('task_id', $task->id)->count());
        $this->assertNull($part->fresh()->done_at);
    }

    public function test_removing_a_part_keeps_the_others_and_a_lone_leftover_becomes_an_ordinary_placement(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [2, 4]);
        $second = TaskSession::where('task_id', $task->id)->orderByDesc('planned_date')->first();

        DayPlanner::removeSession($user, $second->id);

        $this->assertSame(0, TaskSession::where('task_id', $task->id)->count());
        $plain = DayPlanner::board($user)->get($this->day(2))['tasks']->first();
        $this->assertSame('task', $plain['type']);
        $this->assertSame($task->id, $plain['id']);
    }

    public function test_completing_a_part_moves_the_anchor_to_the_next_open_part(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [0, 3, 5]);
        $first = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        $result = DayPlanner::completeSession($user, $first->id);

        $this->assertSame(1, $result['progress']['done']);
        $this->assertSame(2, $result['progress']['position']);
        $this->assertSame($this->day(3), TaskDayPlan::where('task_id', $task->id)->first()->planned_date->toDateString());
        $this->assertFalse($task->fresh()->is_completed, 'ticking a part never completes the task');
    }

    public function test_completing_todays_part_takes_the_task_off_heute(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [0, 3]);
        $this->assertTrue($task->fresh()->is_today, 'a part planned for today puts the task on Heute');

        DayPlanner::completeSession($user, TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first()->id);

        $fresh = $task->fresh();
        $this->assertFalse($fresh->is_today);
        $this->assertNull($fresh->today_date);
    }

    public function test_completing_the_last_part_removes_the_anchor_and_flags_all_done_without_completing_the_task(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);

        foreach (TaskSession::where('task_id', $task->id)->get() as $part) {
            DayPlanner::completeSession($user, $part->id);
        }

        $this->assertSame(0, TaskDayPlan::where('task_id', $task->id)->count());
        $this->assertFalse($task->fresh()->is_completed);
        $item = DayPlanner::backlog($user)->firstWhere('id', $task->id);
        $this->assertNotNull($item);
        $this->assertSame('alle Teile erledigt', $item['partLabel']);
    }

    public function test_reopening_a_part_brings_the_anchor_back(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);
        $first = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();

        DayPlanner::completeSession($user, $first->id);
        DayPlanner::completeSession($user, $first->id, false);

        $this->assertNull($first->fresh()->done_at);
        $this->assertSame($this->day(1), TaskDayPlan::where('task_id', $task->id)->first()->planned_date->toDateString());
    }

    public function test_re_splitting_keeps_done_parts_and_reuses_open_rows_on_unchanged_days(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2, 3]);
        $parts = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get();
        DayPlanner::completeSession($user, $parts[0]->id);

        // Keep day 2, drop day 3, add day 5; the done day-1 part must survive.
        $this->assertTrue(DayPlanner::saveSplit($user, $task->fresh(), [$this->day(2), $this->day(5)], 60));

        $after = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get();
        $this->assertSame([$this->day(1), $this->day(2), $this->day(5)], $after->map(fn ($p) => $p->planned_date->toDateString())->all());
        $this->assertNotNull($after[0]->done_at);
        $this->assertSame($parts[1]->id, $after[1]->id, 'a part on an unchanged day keeps its row');
    }

    public function test_a_done_parts_day_is_not_available_for_a_new_part(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);
        DayPlanner::completeSession($user, TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first()->id);

        DayPlanner::saveSplit($user, $task->fresh(), [$this->day(1), $this->day(4)], 60);

        $this->assertSame(1, TaskSession::where('task_id', $task->id)->whereDate('planned_date', $this->day(1))->count());
        $this->assertSame([$this->day(1), $this->day(4)], TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get()->map(fn ($p) => $p->planned_date->toDateString())->all());
    }

    public function test_unsplit_returns_the_task_to_the_backlog(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);

        DayPlanner::unsplit($user, $task);

        $this->assertSame(0, TaskSession::count());
        $this->assertSame(0, TaskDayPlan::count());
        $this->assertTrue(DayPlanner::backlog($user)->contains('id', $task->id));
    }

    public function test_dragging_an_all_done_split_task_from_the_backlog_replans_it_as_a_whole(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);
        foreach (TaskSession::where('task_id', $task->id)->get() as $part) {
            DayPlanner::completeSession($user, $part->id);
        }

        DayPlanner::assignDay($user, $this->day(3), ["task:{$task->id}"]);

        $this->assertSame(0, TaskSession::count());
        $this->assertSame('task', DayPlanner::board($user)->get($this->day(3))['tasks']->first()['type']);
    }

    public function test_suggestion_prefers_days_with_room_and_stays_before_the_deadline(): void
    {
        $user = $this->plannerUser();
        // Days 0 and 1 have no block; days 2..5 each have a two-hour block.
        foreach ([2, 3, 4, 5] as $o) {
            $this->workBlock($user, $this->day($o));
        }
        $task = Task::factory()->for($user)->tasks()->create(['deadline' => now()->addDays(DayPlanner::DEADLINE_BUFFER_DAYS + 5)->toDateString()]);

        $dates = DayPlanner::suggestSplitDates($user, $task, 3, 90);

        $this->assertCount(3, $dates);
        foreach ($dates as $date) {
            $this->assertContains($date, [$this->day(2), $this->day(3), $this->day(4), $this->day(5)]);
        }
        $this->assertSame($dates, collect($dates)->sort()->values()->all());
    }

    public function test_suggestion_without_a_deadline_takes_the_earliest_days(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $dates = DayPlanner::suggestSplitDates($user, $task, 3, 90);

        $this->assertSame([$this->day(0), $this->day(1), $this->day(2)], $dates);
    }

    public function test_suggestion_never_proposes_a_day_after_the_buffered_deadline(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create(['deadline' => now()->addDays(DayPlanner::DEADLINE_BUFFER_DAYS + 3)->toDateString()]);

        $dates = DayPlanner::suggestSplitDates($user, $task, 4, 120);

        $this->assertNotEmpty($dates);
        foreach ($dates as $date) {
            $this->assertLessThanOrEqual($this->day(3), $date);
        }
    }

    public function test_a_rolled_over_part_is_offered_as_a_part_chip(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 3]);
        TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first()->update(['planned_date' => now()->subDay()->toDateString()]);
        TaskDayPlan::where('task_id', $task->id)->update(['planned_date' => now()->subDay()->toDateString()]);

        $item = DayPlanner::rollover($user)->first();

        $this->assertSame('session', $item['type']);
        $this->assertSame('Teil 1/2', $item['partLabel']);
    }

    public function test_finishing_the_only_open_item_of_todays_set_by_ticking_its_part_makes_the_day_perfect(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [0, 2]);

        $result = DayPlanner::completeSession($user, TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first()->id);

        $this->assertSame('perfect-day', $result['celebration']['kind']);
        $this->assertSame(1, StreakDayOutcome::where('user_id', $user->id)->count());
        $this->assertSame(1, ProgressStats::currentStreak($user));
    }

    public function test_deleting_the_task_removes_its_parts(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->split($user, $task, [1, 2]);

        $task->delete();

        $this->assertSame(0, TaskSession::count());
    }
}
