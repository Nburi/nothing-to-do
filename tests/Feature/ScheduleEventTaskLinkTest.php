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

    public function test_toggle_event_linked_task_adds_and_removes(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->todos()->create(['title' => 'Startlisten prüfen']);

        $component = Livewire::test(Schedule::class)->call('toggleEventLinkedTask', $task->id);
        $this->assertSame([['id' => $task->id, 'title' => 'Startlisten prüfen']], $component->get('eventLinkedTasks'));

        $component->call('toggleEventLinkedTask', $task->id);
        $this->assertSame([], $component->get('eventLinkedTasks'));
    }

    public function test_toggle_event_linked_task_supports_several_at_once(): void
    {
        $user = $this->actingUser();
        $first = Task::factory()->for($user)->todos()->create();
        $second = Task::factory()->for($user)->todos()->create();

        $component = Livewire::test(Schedule::class)
            ->call('toggleEventLinkedTask', $first->id)
            ->call('toggleEventLinkedTask', $second->id);

        $this->assertSame([$first->id, $second->id], collect($component->get('eventLinkedTasks'))->pluck('id')->all());
    }

    public function test_adding_a_foreign_task_is_rejected(): void
    {
        $this->actingUser();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('toggleEventLinkedTask', $foreignTask->id);
    }

    public function test_creating_a_one_off_appointment_persists_multiple_linked_tasks(): void
    {
        $user = $this->actingUser();
        $first = Task::factory()->for($user)->todos()->create();
        $second = Task::factory()->for($user)->todos()->create();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Bio lernen')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->set('eventColor', 'overprint')
            ->call('toggleEventLinkedTask', $first->id)
            ->call('toggleEventLinkedTask', $second->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('title', 'Bio lernen')->firstOrFail();
        $this->assertSame([$first->id, $second->id], $event->linkedTasks()->pluck('tasks.id')->all());
    }

    public function test_creating_a_category_block_persists_linked_tasks(): void
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
            ->call('toggleEventLinkedTask', $task->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('category_id', $category->id)->firstOrFail();
        $this->assertSame([$task->id], $event->linkedTasks()->pluck('tasks.id')->all());
    }

    public function test_editing_an_event_can_add_and_remove_linked_tasks(): void
    {
        $user = $this->actingUser();
        $kept = Task::factory()->for($user)->todos()->create();
        $removed = Task::factory()->for($user)->todos()->create();
        $added = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        $event->linkedTasks()->attach($kept->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach($removed->id, ['sort_order' => 1]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->call('toggleEventLinkedTask', $removed->id) // already picked -> removes
            ->call('toggleEventLinkedTask', $added->id) // not picked -> adds
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertSame([$kept->id, $added->id], $event->linkedTasks()->pluck('tasks.id')->all());
    }

    public function test_starting_to_edit_preloads_all_bound_tasks_including_completed_ones(): void
    {
        $user = $this->actingUser();
        $open = Task::factory()->for($user)->todos()->create(['title' => 'Offen']);
        $done = Task::factory()->for($user)->todos()->completed()->create(['title' => 'Erledigt']);
        $event = ScheduleEvent::factory()->for($user)->create();
        $event->linkedTasks()->attach($open->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach($done->id, ['sort_order' => 1]);

        $component = Livewire::test(Schedule::class)->call('startEditEvent', $event->id);

        $this->assertSame(
            ['Offen', 'Erledigt'],
            collect($component->get('eventLinkedTasks'))->pluck('title')->all()
        );
    }

    public function test_a_recurring_events_materialised_occurrences_never_get_linked_tasks(): void
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
            ->call('toggleEventLinkedTask', $task->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $occurrences = ScheduleEvent::forUser($user)->whereNotNull('template_id')->get();
        $this->assertCount(3, $occurrences);
        $this->assertTrue($occurrences->every(fn (ScheduleEvent $e) => $e->linkedTasks()->count() === 0));
    }

    public function test_a_tampered_linked_task_list_silently_drops_the_foreign_entry_at_save_time(): void
    {
        $user = $this->actingUser();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();

        // Bypasses toggleEventLinkedTask()'s own ownership check to simulate a
        // crafted request — saveEventForm() must re-check independently.
        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Zahnarzt')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->set('eventColor', 'contour')
            ->set('eventLinkedTasks', [['id' => $foreignTask->id, 'title' => 'x']])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('title', 'Zahnarzt')->firstOrFail();
        $this->assertSame(0, $event->linkedTasks()->count());
    }

    public function test_event_task_candidates_exclude_already_picked_tasks(): void
    {
        $user = $this->actingUser();
        $today = $user->localToday();
        $picked = Task::factory()->for($user)->todos()->create(['title' => 'Schon gewählt', 'due_date' => $today->toDateString()]);
        $notPicked = Task::factory()->for($user)->todos()->create(['title' => 'Noch nicht', 'due_date' => $today->toDateString()]);

        $component = Livewire::test(Schedule::class)
            ->set('eventDate', $today->toDateString())
            ->call('toggleEventLinkedTask', $picked->id);

        $ids = $component->instance()->eventTaskCandidates->pluck('id')->all();

        $this->assertNotContains($picked->id, $ids);
        $this->assertContains($notPicked->id, $ids);
    }

    public function test_event_task_candidates_are_anchored_to_the_events_own_date_not_today(): void
    {
        $user = $this->actingUser();
        $nearEvent = Task::factory()->for($user)->todos()->create(['title' => 'Near the event', 'deadline' => '2026-07-02']);
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

    public function test_navigate_to_linked_task_redirects_to_the_next_open_ones_edit_sheet(): void
    {
        $user = $this->actingUser();
        $done = Task::factory()->for($user)->todos()->completed()->create();
        $open = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        $event->linkedTasks()->attach($done->id, ['sort_order' => 0]);
        $event->linkedTasks()->attach($open->id, ['sort_order' => 1]);

        Livewire::test(Schedule::class)
            ->call('navigateToLinkedTask', $event->id)
            ->assertRedirect(route('app', ['task' => $open->id]));
    }

    public function test_navigate_to_linked_task_is_a_no_op_without_any_link(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->create();

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

    public function test_the_pick_order_becomes_the_pivot_sort_order(): void
    {
        $user = $this->actingUser();
        $first = Task::factory()->for($user)->todos()->create();
        $second = Task::factory()->for($user)->todos()->create();
        $third = Task::factory()->for($user)->todos()->create();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Bio lernen')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->set('eventColor', 'contour')
            ->call('toggleEventLinkedTask', $second->id)
            ->call('toggleEventLinkedTask', $third->id)
            ->call('toggleEventLinkedTask', $first->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('title', 'Bio lernen')->firstOrFail();
        $this->assertSame([$second->id, $third->id, $first->id], $event->linkedTasks()->pluck('tasks.id')->all());
        $this->assertSame($second->id, $event->nextLinkedTask()->id);
    }
}
