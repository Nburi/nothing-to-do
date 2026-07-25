<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendPrepareRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_user_past_their_fixed_reminder_time_is_notified_and_stamped(): void
    {
        Carbon::setTestNow('2026-07-10 20:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:00',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();

        $this->assertSame('2026-07-10', $user->fresh()->prepare_reminder_sent_on->toDateString());
    }

    public function test_running_the_command_twice_does_not_send_a_duplicate(): void
    {
        Carbon::setTestNow('2026-07-10 20:05:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:00',
        ]);

        // notify() must fire exactly once total, across BOTH command runs below.
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_a_user_not_yet_at_their_fixed_time_is_not_notified(): void
    {
        Carbon::setTestNow('2026-07-10 19:00:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:00',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_a_user_who_already_prepared_today_is_not_notified(): void
    {
        Carbon::setTestNow('2026-07-10 20:05:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:00',
            'prepared_on' => '2026-07-10',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_a_user_with_reminders_off_is_never_notified(): void
    {
        Carbon::setTestNow('2026-07-10 20:05:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'off',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_automatic_mode_falls_back_to_the_evening_default_time(): void
    {
        Carbon::setTestNow('2026-07-10 21:01:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'evening',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_automatic_mode_falls_back_to_the_morning_default_time(): void
    {
        Carbon::setTestNow('2026-07-10 09:55:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'morning',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();
    }

    public function test_a_missed_tick_still_notifies_once_the_command_finally_runs(): void
    {
        // Fixed time was 20:00, "now" is well past it — simulating a delayed
        // cron tick. Dedup is "already sent today", not an exact-minute
        // match, so this must still fire.
        Carbon::setTestNow('2026-07-10 20:47:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:00',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-prepare-reminders')->assertSuccessful();

        $this->assertNotNull($user->fresh()->prepare_reminder_sent_on);
    }
}
