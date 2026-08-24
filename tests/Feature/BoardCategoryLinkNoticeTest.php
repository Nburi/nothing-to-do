<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Signature moment: a quiet, self-dismissing notice when a category-linked list empties out during a running session. */
class BoardCategoryLinkNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function runningSessionFor(User $user, EventCategory $category): ScheduleEvent
    {
        return ScheduleEvent::factory()->for($user)->for($category, 'category')->create([
            'date' => $user->localToday()->toDateString(),
            'pomodoro_phase' => 'work',
            'pomodoro_cycle' => 1,
            'pomodoro_started_at' => now(),
            'pomodoro_linked_notified' => false,
        ]);
    }

    public function test_notice_appears_once_a_linked_projects_last_task_is_completed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create(['name' => 'Wettkampfvorbereitung']);
        $task = Task::factory()->for($user)->for($project)->create(['list' => 'projects']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        $this->runningSessionFor($user, $category);

        $task->update(['is_completed' => true, 'completed_at' => now()]);

        $component = Livewire::test(TaskBoard::class);

        $this->assertSame('Wettkampfvorbereitung ist fertig.', $component->instance()->linkedSourceNotice);
        // Also exercises the real Blade render (schedule-strip.blade.php's new
        // $linkedNotice branch) end to end, not just the PHP-level computed value.
        $component->assertSee('Wettkampfvorbereitung ist fertig.');
    }

    /**
     * Renders the real Blade output for every new TaskSuggestor 'kind' introduced by
     * this feature (category_group / category_agenda / agenda_generic / category_text),
     * so a typo'd route name or Blade syntax error in schedule-strip-suggestion.blade.php
     * fails a test instead of only surfacing when someone happens to click through it.
     */
    public function test_every_new_suggestion_kind_renders_without_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $group = TaskGroup::factory()->for($user)->create(['name' => 'Referat']);
        Task::factory()->for($user)->for($group, 'group')->create(['list' => 'todos', 'title' => 'Folien bauen']);
        $groupCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'group', 'linked_group_id' => $group->id]);
        $groupSession = $this->runningSessionFor($user, $groupCategory);
        Livewire::test(TaskBoard::class)->assertSee('Referat')->assertSee('Folien bauen');
        $groupSession->delete();

        $entry = AgendaEntry::factory()->for($user)->homework()->create(['subject' => 'Mathematik', 'title' => 'Übungsblatt 3']);
        $agendaCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'agenda_entry', 'linked_agenda_entry_id' => $entry->id]);
        $agendaSession = $this->runningSessionFor($user, $agendaCategory);
        Livewire::test(TaskBoard::class)->assertSee('Mathematik')->assertSee('Übungsblatt 3');
        $agendaSession->delete();
        $entry->toggleDoneFor($user); // keep the next block's "open homework" count self-contained

        AgendaEntry::factory()->for($user)->homework()->create();
        $genericCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'agenda_generic']);
        $genericSession = $this->runningSessionFor($user, $genericCategory);
        Livewire::test(TaskBoard::class)->assertSee('Hausaufgaben erledigen')->assertSee('1 offen');
        $genericSession->delete();

        $textCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);
        $this->runningSessionFor($user, $textCategory);
        Livewire::test(TaskBoard::class)->assertSee('Zimmer aufräumen');
    }

    public function test_notice_is_null_while_the_linked_project_still_has_open_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['list' => 'projects']);
        Task::factory()->for($user)->for($project)->create(['list' => 'projects']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        $this->runningSessionFor($user, $category);

        $notice = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;

        $this->assertNull($notice);
    }

    public function test_notice_is_null_before_the_session_has_ever_been_started(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        // No tasks in the project at all — already "empty" — but the timer was never started.
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create([
            'date' => $user->localToday()->toDateString(),
            'pomodoro_phase' => null,
        ]);

        $notice = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;

        $this->assertNull($notice);
    }

    public function test_notice_is_null_for_a_free_text_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Etwas Freies']);
        $this->runningSessionFor($user, $category);

        $notice = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;

        $this->assertNull($notice);
    }

    public function test_dismiss_stamps_the_flag_so_the_notice_does_not_reappear(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        $event = $this->runningSessionFor($user, $category);

        $before = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;
        $this->assertNotNull($before);

        Livewire::test(TaskBoard::class)->call('dismissLinkedSourceNotice');

        $this->assertTrue($event->refresh()->pomodoro_linked_notified);
        $after = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;
        $this->assertNull($after);
    }

    public function test_starting_a_fresh_session_resets_the_notified_flag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'x']);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create([
            'date' => $user->localToday()->toDateString(),
            'pomodoro_phase' => 'work',
            'pomodoro_started_at' => now(),
            'pomodoro_linked_notified' => true,
        ]);

        Livewire::test(TaskBoard::class)->call('stopFocusTimer', $event->id);
        $this->assertFalse($event->refresh()->pomodoro_linked_notified);

        $event->update(['pomodoro_linked_notified' => true]);
        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);
        $this->assertFalse($event->refresh()->pomodoro_linked_notified);
    }
}
