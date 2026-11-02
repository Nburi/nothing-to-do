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

    public function test_notice_appears_once_the_last_pinned_task_is_completed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::factory()->for($user)->todos()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $category->pinnedTasks()->attach($task->id, ['sort_order' => 0]);
        $this->runningSessionFor($user, $category);

        $task->update(['is_completed' => true, 'completed_at' => now()]);

        $component = Livewire::test(TaskBoard::class);

        $this->assertSame('Die ausgewählten Aufgaben sind fertig.', $component->instance()->linkedSourceNotice);
        $component->assertSee('Die ausgewählten Aufgaben sind fertig.');
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

        $textCategory = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);
        $this->runningSessionFor($user, $textCategory);
        Livewire::test(TaskBoard::class)->assertSee('Zimmer aufräumen');
    }

    public function test_notice_is_null_before_the_session_has_ever_been_started(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        // No pinned tasks at all — already "empty" — but the timer was never started.
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
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
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
