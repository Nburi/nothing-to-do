<?php

namespace Tests\Feature;

use App\Livewire\WeekPlan;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\SchedulePause;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WeekPlanTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_the_week_plan_page_renders(): void
    {
        $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->assertOk()
            ->assertSee('Wochenplan');
    }

    public function test_it_creates_a_recurring_appointment_block(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Schule')
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:30')
            ->set('eventColor', 'contour')
            ->set('eventDays', [1, 2, 3, 4, 5])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'category_id' => null,
            'name' => 'Schule',
            'is_recurring' => true,
            'recurrence' => '1,2,3,4,5',
            'duration' => 90,
            'default_start' => '08:00',
        ]);
    }

    public function test_it_creates_a_recurring_category_block(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);

        Livewire::test(WeekPlan::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventStart', '17:00')
            ->set('eventEnd', '18:30')
            ->set('eventDays', [2, 4])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Training',
            'color' => 'forest',
            'recurrence' => '2,4',
        ]);
    }

    public function test_creating_a_block_requires_at_least_one_weekday(): void
    {
        $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->set('eventKind', 'appointment')
            ->set('eventTitle', 'Schule')
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:00')
            ->set('eventDays', [])
            ->call('saveEventForm')
            ->assertHasErrors(['eventDays']);
    }

    public function test_a_user_cannot_use_another_users_category(): void
    {
        $this->actingUser();
        $other = EventCategory::factory()->for(User::factory())->create();

        Livewire::test(WeekPlan::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $other->id)
            ->set('eventStart', '08:00')
            ->set('eventEnd', '09:00')
            ->set('eventDays', [1])
            ->call('saveEventForm')
            ->assertHasErrors(['eventCategoryId']);
    }

    public function test_the_missing_category_error_is_shown_in_german_not_englisch(): void
    {
        // Regression: this project had no lang/ directory at all, so this
        // exact validation failure (no category picked on the Kategorie
        // tab) rendered Laravel's raw English default — "The event
        // category id field is required." — inside an otherwise fully
        // German UI. See lang/de/validation.php.
        $user = $this->actingUser();
        EventCategory::factory()->for($user)->create(['name' => 'Training']);

        $component = Livewire::test(WeekPlan::class)
            ->set('eventKind', 'category')
            ->set('eventStart', '17:00')
            ->set('eventEnd', '19:00')
            ->set('eventDays', [3])
            ->call('saveEventForm');

        $component->assertHasErrors(['eventCategoryId']);

        $message = $component->errors()->first('eventCategoryId');

        $this->assertStringNotContainsString('field is required', $message);
        $this->assertSame('Das Feld Kategorie muss ausgefüllt werden.', $message);
    }

    public function test_the_chosen_category_chip_gets_a_visible_ring_not_just_its_own_colour(): void
    {
        // UX finding: a category chip's dot always shows that category's own
        // assigned colour, selected or not — with exactly one category (the
        // common case right after creating the first one), that read as
        // "already selected" and was the direct cause of the previous test's
        // regression. A ring around the dot, matching the plain colour
        // picker's own already-established selected-state language a few
        // lines below in the same form, makes "selected" unambiguous
        // regardless of the category's own colour.
        $user = $this->actingUser();
        $chosen = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $other = EventCategory::factory()->for($user)->create(['name' => 'Schule']);

        $html = Livewire::test(WeekPlan::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $chosen->id)
            ->html();

        // Anchor on the chip's own wire:click call (unique per category and
        // only present in the picker, not the page's separate quick-create
        // footer, which also shows the category name) rather than the bare
        // name, so the window can't land on the wrong "Training" chip.
        $chosenAnchor = strpos($html, "\$set('eventCategoryId', {$chosen->id})");
        $otherAnchor = strpos($html, "\$set('eventCategoryId', {$other->id})");
        $this->assertNotFalse($chosenAnchor);
        $this->assertNotFalse($otherAnchor);

        $chosenChip = substr($html, $chosenAnchor, 400);
        $otherChip = substr($html, $otherAnchor, 400);

        $this->assertStringContainsString('ring-2 ring-offset-1', $chosenChip);
        $this->assertStringNotContainsString('ring-2 ring-offset-1', $otherChip);
    }

    public function test_editing_a_template_updates_its_shape(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id, 'name' => 'Schule', 'default_start' => '08:00', 'duration' => 90,
        ]);

        Livewire::test(WeekPlan::class)
            ->call('startEditEvent', $template->id)
            ->set('eventTitle', 'Schule (neu)')
            ->set('eventStart', '09:00')
            ->set('eventDays', [1, 3])
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('Schule (neu)', $template->name);
        $this->assertSame('09:00', $template->default_start);
        $this->assertSame('1,3', $template->recurrence);
    }

    public function test_editing_a_template_propagates_to_an_already_materialised_future_occurrence(): void
    {
        $user = $this->actingUser();
        Carbon::setTestNow('2026-06-22 07:00:00'); // Monday
        $template = EventTemplate::factory()->recurring('1,3')->create([ // Mon, Wed
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 90,
        ]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-24'));

        Livewire::test(WeekPlan::class)
            ->call('startEditEvent', $template->id)
            ->set('eventStart', '15:00')
            ->set('eventEnd', '16:30')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $wednesday = ScheduleEvent::forUser($user)->whereDate('date', '2026-06-24')->firstOrFail();
        $this->assertSame('15:00', $wednesday->start_time);
        $this->assertSame('16:30', $wednesday->end_time);

        Carbon::setTestNow();
    }

    public function test_editing_a_template_never_touches_todays_occurrence(): void
    {
        $user = $this->actingUser();
        Carbon::setTestNow('2026-06-22 07:00:00'); // Monday
        $template = EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 90,
        ]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'));

        Livewire::test(WeekPlan::class)
            ->call('startEditEvent', $template->id)
            ->set('eventStart', '15:00')
            ->set('eventEnd', '16:30')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $today = ScheduleEvent::forUser($user)->whereDate('date', '2026-06-22')->firstOrFail();
        $this->assertSame('08:00', $today->start_time); // untouched — editing a template must never disturb today

        Carbon::setTestNow();
    }

    public function test_dropping_a_weekday_from_the_recurrence_removes_its_future_occurrence(): void
    {
        $user = $this->actingUser();
        Carbon::setTestNow('2026-06-22 07:00:00'); // Monday
        $template = EventTemplate::factory()->recurring('1,3')->create([ // Mon, Wed
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 90,
        ]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-24'));

        Livewire::test(WeekPlan::class)
            ->call('startEditEvent', $template->id)
            ->set('eventDays', [1]) // drop Wednesday
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('schedule_events', ['template_id' => $template->id, 'date' => '2026-06-24']);
        // A future re-materialisation must not resurrect it either (no leftover tombstone).
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-24'), Carbon::parse('2026-06-24'));
        $this->assertDatabaseMissing('schedule_events', ['template_id' => $template->id, 'date' => '2026-06-24']);

        Carbon::setTestNow();
    }

    public function test_move_shifts_default_start_and_keeps_duration(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 60,
        ]);

        Livewire::test(WeekPlan::class)->call('moveEvent', $template->id, '10:30');

        $template->refresh();
        $this->assertSame('10:30', $template->default_start);
        $this->assertSame(60, $template->duration); // duration preserved
    }

    public function test_resize_guards_a_minimum_length(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 60,
        ]);

        Livewire::test(WeekPlan::class)->call('resizeEvent', $template->id, '08:00', '08:05'); // < MIN_EVENT
        $this->assertSame(60, $template->refresh()->duration); // unchanged

        Livewire::test(WeekPlan::class)->call('resizeEvent', $template->id, '08:00', '10:00');
        $this->assertSame(120, $template->refresh()->duration);
    }

    public function test_quick_creating_a_category_block_on_one_weekday(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);

        Livewire::test(WeekPlan::class)->call('quickCreateCategoryBlock', $category->id, '2', '17:00', '18:30');

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'is_recurring' => true,
            'recurrence' => '2',
            'default_start' => '17:00',
            'duration' => 90,
        ]);
    }

    public function test_quick_creating_a_termin_on_one_weekday(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)->call('quickCreateTermin', 'Zahnarzt', 'signal', '6', '10:00', '10:30');

        $this->assertDatabaseHas('event_templates', [
            'user_id' => $user->id,
            'name' => 'Zahnarzt',
            'color' => 'signal',
            'recurrence' => '6',
            'default_start' => '10:00',
            'duration' => 30,
        ]);
    }

    public function test_quick_creating_rejects_an_invalid_weekday(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)->call('quickCreateTermin', 'Zahnarzt', 'signal', '9', '10:00', '10:30');

        $this->assertDatabaseMissing('event_templates', ['user_id' => $user->id]);
    }

    public function test_deleting_a_template_removes_it_and_cascades_its_occurrences(): void
    {
        $user = $this->actingUser();
        $template = EventTemplate::factory()->recurring('1')->create(['user_id' => $user->id]);
        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'));

        Livewire::test(WeekPlan::class)->call('deleteEvent', $template->id);

        $this->assertDatabaseMissing('event_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('schedule_events', ['template_id' => $template->id]);
    }

    public function test_a_user_cannot_touch_another_users_template(): void
    {
        $this->actingUser();
        $other = EventTemplate::factory()->recurring('1')->create(['user_id' => User::factory()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(WeekPlan::class)->call('moveEvent', $other->id, '10:00');
    }

    public function test_pausing_a_range_creates_one_pause_per_date(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->set('pauseFrom', '2026-07-20')
            ->set('pauseTo', '2026-07-22')
            ->set('pauseNote', 'Sportferien')
            ->call('savePauseRange')
            ->assertHasNoErrors();

        $this->assertSame(3, SchedulePause::forUser($user)->count());
    }

    public function test_pausing_a_range_rejects_an_end_before_the_start(): void
    {
        $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->set('pauseFrom', '2026-07-22')
            ->set('pauseTo', '2026-07-20')
            ->call('savePauseRange')
            ->assertHasErrors(['pauseTo']);
    }

    public function test_pausing_a_range_caps_at_roughly_one_year(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->set('pauseFrom', '2026-01-01')
            ->set('pauseTo', '2028-01-01')
            ->call('savePauseRange')
            ->assertHasErrors(['pauseTo']);

        $this->assertSame(0, SchedulePause::forUser($user)->count());
    }

    public function test_unpausing_a_date_removes_just_that_day(): void
    {
        $user = $this->actingUser();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-22'), null);

        Livewire::test(WeekPlan::class)->call('unpauseDate', '2026-07-21');

        $this->assertSame(2, SchedulePause::forUser($user)->count());
        $this->assertDatabaseMissing('schedule_pauses', ['user_id' => $user->id, 'date' => '2026-07-21']);
    }

    public function test_unpausing_a_date_rematerialises_it_immediately(): void
    {
        $user = $this->actingUser();
        EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id, 'default_start' => '08:00', 'duration' => 90,
        ]);
        SchedulePause::pauseRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'), null); // Monday

        Livewire::test(WeekPlan::class)->call('unpauseDate', '2026-06-22');

        $this->assertDatabaseHas('schedule_events', ['user_id' => $user->id, 'date' => '2026-06-22']);
    }

    public function test_unpausing_a_range_removes_every_date_in_it(): void
    {
        $user = $this->actingUser();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-22'), null);

        Livewire::test(WeekPlan::class)->call('unpauseRange', '2026-07-20', '2026-07-22');

        $this->assertSame(0, SchedulePause::forUser($user)->count());
    }

    public function test_unpausing_an_impossible_date_is_ignored_not_a_server_error(): void
    {
        $user = $this->actingUser();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-22'), null);

        // Well-formed to the eye, not a calendar date: used to reach Carbon::parse() and throw.
        foreach (['2026-13-45', '2026-02-30', '0000-00-00', 'garbage'] as $bad) {
            Livewire::test(WeekPlan::class)->call('unpauseDate', $bad)->assertHasNoErrors();
            Livewire::test(WeekPlan::class)->call('unpauseRange', $bad, '2026-07-22')->assertHasNoErrors();
        }

        $this->assertSame(3, SchedulePause::forUser($user)->count());
    }

    public function test_unpausing_a_range_longer_than_a_year_or_backwards_does_nothing(): void
    {
        $user = $this->actingUser();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-22'), null);

        // A pause can never be created longer than a year (savePauseRange), so a longer
        // unpause can only be hand-made — and would materialise every day of it.
        Livewire::test(WeekPlan::class)->call('unpauseRange', '1900-01-01', '2999-12-31');
        Livewire::test(WeekPlan::class)->call('unpauseRange', '2026-07-22', '2026-07-20');

        $this->assertSame(3, SchedulePause::forUser($user)->count());
    }
}
