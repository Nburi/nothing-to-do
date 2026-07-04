<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_the_schedule_page_renders(): void
    {
        $this->actingUser();

        Livewire::test(Schedule::class)
            ->assertOk()
            ->assertSee('Zeitplan');
    }

    public function test_it_creates_a_one_off_appointment(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Zahnarzt')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->set('eventColor', 'overprint')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => null,
            'title' => 'Zahnarzt',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);
    }

    public function test_a_recurring_appointment_creates_a_template_and_materialises(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->set('weekStart', '2026-06-22') // Mon
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Schule')
            ->set('eventDate', '2026-06-22')
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:30')
            ->set('eventColor', 'contour')
            ->set('eventRecurring', true)
            ->set('eventDays', [1, 2, 3, 4, 5])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'category_id' => null,
            'name' => 'Schule',
            'is_recurring' => true,
            'recurrence' => '1,2,3,4,5',
        ]);

        // Mon–Fri of that week materialised (5 occurrences).
        $this->assertSame(5, ScheduleEvent::forUser($user)->whereNotNull('template_id')->count());
    }

    public function test_it_creates_a_category_block(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Arbeiten', 'color' => 'overprint']);

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '16:00')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Arbeiten',
            'color' => 'overprint',
            'start_time' => '14:00',
            'end_time' => '16:00',
        ]);
    }

    public function test_creating_a_category_block_requires_a_category_id(): void
    {
        $this->actingUser();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->call('saveEventForm')
            ->assertHasErrors(['eventCategoryId']);
    }

    public function test_a_user_cannot_use_another_users_category(): void
    {
        $this->actingUser();
        $other = EventCategory::factory()->for(User::factory())->create();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $other->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->call('saveEventForm')
            ->assertHasErrors(['eventCategoryId']);
    }

    public function test_editing_an_appointment_into_a_category(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);
        $event = ScheduleEvent::factory()->for($user)
            ->create(['title' => 'Zahnarzt', 'color' => 'signal', 'category_id' => null]);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame($category->id, $event->category_id);
        $this->assertSame('Training', $event->title);
        $this->assertSame('forest', $event->color);
    }

    public function test_a_recurring_category_creates_a_template_with_the_category_and_materialises(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);

        Livewire::test(Schedule::class)
            ->set('weekStart', '2026-06-22') // Mon
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-22')
            ->set('eventStart', '17:00')
            ->set('eventEnd', '18:00')
            ->set('eventRecurring', true)
            ->set('eventDays', [1, 3, 5])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Training',
            'recurrence' => '1,3,5',
        ]);

        // Materialised rows are inserted raw (insertOrIgnore), so the date is
        // stored as a plain "Y-m-d" — unlike Eloquent's create(), which expands
        // the `date` cast to a full datetime string on write.
        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'date' => '2026-06-22',
        ]);
    }

    public function test_applying_a_template_carries_its_category(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);
        $template = EventTemplate::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Training',
            'color' => 'forest',
            'duration' => 60,
            'default_start' => '17:00',
        ]);

        Livewire::test(Schedule::class)->call('applyTemplate', $template->id, '2026-06-27');

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Training',
            'date' => '2026-06-27 00:00:00',
            'start_time' => '17:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_quick_creating_a_termin_by_drawing(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('quickCreateTermin', 'Zahnarzt', 'overprint', '2026-06-26', '14:00', '14:30');

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'category_id' => null,
            'title' => 'Zahnarzt',
            'color' => 'overprint',
            'date' => '2026-06-26 00:00:00',
            'start_time' => '14:00',
            'end_time' => '14:30',
        ]);
    }

    public function test_quick_creating_a_termin_rejects_a_blank_title(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('quickCreateTermin', '   ', 'overprint', '2026-06-26', '14:00', '14:30');

        $this->assertDatabaseMissing('schedule_events', ['user_id' => $user->id]);
    }

    public function test_quick_creating_a_termin_rejects_an_invalid_color(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('quickCreateTermin', 'Zahnarzt', 'not-a-color', '2026-06-26', '14:00', '14:30');

        $this->assertDatabaseMissing('schedule_events', ['user_id' => $user->id]);
    }

    public function test_quick_creating_a_termin_guards_a_minimum_length(): void
    {
        $user = $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('quickCreateTermin', 'Zahnarzt', 'overprint', '2026-06-26', '14:00', '14:05');

        $this->assertDatabaseMissing('schedule_events', ['user_id' => $user->id]);
    }

    public function test_move_keeps_duration(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create();

        Livewire::test(Schedule::class)->call('moveEvent', $event->id, '10:30');

        $event->refresh();
        $this->assertSame('10:30', $event->start_time);
        $this->assertSame('11:30', $event->end_time); // 60' preserved
    }

    public function test_resize_guards_a_minimum_length(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create();

        Livewire::test(Schedule::class)->call('resizeEvent', $event->id, '08:00', '08:05'); // < MIN_EVENT
        $this->assertSame('09:00', $event->refresh()->end_time); // unchanged

        Livewire::test(Schedule::class)->call('resizeEvent', $event->id, '08:00', '10:00');
        $this->assertSame('10:00', $event->refresh()->end_time);
    }

    public function test_deleting_a_recurring_occurrence_cancels_instead_of_removing(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create(['user_id' => $user->id]);
        $event = ScheduleEvent::factory()->for($user)->create(['template_id' => $template->id]);

        Livewire::test(Schedule::class)->call('deleteEvent', $event->id);

        $this->assertTrue($event->refresh()->is_cancelled);
        $this->assertDatabaseHas('schedule_events', ['id' => $event->id]); // tombstone kept
    }

    public function test_a_one_off_appointment_is_hard_deleted(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->create(['template_id' => null]);

        Livewire::test(Schedule::class)->call('deleteEvent', $event->id);

        $this->assertDatabaseMissing('schedule_events', ['id' => $event->id]);
    }

    public function test_moving_a_recurring_occurrence_detaches_it_and_does_not_regenerate(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create([ // Mondays
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 90,
        ]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'));
        $occ = ScheduleEvent::forUser($user)->whereNotNull('template_id')->firstOrFail();

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $occ->id)
            ->set('eventDate', '2026-06-23') // move Mon → Tue
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:30')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $occ->refresh();
        $this->assertSame('2026-06-23', $occ->date->toDateString());
        $this->assertNull($occ->template_id); // detached into a one-off

        // The original Monday slot is tombstoned, so re-materialising won't recreate it.
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'));
        $this->assertSame(0, ScheduleEvent::forUser($user)->visible()->where('template_id', $template->id)->forDay('2026-06-22')->count());
    }

    public function test_applying_a_template_places_an_appointment(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->create(['user_id' => $user->id, 'name' => 'Lauftraining', 'duration' => 90, 'default_start' => '17:30']);

        Livewire::test(Schedule::class)->call('applyTemplate', $template->id, '2026-06-27');

        $this->assertDatabaseHas('schedule_events', [
            'user_id' => $user->id,
            'title' => 'Lauftraining',
            'date' => '2026-06-27 00:00:00',
            'start_time' => '17:30',
            'end_time' => '19:00',
        ]);
    }

    public function test_a_user_cannot_touch_another_users_event(): void
    {
        $this->actingUser();
        $other = ScheduleEvent::factory()->for(User::factory())->at('08:00', '09:00')->create();

        // The event is resolved through the owner relationship, so another user's
        // id never matches — the write is rejected before it can run.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('moveEvent', $other->id, '12:00');
    }
}
