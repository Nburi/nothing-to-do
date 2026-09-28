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

    public function test_the_missing_category_error_is_shown_in_german_not_english(): void
    {
        // Same regression as WeekPlanTest's own version — the "+ Termin"
        // full form on the Zeitplan page shares the exact same category
        // picker markup and validation rule. See lang/de/validation.php.
        $user = $this->actingUser();
        EventCategory::factory()->for($user)->create(['name' => 'Training']);

        $component = Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '15:00')
            ->call('saveEventForm');

        $message = $component->errors()->first('eventCategoryId');

        $this->assertStringNotContainsString('field is required', $message);
        $this->assertSame('Das Feld Kategorie muss ausgefüllt werden.', $message);
    }

    public function test_the_chosen_category_chip_gets_a_visible_ring_not_just_its_own_colour(): void
    {
        // Same fix as WeekPlanTest's own version — see that test's docblock.
        $user = $this->actingUser();
        $chosen = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $other = EventCategory::factory()->for($user)->create(['name' => 'Schule']);

        $html = Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $chosen->id)
            ->html();

        $chosenAnchor = strpos($html, "\$set('eventCategoryId', {$chosen->id})");
        $otherAnchor = strpos($html, "\$set('eventCategoryId', {$other->id})");
        $this->assertNotFalse($chosenAnchor);
        $this->assertNotFalse($otherAnchor);

        $this->assertStringContainsString('ring-2 ring-offset-1', substr($html, $chosenAnchor, 400));
        $this->assertStringNotContainsString('ring-2 ring-offset-1', substr($html, $otherAnchor, 400));
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

    public function test_dragging_a_block_into_another_day_column_moves_its_date(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create(['date' => '2026-06-26']);

        Livewire::test(Schedule::class)->call('moveEvent', $event->id, '10:30', '2026-06-29');

        $event->refresh();
        $this->assertSame('2026-06-29', $event->date->toDateString());
        $this->assertSame('10:30', $event->start_time);
        $this->assertSame('11:30', $event->end_time);
    }

    public function test_moving_to_another_day_makes_the_event_notify_again(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create([
            'date' => '2026-06-26', 'notified_at' => now(), 'notified_upcoming_at' => now(),
        ]);

        Livewire::test(Schedule::class)->call('moveEvent', $event->id, '08:00', '2026-06-27');

        $event->refresh();
        $this->assertNull($event->notified_at);
        $this->assertNull($event->notified_upcoming_at);
    }

    public function test_a_malformed_or_absurd_target_date_is_ignored(): void
    {
        $user = $this->actingUser();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create(['date' => '2026-06-26']);

        foreach (['2026-13-40', 'tomorrow', '2026-6-27', '1999-01-01'] as $bad) {
            Livewire::test(Schedule::class)->call('moveEvent', $event->id, '10:00', $bad);
        }

        $event->refresh();
        $this->assertSame('2026-06-26', $event->date->toDateString());
        $this->assertSame('08:00', $event->start_time);
    }

    public function test_a_recurring_occurrence_leaves_its_series_and_the_old_day_stays_empty(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('5')->create([ // Fridays
            'user_id' => $user->id, 'name' => 'Training', 'duration' => 60, 'default_start' => '17:00',
        ]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-26'), Carbon::parse('2026-06-28'));
        $occurrence = ScheduleEvent::where('template_id', $template->id)->whereDate('date', '2026-06-26')->firstOrFail();

        Livewire::test(Schedule::class)->call('moveEvent', $occurrence->id, '18:00', '2026-06-27');

        $moved = $occurrence->fresh();
        $this->assertNull($moved->template_id);
        $this->assertSame('2026-06-27', $moved->date->toDateString());
        $this->assertSame('18:00', $moved->start_time);

        // Re-materialising must not bring the Friday block back.
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-26'), Carbon::parse('2026-06-28'));
        $friday = ScheduleEvent::where('template_id', $template->id)->whereDate('date', '2026-06-26')->get();
        $this->assertCount(1, $friday);
        $this->assertTrue($friday->first()->is_cancelled);
        $this->assertCount(1, ScheduleEvent::forUser($user)->visible()->forDay(Carbon::parse('2026-06-26'))->get()->concat(
            ScheduleEvent::forUser($user)->visible()->forDay(Carbon::parse('2026-06-27'))->get()
        ));
    }

    public function test_a_foreign_event_cannot_be_dragged_into_another_day(): void
    {
        $this->actingUser();
        $other = ScheduleEvent::factory()->create(['date' => '2026-06-26']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(Schedule::class)->call('moveEvent', $other->id, '10:00', '2026-06-27');
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
