<?php

namespace Tests\Unit;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_minute_and_duration_helpers(): void
    {
        $event = ScheduleEvent::factory()->make(['start_time' => '08:00', 'end_time' => '09:30']);

        $this->assertSame(480, $event->startMinutes());
        $this->assertSame(570, $event->endMinutes());
        $this->assertSame(90, $event->durationMinutes());
        $this->assertSame('14:25', ScheduleEvent::fromMinutes(865));
    }

    public function test_display_title_and_color_prefer_the_live_category(): void
    {
        $category = EventCategory::factory()->make(['name' => 'Training', 'color' => 'forest']);
        $event = ScheduleEvent::factory()->make(['title' => 'stale snapshot', 'color' => 'contour', 'category_id' => 1]);
        $event->setRelation('category', $category);

        $this->assertTrue($event->isCategory());
        $this->assertSame('Training', $event->displayTitle());
        $this->assertSame('forest', $event->colorToken());
    }

    public function test_display_title_and_color_fall_back_to_the_snapshot_when_uncategorised(): void
    {
        $event = ScheduleEvent::factory()->make(['title' => 'Zahnarzt', 'color' => 'signal', 'category_id' => null]);
        $event->setRelation('category', null);

        $this->assertTrue($event->isAppointment());
        $this->assertSame('Zahnarzt', $event->displayTitle());
        $this->assertSame('signal', $event->colorToken());
    }

    public function test_color_token_falls_back_to_contour_when_nothing_is_set(): void
    {
        $event = ScheduleEvent::factory()->make(['color' => null, 'category_id' => null]);
        $event->setRelation('category', null);

        $this->assertSame('contour', $event->colorToken());
    }

    public function test_display_title_and_color_follow_a_renamed_recoloured_category_live(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['title' => 'Training', 'color' => 'forest']);

        $category->update(['name' => 'Lauftraining', 'color' => 'signal']);
        $event->refresh();

        $this->assertSame('Lauftraining', $event->displayTitle());
        $this->assertSame('signal', $event->colorToken());
    }

    public function test_display_title_and_color_fall_back_to_snapshot_once_the_category_is_deleted(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['title' => 'Training', 'color' => 'forest']);

        $category->delete();
        $event->refresh();

        $this->assertNull($event->category_id);
        $this->assertSame('Training', $event->displayTitle());
        $this->assertSame('forest', $event->colorToken());
    }

    public function test_with_notified_reset_clears_the_flag_when_start_time_changes(): void
    {
        $event = ScheduleEvent::factory()->make(['start_time' => '08:00', 'notified_at' => now()]);

        $updates = $event->withNotifiedReset(['start_time' => '09:00']);

        $this->assertNull($updates['notified_at']);
    }

    public function test_with_notified_reset_clears_the_flag_when_only_the_date_changes(): void
    {
        $event = ScheduleEvent::factory()->make([
            'date' => '2026-07-10', 'start_time' => '08:00', 'notified_at' => now(),
        ]);

        // A drag-to-another-day move that keeps the same clock time must still reset the dedupe
        // flag, or the event silently never notifies again on its new date.
        $updates = $event->withNotifiedReset(['date' => '2026-07-11']);

        $this->assertArrayHasKey('notified_at', $updates);
        $this->assertNull($updates['notified_at']);
    }

    public function test_with_notified_reset_leaves_the_flag_untouched_when_neither_date_nor_start_time_changes(): void
    {
        $event = ScheduleEvent::factory()->make([
            'date' => '2026-07-10', 'start_time' => '08:00', 'notified_at' => now(),
        ]);

        $updates = $event->withNotifiedReset(['date' => '2026-07-10', 'start_time' => '08:00', 'color' => 'forest']);

        $this->assertArrayNotHasKey('notified_at', $updates);
    }

    public function test_occurs_on_respects_iso_weekday_mask(): void
    {
        $template = EventTemplate::factory()->recurring('1,3,5')->make(); // Mon, Wed, Fri

        $this->assertTrue($template->occursOn(Carbon::parse('2026-06-22')));  // Monday
        $this->assertFalse($template->occursOn(Carbon::parse('2026-06-23'))); // Tuesday
        $this->assertTrue($template->occursOn(Carbon::parse('2026-06-24')));  // Wednesday
    }

    public function test_materialize_creates_one_occurrence_per_weekday_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        EventTemplate::factory()->recurring('1,2,3,4,5')->create([
            'user_id' => $user->id,
            'name' => 'Schule',
            'default_start' => '08:00',
            'duration' => 90,
        ]);

        $start = Carbon::parse('2026-06-22'); // Monday
        $end = Carbon::parse('2026-06-28');   // Sunday

        ScheduleEvent::materializeRange($user, $start, $end);
        ScheduleEvent::materializeRange($user, $start->copy(), $end->copy()); // run twice

        $events = ScheduleEvent::forUser($user)->visible()->ordered()->get();

        $this->assertCount(5, $events); // Mon–Fri only, no duplicates
        $this->assertSame('Schule', $events->first()->title);
        $this->assertSame('08:00', $events->first()->start_time);
        $this->assertSame('09:30', $events->first()->end_time);
    }

    public function test_materialize_carries_the_templates_category_through(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training', 'color' => 'forest']);
        EventTemplate::factory()->recurring('1')->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Training',
            'color' => 'forest',
            'default_start' => '17:00',
            'duration' => 60,
        ]);

        ScheduleEvent::materializeRange($user, Carbon::parse('2026-06-22'), Carbon::parse('2026-06-22'));

        $event = ScheduleEvent::forUser($user)->firstOrFail();
        $this->assertSame($category->id, $event->category_id);
        $this->assertSame('Training', $event->displayTitle());
        $this->assertSame('forest', $event->colorToken());
    }

    public function test_cancelled_recurring_occurrence_is_not_regenerated(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->recurring('1')->create(['user_id' => $user->id]); // Mondays

        $start = Carbon::parse('2026-06-22'); // Monday
        $end = Carbon::parse('2026-06-22');

        ScheduleEvent::materializeRange($user, $start, $end);
        ScheduleEvent::forUser($user)->first()->update(['is_cancelled' => true]);

        ScheduleEvent::materializeRange($user, $start->copy(), $end->copy());

        $this->assertSame(1, ScheduleEvent::forUser($user)->count()); // tombstone kept, not duplicated
        $this->assertSame(0, ScheduleEvent::forUser($user)->visible()->count());
    }
}
