<?php

namespace Tests\Feature\Commands;

use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendEventUpcomingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_event_starting_in_5_minutes_is_notified_exactly_once_and_stamps_notified_upcoming_at(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_upcoming_at);
    }

    public function test_running_the_command_twice_does_not_send_a_duplicate_notification(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();
        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_upcoming_at);
    }

    public function test_a_user_with_the_upcoming_notification_disabled_is_never_notified(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => false,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_upcoming_at);
    }

    public function test_an_event_more_than_5_minutes_away_is_not_notified_yet(): void
    {
        Carbon::setTestNow('2026-07-10 08:50:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_upcoming_at);
    }

    public function test_the_upcoming_notification_still_fires_after_the_event_has_already_started(): void
    {
        // The lead-time instant (08:55) has long passed by the time this delayed
        // tick finally runs — dedup is "already notified", not a sliding window,
        // so a late tick still sends the heads-up rather than losing it.
        Carbon::setTestNow('2026-07-10 09:10:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_upcoming_at);
    }

    public function test_timezone_offset_is_applied_when_deciding_whether_the_lead_time_has_arrived(): void
    {
        // Frozen UTC instant: 06:56. User is UTC+2, so their local "now" is 08:56.
        Carbon::setTestNow('2026-07-10 06:56:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 2.0,
            'timezone_auto_dst' => false,
        ]);

        // Local 09:00 -> UTC 07:00; lead instant 06:55 is at-or-before the frozen "now" (06:56).
        $dueEvent = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        // Local 10:00 -> UTC 08:00; lead instant 07:55 is after the frozen "now" (06:56).
        $futureEvent = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('10:00', '10:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($dueEvent->fresh()->notified_upcoming_at);
        $this->assertNull($futureEvent->fresh()->notified_upcoming_at);
    }

    public function test_a_cancelled_event_is_never_notified(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')
            ->cancelled()->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_upcoming_at);
    }

    public function test_the_at_start_notification_is_independent_of_the_upcoming_notification(): void
    {
        Carbon::setTestNow('2026-07-10 08:55:00');

        $user = User::factory()->create([
            'notify_event_upcoming' => true,
            'notify_event_start' => false,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-upcoming-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_upcoming_at);
        $this->assertNull($event->fresh()->notified_at);
    }
}
