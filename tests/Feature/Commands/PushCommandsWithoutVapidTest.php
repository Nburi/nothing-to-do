<?php

namespace Tests\Feature\Commands;

use App\Models\PushSubscription;
use App\Models\Task;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A server without VAPID keys (a fresh dev server) runs the scheduler every minute. The push
 * commands must skip their tick with one line instead of throwing, and must not stamp anything
 * as sent, so the notifications still go out once the keys are set. See ERRORS.md, 2026-11-16.
 */
class PushCommandsWithoutVapidTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function removeVapidKeys(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        // Built with the keys of the test environment otherwise, singletons survive config changes.
        $this->app->forgetInstance(WebPush::class);
        $this->app->forgetInstance(PushNotifier::class);
    }

    /** @return array<string, array{string}> */
    public static function pushCommands(): array
    {
        return [
            'progress reminders' => ['app:send-progress-reminders'],
            'reminders' => ['app:send-reminder-notifications'],
            'event start' => ['app:send-event-start-notifications'],
            'event upcoming' => ['app:send-event-upcoming-notifications'],
            'day preview' => ['app:send-day-preview-notifications'],
        ];
    }

    #[DataProvider('pushCommands')]
    public function test_a_push_command_skips_with_one_line_on_a_server_without_keys(string $command): void
    {
        $this->removeVapidKeys();

        $this->artisan($command)
            ->expectsOutputToContain('no VAPID keys')
            ->assertSuccessful();
    }

    #[DataProvider('pushCommands')]
    public function test_a_push_command_still_runs_when_the_keys_are_set(string $command): void
    {
        $this->artisan($command)->doesntExpectOutputToContain('no VAPID keys')->assertSuccessful();
    }

    public function test_a_skipped_tick_stamps_nothing_so_the_reminder_still_goes_out_later(): void
    {
        Carbon::setTestNow('2026-08-16 19:05:00');

        $user = User::factory()->create([
            'timezone_offset' => 0,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '19:00',
        ]);
        Task::factory()->for($user)->todos()->create(['is_today' => true]);

        $this->removeVapidKeys();
        $this->artisan('app:send-progress-reminders')->assertSuccessful();
        $this->assertNull($user->fresh()->daily_reminder_sent_on);

        config(['webpush.vapid.public_key' => 'set', 'webpush.vapid.private_key' => 'set']);
        $this->mock(PushNotifier::class, fn ($mock) => $mock->shouldReceive('notify')->once());
        $this->artisan('app:send-progress-reminders')->assertSuccessful();
        $this->assertSame('2026-08-16', $user->fresh()->daily_reminder_sent_on->toDateString());
    }

    public function test_the_notifier_can_be_built_without_keys_and_sends_nothing(): void
    {
        $this->removeVapidKeys();
        $user = User::factory()->create();
        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/abc',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/abc'),
            'p256dh' => 'x',
            'auth_token' => 'y',
        ]);

        $notifier = app(PushNotifier::class);

        $this->assertFalse(PushNotifier::isConfigured());
        $notifier->notify($user, ['title' => 'a', 'body' => 'b']);

        // The subscription is kept: a missing key is the server's problem, not the device's.
        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    public function test_the_settings_test_push_explains_that_push_is_off(): void
    {
        $this->removeVapidKeys();
        $user = User::factory()->create();

        $results = app(PushNotifier::class)->sendDebug($user, ['title' => 'a', 'body' => 'b']);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('VAPID', $results[0]['reason']);
    }
}
