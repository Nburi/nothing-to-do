<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_endpoint_returns_a_deterministic_64_char_lowercase_hex_digest(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $hash = PushSubscription::hashEndpoint($endpoint);

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertSame($hash, PushSubscription::hashEndpoint($endpoint));
        $this->assertSame(hash('sha256', $endpoint), $hash);
    }

    public function test_hash_endpoint_differs_for_different_endpoints(): void
    {
        $this->assertNotSame(
            PushSubscription::hashEndpoint('https://example.com/endpoint-a'),
            PushSubscription::hashEndpoint('https://example.com/endpoint-b'),
        );
    }

    public function test_store_for_creates_a_new_row_for_a_brand_new_endpoint(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/new-endpoint';

        $subscription = PushSubscription::storeFor($user, $endpoint, 'p256dh-key', 'auth-token', 'Mozilla/5.0');

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'p256dh-key',
            'auth_token' => 'auth-token',
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    public function test_store_for_called_twice_with_the_same_endpoint_updates_the_existing_row(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/same-endpoint';

        $first = PushSubscription::storeFor($user, $endpoint, 'old-p256dh', 'old-auth', 'old-agent');
        $second = PushSubscription::storeFor($user, $endpoint, 'new-p256dh', 'new-auth', 'new-agent');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $first->id,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'new-p256dh',
            'auth_token' => 'new-auth',
            'user_agent' => 'new-agent',
        ]);
    }

    public function test_store_for_with_two_different_endpoints_creates_two_distinct_rows(): void
    {
        $user = User::factory()->create();

        PushSubscription::storeFor($user, 'https://fcm.googleapis.com/fcm/send/endpoint-a', 'p256dh-a', 'auth-a', null);
        PushSubscription::storeFor($user, 'https://fcm.googleapis.com/fcm/send/endpoint-b', 'p256dh-b', 'auth-b', null);

        $this->assertDatabaseCount('push_subscriptions', 2);
        $this->assertSame(2, $user->pushSubscriptions()->count());
    }

    public function test_user_relation_resolves_to_the_owning_user(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::storeFor($user, 'https://fcm.googleapis.com/fcm/send/rel-endpoint', 'p256dh', 'auth', null);

        $this->assertTrue($subscription->user->is($user));
    }

    /**
     * A shared/kiosk browser: two different accounts logging in on the same device both end up handed the
     * identical browser-level push subscription by the Push API. Re-subscribing under a different account
     * must explicitly reassign the row to the new owner — not leave two rows sharing one endpoint_hash
     * (impossible, it's unique) and not silently leave it attributed to the wrong account.
     */
    public function test_store_for_reassigns_an_endpoint_from_a_different_user_rather_than_leaving_it_orphaned(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/shared-device-endpoint';

        PushSubscription::storeFor($userA, $endpoint, 'a-key', 'a-auth', null);
        $forB = PushSubscription::storeFor($userB, $endpoint, 'b-key', 'b-auth', null);

        // Reassignment goes through an explicit delete-then-create, so the row id itself isn't
        // preserved — what matters is exactly one row exists, and it's owned by the new subscriber.
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $forB->id,
            'user_id' => $userB->id,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'b-key',
            'auth_token' => 'b-auth',
        ]);
        $this->assertSame(0, $userA->pushSubscriptions()->count());
        $this->assertSame(1, $userB->pushSubscriptions()->count());
    }
}
