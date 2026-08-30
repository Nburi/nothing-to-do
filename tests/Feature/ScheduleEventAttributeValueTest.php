<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleEventAttributeValueTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function trainingCategory(User $user): EventCategory
    {
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $category->customAttributes()->create(['name' => 'Trainingstyp', 'type' => 'select', 'sort_order' => 0, 'options' => [
            ['label' => 'Lauf', 'color' => 'forest'],
            ['label' => 'Kraft', 'color' => 'signal'],
        ]]);
        $category->customAttributes()->create(['name' => 'Dauer', 'type' => 'number', 'unit' => 'Min', 'sort_order' => 1]);
        $category->customAttributes()->create(['name' => 'Draussen', 'type' => 'checkbox', 'sort_order' => 2]);

        return $category;
    }

    public function test_event_category_attributes_is_empty_for_an_appointment(): void
    {
        $this->actingUser();

        $component = Livewire::test(Schedule::class)->set('eventKind', 'appointment');

        $this->assertCount(0, $component->get('eventCategoryAttributes'));
    }

    public function test_event_category_attributes_reflects_the_chosen_category(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);

        $component = Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id);

        $this->assertCount(3, $component->get('eventCategoryAttributes'));
    }

    public function test_saving_a_category_event_persists_filled_attribute_values(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type, $duration, $outdoor] = $category->customAttributes->all();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->call('pickEventAttributeOption', $type->id, 0) // "Lauf"
            ->set("eventAttributeValues.{$duration->id}", '60')
            ->set("eventAttributeValues.{$outdoor->id}", true)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('category_id', $category->id)->firstOrFail();
        $values = $event->attributeValues->mapWithKeys(fn ($a) => [$a->id => $a->pivot->value]);

        $this->assertSame('Lauf', $values[$type->id]);
        $this->assertSame('60', $values[$duration->id]);
        $this->assertSame('1', $values[$outdoor->id]);
    }

    public function test_an_unfilled_attribute_leaves_no_row(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type] = $category->customAttributes->all();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->call('pickEventAttributeOption', $type->id, 0)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('category_id', $category->id)->firstOrFail();

        $this->assertSame(1, $event->attributeValues()->count());
    }

    public function test_a_non_numeric_number_value_is_ignored(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [, $duration] = $category->customAttributes->all();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->set("eventAttributeValues.{$duration->id}", 'nicht-numerisch')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('category_id', $category->id)->firstOrFail();

        $this->assertSame(0, $event->attributeValues()->count());
    }

    public function test_pick_event_attribute_option_toggles_off_on_a_second_tap(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type] = $category->customAttributes->all();

        $component = Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->call('pickEventAttributeOption', $type->id, 0);

        $this->assertSame('Lauf', $component->get('eventAttributeValues')[$type->id]);

        $component->call('pickEventAttributeOption', $type->id, 0);

        $this->assertNull($component->get('eventAttributeValues')[$type->id]);
    }

    public function test_editing_an_event_seeds_and_resaving_updates_values(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type, $duration] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id, 'title' => 'Training']);
        $event->attributeValues()->attach($type->id, ['value' => 'Lauf']);
        $event->attributeValues()->attach($duration->id, ['value' => '45']);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->assertSet("eventAttributeValues.{$type->id}", 'Lauf')
            ->assertSet("eventAttributeValues.{$duration->id}", '45')
            ->set("eventAttributeValues.{$duration->id}", '60')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $values = $event->fresh()->attributeValues->mapWithKeys(fn ($a) => [$a->id => $a->pivot->value]);
        $this->assertSame('60', $values[$duration->id]);
    }

    public function test_clearing_a_value_on_edit_removes_its_row(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [, $duration] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($duration->id, ['value' => '45']);

        Livewire::test(Schedule::class)
            ->call('startEditEvent', $event->id)
            ->set("eventAttributeValues.{$duration->id}", '')
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $this->assertSame(0, $event->fresh()->attributeValues()->count());
    }

    public function test_attribute_display_rows_include_a_dot_colour_only_for_select_values(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type, $duration, $outdoor] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($type->id, ['value' => 'Kraft']);
        $event->attributeValues()->attach($duration->id, ['value' => '45']);
        $event->attributeValues()->attach($outdoor->id, ['value' => '1']);

        $rows = $event->fresh()->attributeDisplayRows();

        $this->assertSame('Kraft', $rows[0]['display']);
        $this->assertSame('signal', $rows[0]['dot']);
        $this->assertSame('45 Min', $rows[1]['display']);
        $this->assertNull($rows[1]['dot']);
        $this->assertSame('Draussen', $rows[2]['display']);
        $this->assertNull($rows[2]['dot']);
    }

    public function test_renaming_an_attribute_updates_the_display_immediately(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [, $duration] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($duration->id, ['value' => '45']);

        $duration->update(['unit' => 'Std']);

        $rows = $event->fresh()->attributeDisplayRows();

        $this->assertSame('45 Std', $rows[0]['display']);
    }

    public function test_a_recurring_category_block_can_be_created_without_touching_attribute_values(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type] = $category->customAttributes->all();

        Livewire::test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id)
            ->set('eventDate', '2026-06-26')
            ->set('eventStart', '16:00')
            ->set('eventEnd', '17:00')
            ->set('eventRecurring', true)
            ->set('eventDays', [5])
            ->call('pickEventAttributeOption', $type->id, 0)
            ->call('saveEventForm')
            ->assertHasNoErrors();

        $event = ScheduleEvent::forUser($user)->where('category_id', $category->id)->firstOrFail();
        $this->assertSame(0, $event->attributeValues()->count());
    }

    public function test_a_long_enough_block_renders_the_full_line_in_both_the_day_and_week_view(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('08:00', '09:00')->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($type->id, ['value' => 'Lauf']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        // The full "label + value" line renders for both the day view and the compact desktop
        // week view instance of this same event — >=30min is room enough in either.
        $response->assertSeeInOrder(['mt-0.5 flex flex-wrap', 'mt-0.5 flex flex-wrap'], false);
        // The compact dots-only preview never renders once the full line already fits.
        $response->assertDontSee('title="Lauf"', false);
    }

    public function test_a_short_block_only_renders_the_compact_dots(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [$type] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('08:00', '08:15')->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($type->id, ['value' => 'Lauf']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        $response->assertDontSee('mt-0.5 flex flex-wrap', false);
        $response->assertSee('title="Lauf"', false);
        $response->assertSee('aria-label="Lauf"', false);
    }

    public function test_an_event_with_no_select_value_shows_no_dot_preview(): void
    {
        $user = $this->actingUser();
        $category = $this->trainingCategory($user);
        [, $duration] = $category->customAttributes->all();
        $event = ScheduleEvent::factory()->for($user)->on(now()->toDateString())->at('08:00', '08:15')->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($duration->id, ['value' => '45']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        $response->assertDontSee('mt-0.5 flex flex-wrap', false);
        $response->assertDontSee('aria-label="45 Min"', false);
    }
}
