<?php

namespace Tests\Unit\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotifier;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class PushNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_is_a_no_op_when_the_user_has_no_subscriptions(): void
    {
        $user = User::factory()->create();

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldNotReceive('queueNotification');
        $webPush->shouldNotReceive('flush');

        (new PushNotifier($webPush))->notify($user, ['title' => 'x', 'body' => 'y']);

        $this->assertTrue(true);
    }

    public function test_notify_prunes_a_subscription_the_push_service_reports_as_expired(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://push.example.com/expired';
        $subscription = PushSubscription::storeFor($user, $endpoint, 'key', 'auth', null);

        $expiredReport = new MessageSentReport(new Request('POST', $endpoint), new Response(410));

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($expiredReport) {
            yield $expiredReport;
        })());

        (new PushNotifier($webPush))->notify($user, ['title' => 'x', 'body' => 'y']);

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_notify_keeps_a_subscription_the_push_service_accepted(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://push.example.com/ok';
        $subscription = PushSubscription::storeFor($user, $endpoint, 'key', 'auth', null);

        $okReport = new MessageSentReport(new Request('POST', $endpoint), new Response(201));

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($okReport) {
            yield $okReport;
        })());

        (new PushNotifier($webPush))->notify($user, ['title' => 'x', 'body' => 'y']);

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }

    /**
     * A malformed/corrupt subscription's key data throws inside minishlink/web-push's own encryption
     * step (Encryption::deterministicEncrypt) *before* any HTTP request is made — outside the library's
     * own per-request failure handling, which only catches promise rejections from the network call
     * itself. Reproduced directly against the real library while manually verifying this feature (see
     * git history) — a fake-but-malformed p256dh key crashed the whole scheduled command mid-run. Uncaught,
     * this would abort the caller's loop over every other due user/event in the same tick. notify() must
     * swallow it (logging via report()) rather than let it propagate.
     */
    public function test_notify_does_not_propagate_an_exception_thrown_during_flush(): void
    {
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/corrupt', 'not-valid-key-data', 'auth', null);

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andThrow(new \InvalidArgumentException('Invalid data: only uncompressed keys are supported.'));

        // Must not throw — a single bad subscription can't be allowed to abort the caller.
        (new PushNotifier($webPush))->notify($user, ['title' => 'x', 'body' => 'y']);

        $this->assertTrue(true);
    }

    /**
     * The bug found while diagnosing "notifications don't arrive despite the scheduled commands
     * completing with no error": a delivery failure that isn't a 404/410 (e.g. a TLS/network
     * failure, or the push service rejecting the request) produced a MessageSentReport with
     * isSuccess()=false, but notify() only ever checked isSubscriptionExpired() — so a
     * persistently failing subscription was invisible everywhere, forever. Now logged.
     */
    public function test_notify_logs_a_delivery_failure_that_is_not_an_expiry(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'Push notification delivery failed',
            Mockery::on(fn ($context) => $context['endpoint'] === 'https://push.example.com/broken'),
        );

        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/broken', 'key', 'auth', null);

        $failedReport = new MessageSentReport(
            new Request('POST', 'https://push.example.com/broken'),
            new Response(400),
            success: false,
            reason: 'cURL error 60: SSL certificate problem',
        );

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($failedReport) {
            yield $failedReport;
        })());

        (new PushNotifier($webPush))->notify($user, ['title' => 'x', 'body' => 'y']);

        // Not an expiry (410/404) — must NOT be pruned, unlike test_notify_prunes_a_subscription_the_push_service_reports_as_expired.
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/broken']);
    }

    public function test_send_debug_reports_a_successful_delivery_per_subscription(): void
    {
        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/device-a', 'key', 'auth', 'Mozilla/5.0 (Device A)');

        $okReport = new MessageSentReport(new Request('POST', 'https://push.example.com/device-a'), new Response(201));

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($okReport) {
            yield $okReport;
        })());

        $results = (new PushNotifier($webPush))->sendDebug($user, ['title' => 'x', 'body' => 'y']);

        $this->assertCount(1, $results);
        $this->assertSame('https://push.example.com/device-a', $results[0]['endpoint']);
        $this->assertSame('Mozilla/5.0 (Device A)', $results[0]['user_agent']);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(201, $results[0]['status']);
    }

    public function test_send_debug_reports_a_failed_delivery_with_the_reason(): void
    {
        Log::shouldReceive('warning')->once();

        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/device-b', 'key', 'auth', null);

        $failedReport = new MessageSentReport(
            new Request('POST', 'https://push.example.com/device-b'),
            new Response(403),
            success: false,
            reason: 'Forbidden',
        );

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($failedReport) {
            yield $failedReport;
        })());

        $results = (new PushNotifier($webPush))->sendDebug($user, ['title' => 'x', 'body' => 'y']);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertSame(403, $results[0]['status']);
        $this->assertSame('Forbidden', $results[0]['reason']);
    }

    public function test_send_debug_returns_an_empty_array_when_the_user_has_no_subscriptions(): void
    {
        $user = User::factory()->create();

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldNotReceive('queueNotification');

        $results = (new PushNotifier($webPush))->sendDebug($user, ['title' => 'x', 'body' => 'y']);

        $this->assertSame([], $results);
    }

    public function test_send_debug_surfaces_a_crash_during_flush_as_a_failed_result(): void
    {
        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/corrupt', 'not-valid-key-data', 'auth', null);

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andThrow(new \InvalidArgumentException('Invalid data: only uncompressed keys are supported.'));

        $results = (new PushNotifier($webPush))->sendDebug($user, ['title' => 'x', 'body' => 'y']);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('uncompressed keys', $results[0]['reason']);
    }
}
