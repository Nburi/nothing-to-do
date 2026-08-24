<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleEventTaskLinkTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_set_event_linked_task_updates_the_form_state(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create(['title' => 'Startlisten prüfen']);

        $component = Livewire::test(Schedule::class)->call('setEventLinkedTask', $task->id);

        $this->assertSame($task->id, $component->get('eventLinkedTaskId'));
        $this->assertSame('Startlisten prüfen', $component->get('eventLinkedTaskTitle'));
    }

    public function test_setting_a_foreign_task_is_rejected(): void
    {
        $this->actingUser();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('setEventLinkedTask', $foreignTask->id);
    }

    public function test_clear_event_linked_task_resets_the_form_state(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        $component = Livewire::test(Schedule::class)
            ->call('setEventLinkedTask', $task->id)
            ->call('clearEventLinkedTask');

        $this->assertNull($component->get('eventLinkedTaskId'));
        $this->assertSame('', $component->get('eventLinkedTaskTitle'));
    }

    public function test_creating_a_one_off_appointment_persists_the_linked_task(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Bio lernen')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->set('eventColor', 'overprint')
            ->call('setEventLinkedTask', $task->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'title' => 'Bio lernen',
            'linked_task_id' => $task->id,
        ]);
    }

    public function test_creating_a_category_block_persists_the_linked_task(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['name' => 'Training']);
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '16:00')
            ->call('setEventLinkedTask', $task->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'linked_task_id' => $task->id,
        ]);
    }

    public function test_editing_an_event_updates_its_linked_task(): void
    {
        $user = $this->actingUser();
        $original = Task::factory()->for($user)->todos()->create();
        $replacement = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create(['linked_task_id' => $original->id]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->call('setEventLinkedTask', $replacement->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertSame($replacement->id, $event->refresh()->linked_task_id);
    }

    public function test_editing_an_event_can_clear_its_linked_task(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create(['linked_task_id' => $task->id]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->call('clearEventLinkedTask')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertNull($event->refresh()->linked_task_id);
    }

    public function test_starting_to_edit_an_event_preloads_its_linked_task(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create(['title' => 'Ausrüstung checken']);
        $event = ScheduleEvent::factory()->for($user)->create(['linked_task_id' => $task->id]);

        $component = Livewire::test(Schedule::class)->call('startEditEvent', $event->id);

        $this->assertSame($task->id, $component->get('eventLinkedTaskId'));
        $this->assertSame('Ausrüstung checken', $component->get('eventLinkedTaskTitle'));
    }

    public function test_a_recurring_events_materialised_occurrences_never_get_a_linked_task(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(Schedule::class)
            ->set('weekStart', '2026-06-22') // Mon
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Schule')
            ->set('eventDate', '2026-06-22')
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:30')
            ->set('eventColor', 'contour')
            ->set('eventRecurring', true)
            ->set('eventDays', [1, 2, 3])
            ->call('setEventLinkedTask', $task->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $occurrences = ScheduleEvent::forUser($user)->whereNotNull('template_id')->get();
        $this->assertCount(3, $occurrences);
        $this->assertTrue($occurrences->every(fn (ScheduleEvent $e) => $e->linked_task_id === null));
    }

    public function test_a_tampered_linked_task_id_is_silently_dropped_at_save_time(): void
    {
        $user = $this->actingUser();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();

        // Bypasses setEventLinkedTask()'s own ownership check to simulate a
        // crafted request — saveEventForm() must re-check independently.
        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Zahnarzt')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->set('eventColor', 'contour')
            ->set('eventLinkedTaskId', $foreignTask->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_events', ['title' => 'Zahnarzt', 'linked_task_id' => null]);
    }

    public function test_event_task_candidates_are_anchored_to_the_events_own_date_not_today(): void
    {
        $user = $this->actingUser();
        // Due 2 days after the event's date, far from "today".
        $nearEvent = Task::factory()->for($user)->todos()->create(['title' => 'Near the event', 'deadline' => '2026-07-02']);
        // Due soon relative to *today*, but nowhere near the event's date.
        $nearToday = Task::factory()->for($user)->todos()->create(['title' => 'Near today', 'deadline' => now()->addDay()->toDateString()]);

        $component = Livewire::test(Schedule::class)->set('eventDate', '2026-06-30');

        $ids = $component->instance()->eventTaskCandidates->pluck('id')->all();

        $this->assertContains($nearEvent->id, $ids);
        $this->assertNotContains($nearToday->id, $ids);
    }

    public function test_event_task_candidates_search_overrides_the_date_window(): void
    {
        $user = $this->actingUser();
        $farAway = Task::factory()->for($user)->todos()->create(['title' => 'Ganz weit weg', 'deadline' => '2027-01-01']);
        Task::factory()->for($user)->todos()->create(['title' => 'Anderer Titel']);

        $component = Livewire::test(Schedule::class)
            ->set('eventDate', '2026-06-26')
            ->set('eventTaskSearch', 'Ganz weit');

        $ids = $component->instance()->eventTaskCandidates->pluck('id')->all();

        $this->assertSame([$farAway->id], $ids);
    }

    public function test_navigate_to_linked_task_redirects_to_the_board_with_the_task_query_param(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create(['linked_task_id' => $task->id]);

        Livewire::test(Schedule::class)
            ->call('navigateToLinkedTask', $event->id)
            ->assertRedirect(route('app', ['task' => $task->id]));
    }

    public function test_navigate_to_linked_task_is_a_no_op_without_a_link(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->create(['linked_task_id' => null]);

        Livewire::test(Schedule::class)
            ->call('navigateToLinkedTask', $event->id)
            ->assertNoRedirect();
    }

    public function test_a_user_cannot_navigate_via_another_users_event(): void
    {
        $this->actingUser();
        $otherUser = User::factory()->create();
        $event = ScheduleEvent::factory()->for($otherUser)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('navigateToLinkedTask', $event->id);
    }
}
