<?php

namespace Tests\Unit;

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

    public function test_colour_token_falls_back_to_type_default(): void
    {
        $work = ScheduleEvent::factory()->workSession()->make(['color' => null]);
        $appt = ScheduleEvent::factory()->make(['color' => null, 'type' => ScheduleEvent::TYPE_APPOINTMENT]);

        $this->assertSame('forest', $work->colorToken());
        $this->assertSame('contour', $appt->colorToken());
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
