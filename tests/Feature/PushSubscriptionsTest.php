<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PushSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_creates_a_push_subscription_row_scoped_to_the_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('subscribeToPush', 'https://push.example.com/abc', 'p256dh-key', 'auth-token');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => 'p256dh-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_subscribing_twice_with_the_same_endpoint_results_in_one_row(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('subscribeToPush', 'https://push.example.com/abc', 'p256dh-key', 'auth-token')
            ->call('subscribeToPush', 'https://push.example.com/abc', 'p256dh-key-updated', 'auth-token-updated');

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_subscribing_with_a_non_url_endpoint_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('subscribeToPush', 'not-a-url', 'key', 'token')
            ->assertHasErrors(['endpoint' => 'url']);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_unsubscribing_removes_the_users_own_subscription(): void
    {
        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/abc', 'p256dh-key', 'auth-token', 'Mozilla/5.0');

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('unsubscribeFromPush', 'https://push.example.com/abc');

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);
    }

    public function test_unsubscribing_cannot_remove_another_users_subscription(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        PushSubscription::storeFor($owner, 'https://push.example.com/owner', 'p256dh-key', 'auth-token', 'Mozilla/5.0');

        Livewire::actingAs($other)
            ->test(Settings::class)
            ->call('unsubscribeFromPush', 'https://push.example.com/owner');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $owner->id,
            'endpoint' => 'https://push.example.com/owner',
        ]);
    }

    public function test_unsubscribing_from_an_endpoint_that_was_never_subscribed_is_a_silent_no_op(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('unsubscribeFromPush', 'https://push.example.com/never-subscribed');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    /**
     * Defence-in-depth against SSRF: the server later makes a real outbound HTTP request to whatever
     * endpoint is stored, so a tampered/manual Livewire call supplying a loopback or private-network host
     * must be rejected rather than silently stored and later POSTed to by the server's own VAPID-signed
     * request.
     */
    public function test_subscribing_with_a_loopback_or_private_ip_endpoint_is_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['https://127.0.0.1/abc', 'https://localhost/abc', 'https://192.168.1.1/abc', 'https://10.0.0.5/abc'] as $endpoint) {
            Livewire::actingAs($user)
                ->test(Settings::class)
                ->call('subscribeToPush', $endpoint, 'key', 'token')
                ->assertHasErrors(['endpoint']);
        }

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscribing_with_a_non_https_endpoint_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('subscribeToPush', 'http://push.example.com/abc', 'key', 'token')
            ->assertHasErrors(['endpoint']);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_send_test_push_populates_the_results_from_push_notifier(): void
    {
        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example.com/abc', 'key', 'auth', 'Mozilla/5.0');

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('sendDebug')->once()->andReturn([
                ['endpoint' => 'https://push.example.com/abc', 'user_agent' => 'Mozilla/5.0', 'success' => true, 'status' => 201, 'reason' => 'OK'],
            ]);
        });

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('sendTestPush')
            ->assertSet('testPushSent', true)
            ->assertSet('testPushResults', [
                ['endpoint' => 'https://push.example.com/abc', 'user_agent' => 'Mozilla/5.0', 'success' => true, 'status' => 201, 'reason' => 'OK'],
            ]);
    }

    public function test_send_test_push_with_no_subscriptions_returns_an_empty_result(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('sendTestPush')
            ->assertSet('testPushSent', true)
            ->assertSet('testPushResults', []);
    }
}
