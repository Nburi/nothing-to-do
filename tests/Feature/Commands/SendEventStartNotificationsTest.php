<?php

namespace Tests\Feature\Commands;

use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendEventStartNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_past_due_event_is_notified_exactly_once_and_stamps_notified_at(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('08:00', '08:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_at);
    }

    public function test_running_the_command_twice_does_not_send_a_duplicate_notification(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('08:00', '08:30')->create();

        // notify() must fire exactly once total, across BOTH command runs below.
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();
        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_at);
    }

    public function test_a_user_with_notifications_disabled_is_never_notified(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $user = User::factory()->create([
            'notify_event_start' => false,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('08:00', '08:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_at);
    }

    public function test_a_future_event_is_not_notified_yet(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('10:00', '10:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_at);
    }

    public function test_timezone_offset_is_applied_when_deciding_whether_an_event_has_started(): void
    {
        // Frozen UTC instant: 07:30. User is UTC+2, so their local "now" is
        // 09:30 on the same calendar day.
        Carbon::setTestNow('2026-07-10 07:30:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 2.0,
            'timezone_auto_dst' => false,
        ]);

        // Local 09:00 -> UTC 07:00, which is at-or-before the frozen "now" (07:30).
        $pastEvent = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        // Local 10:00 -> UTC 08:00, which is after the frozen "now" (07:30).
        $futureEvent = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('10:00', '10:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNotNull($pastEvent->fresh()->notified_at);
        $this->assertNull($futureEvent->fresh()->notified_at);
    }

    public function test_a_missed_tick_still_notifies_once_the_command_finally_runs(): void
    {
        // The event started 15 minutes before the frozen "now" — simulating a
        // delayed/missed per-minute cron tick. Dedup is "already notified",
        // not a sliding time window, so this must still fire.
        Carbon::setTestNow('2026-07-10 09:15:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('09:00', '09:30')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNotNull($event->fresh()->notified_at);
    }

    /**
     * A cron outage spanning the user's local midnight must not permanently strand a still-due event on a
     * day that's already rolled over — the command must revisit unnotified events from earlier days too,
     * not just forDay(today).
     */
    public function test_an_unnotified_event_from_a_previous_day_is_still_picked_up_after_a_midnight_rollover(): void
    {
        Carbon::setTestNow('2026-07-10 00:10:00'); // just past local midnight

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        // Started 23:58 the previous day and was never notified (the cron was down across midnight).
        $staleEvent = ScheduleEvent::factory()->for($user)->on('2026-07-09')->at('23:58', '23:59')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNotNull($staleEvent->fresh()->notified_at);
    }

    public function test_a_cancelled_event_is_never_notified(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $user = User::factory()->create([
            'notify_event_start' => true,
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
        $event = ScheduleEvent::factory()->for($user)->on('2026-07-10')->at('08:00', '08:30')
            ->cancelled()->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-event-start-notifications')->assertSuccessful();

        $this->assertNull($event->fresh()->notified_at);
    }
}
