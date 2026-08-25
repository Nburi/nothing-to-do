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

/**
 * The event-level task link's own copy of the signature moment from the category
 * link (BoardCategoryLinkNoticeTest) — but for several bound tasks, not one, so
 * the notice must wait for the *last* one, not the first.
 */
class BoardEventTaskLinkNoticeTest extends TestCase
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

    public function test_suggestion_advances_to_the_next_task_as_each_one_completes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $first = Task::factory()->for($user)->todos()->create(['title' => 'Erste Aufgabe']);
        $second = Task::factory()->for($user)->todos()->create(['title' => 'Zweite Aufgabe']);
        $event = $this->runningSessionFor($user, $category);
        $event->linkedTasks()->attach($first->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach($second->id, ['sort_order' => 1]);

        $suggestion = Livewire::test(TaskBoard::class)->instance()->taskSuggestion;
        $this->assertSame($first->id, $suggestion['task_id']);

        $first->update(['is_completed' => true, 'completed_at' => now()]);

        $suggestion = Livewire::test(TaskBoard::class)->instance()->taskSuggestion;
        $this->assertSame($second->id, $suggestion['task_id']);
    }

    public function test_notice_stays_null_while_any_bound_task_is_still_open(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $event = $this->runningSessionFor($user, $category);
        $event->linkedTasks()->attach(Task::factory()->for($user)->todos()->completed()->create()->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach(Task::factory()->for($user)->todos()->create()->id, ['sort_order' => 1]);

        $notice = Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice;

        $this->assertNull($notice);
    }

    public function test_notice_appears_once_the_last_bound_task_is_completed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $first = Task::factory()->for($user)->todos()->create();
        $second = Task::factory()->for($user)->todos()->create();
        $event = $this->runningSessionFor($user, $category);
        $event->linkedTasks()->attach($first->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach($second->id, ['sort_order' => 1]);

        $first->update(['is_completed' => true, 'completed_at' => now()]);
        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->linkedSourceNotice);

        $second->update(['is_completed' => true, 'completed_at' => now()]);
        $component = Livewire::test(TaskBoard::class);

        $this->assertSame('Die gebundenen Aufgaben sind fertig.', $component->instance()->linkedSourceNotice);
        $component->assertSee('Die gebundenen Aufgaben sind fertig.');
    }

    public function test_event_link_takes_priority_over_the_categorys_own_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = \App\Models\Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['list' => 'projects', 'title' => 'Kategorie-Vorschlag']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);
        $eventTask = Task::factory()->for($user)->todos()->create(['title' => 'Eintrags-Vorschlag']);
        $event = $this->runningSessionFor($user, $category);
        $event->linkedTasks()->attach($eventTask->id, ['sort_order' => 0]);

        $suggestion = Livewire::test(TaskBoard::class)->instance()->taskSuggestion;

        $this->assertSame($eventTask->id, $suggestion['task_id']);
    }
}
