<?php

namespace Tests\Feature\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendProgressRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Offene Aufgaben am Abend ────────────────────────────────────────

    public function test_a_user_past_their_reminder_time_with_open_today_tasks_is_notified_and_stamped(): void
    {
        Carbon::setTestNow('2026-08-16 19:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();

        $this->assertSame('2026-08-16', $user->fresh()->daily_reminder_sent_on->toDateString());
    }

    public function test_running_the_command_twice_does_not_duplicate_the_daily_reminder(): void
    {
        Carbon::setTestNow('2026-08-16 19:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_with_no_open_today_tasks_is_not_notified(): void
    {
        Carbon::setTestNow('2026-08-16 19:05:00');

        User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_not_yet_at_their_reminder_time_is_not_notified(): void
    {
        Carbon::setTestNow('2026-08-16 18:00:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_with_the_daily_reminder_off_is_never_notified(): void
    {
        Carbon::setTestNow('2026-08-16 19:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => false,
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    // ── Serie in Gefahr ──────────────────────────────────────────────────

    public function test_a_user_with_a_trailing_streak_and_an_unfinished_today_list_is_warned_after_the_fixed_time(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        $user = User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => true]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-14')->completed()->create(['completed_at' => '2026-08-14 10:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create(); // today's list is still open

        $notified = null;
        $this->mock(PushNotifier::class, function ($mock) use (&$notified) {
            $mock->shouldReceive('notify')->once()->andReturnUsing(function ($user, $payload) use (&$notified) {
                $notified = $payload;
            });
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();

        $this->assertSame('2026-08-16', $user->fresh()->streak_risk_sent_on->toDateString());
        $this->assertStringContainsString('2 Tagen', $notified['body']);
        $this->assertStringContainsString('1 offene', $notified['body']);
    }

    public function test_a_user_with_a_trailing_streak_and_no_today_list_at_all_gets_a_distinct_message(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        $user = User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => true]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']);
        // Nothing at all flagged "today" today — not even an unfinished list.

        $notified = null;
        $this->mock(PushNotifier::class, function ($mock) use (&$notified) {
            $mock->shouldReceive('notify')->once()->andReturnUsing(function ($user, $payload) use (&$notified) {
                $notified = $payload;
            });
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();

        $this->assertStringContainsString('noch keine Heute-Liste', $notified['body']);
    }

    public function test_a_user_whose_today_list_is_already_fully_cleared_is_not_warned(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        $user = User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => true]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->completed()->create(['completed_at' => '2026-08-16 08:00:00']); // today already perfect

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_with_no_streak_at_all_is_not_warned(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => true]);
        // No today-lists ever — nothing to protect.

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_is_not_warned_before_the_fixed_streak_risk_time(): void
    {
        Carbon::setTestNow('2026-08-16 20:30:00');

        $user = User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => true]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_a_user_with_streak_risk_off_is_never_warned(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        $user = User::factory()->create(['timezone_offset' => 0, 'notify_streak_risk' => false]);
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']);
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();
    }

    public function test_both_reminders_can_fire_independently_for_the_same_user_in_one_run(): void
    {
        Carbon::setTestNow('2026-08-16 21:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
            'notify_streak_risk' => true,
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]); // open today task (board reminder)
        Task::factory()->for($user)->todos()->todayOn('2026-08-15')->completed()->create(['completed_at' => '2026-08-15 10:00:00']); // trailing streak
        Task::factory()->for($user)->todos()->todayOn('2026-08-16')->create(); // today's list open (streak-risk reminder)

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->twice();
        });

        $this->artisan('app:send-progress-reminders')->assertSuccessful();

        $user->refresh();
        $this->assertSame('2026-08-16', $user->daily_reminder_sent_on->toDateString());
        $this->assertSame('2026-08-16', $user->streak_risk_sent_on->toDateString());
    }
}
