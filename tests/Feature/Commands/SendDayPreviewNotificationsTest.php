<?php

namespace Tests\Feature\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendDayPreviewNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['day_preview.notification.titles' => ['Dein Tag ist bereit', 'Guten Morgen — dein Überblick wartet', 'Zeit für den Tagesüberblick']]);
        parent::tearDown();
    }

    public function test_a_user_past_their_own_notification_time_is_notified_with_a_live_summary_and_stamped(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        config(['day_preview.notification.titles' => ['Dein Tag ist bereit']]);
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_day_preview' => true,
            'day_preview_notification_time' => '07:30',
        ]);
        Task::factory()->for($user)->today()->count(2)->create();

        $this->mock(PushNotifier::class, function ($mock) use ($user) {
            $mock->shouldReceive('notify')->once()->with(
                \Mockery::on(fn ($u) => $u->is($user)),
                \Mockery::on(fn ($payload) => $payload === [
                    'title' => 'Dein Tag ist bereit',
                    'body' => '2 Aufgaben für heute.',
                    'url' => '/app/today',
                ])
            );
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();

        $this->assertSame('2026-09-18', $user->fresh()->day_preview_notification_sent_on->toDateString());
    }

    public function test_a_user_not_yet_at_their_own_notification_time_is_not_notified(): void
    {
        Carbon::setTestNow('2026-09-18 07:00:00');
        User::factory()->create([
            'timezone_offset' => 0,
            'notify_day_preview' => true,
            'day_preview_notification_time' => '07:30',
        ]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_each_user_has_their_own_independent_notification_time(): void
    {
        Carbon::setTestNow('2026-09-18 06:15:00');
        $early = User::factory()->create([
            'timezone_offset' => 0,
            'notify_day_preview' => true,
            'day_preview_notification_time' => '06:00',
        ]);
        $late = User::factory()->create([
            'timezone_offset' => 0,
            'notify_day_preview' => true,
            'day_preview_notification_time' => '07:30',
        ]);

        $this->mock(PushNotifier::class, function ($mock) use ($early) {
            $mock->shouldReceive('notify')->once()->with(\Mockery::on(fn ($u) => $u->is($early)), \Mockery::any());
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();

        $this->assertNotNull($early->fresh()->day_preview_notification_sent_on);
        $this->assertNull($late->fresh()->day_preview_notification_sent_on);
    }

    public function test_the_default_notification_time_is_0730_when_never_set(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_a_user_who_opted_out_is_never_notified(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => false]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->never();
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_running_the_command_twice_does_not_send_a_duplicate(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_the_title_is_picked_from_the_configured_pool(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        config(['day_preview.notification.titles' => ['Titel A', 'Titel B']]);
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->with(
                \Mockery::any(),
                \Mockery::on(fn ($payload) => in_array($payload['title'], ['Titel A', 'Titel B'], true))
            );
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_an_emptied_out_title_pool_falls_back_to_a_plain_default(): void
    {
        Carbon::setTestNow('2026-09-18 07:31:00');
        config(['day_preview.notification.titles' => []]);
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->with(
                \Mockery::any(),
                \Mockery::on(fn ($payload) => $payload['title'] === 'Dein Tag ist bereit')
            );
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }

    public function test_a_missed_tick_still_notifies_once_the_command_finally_runs(): void
    {
        Carbon::setTestNow('2026-09-18 11:00:00');
        User::factory()->create(['timezone_offset' => 0, 'notify_day_preview' => true]);

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('app:send-day-preview-notifications')->assertSuccessful();
    }
}
