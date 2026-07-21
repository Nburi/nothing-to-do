<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a Web Push notification to every subscription a user has (multiple
 * devices/browsers are all notified). Delivery is synchronous — this app has
 * no queue worker running, and the volume here (a handful of subscriptions
 * per user) doesn't warrant one. Any subscription the push service reports
 * as gone (410/404 — permission revoked, browser data cleared, etc.) is
 * pruned immediately.
 */
class PushNotifier
{
    public function __construct(private readonly WebPush $webPush) {}

    /** @param array{title: string, body: string, url?: string} $payload */
    public function notify(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth_token,
                ]),
                json_encode($payload),
            );
        }

        // One malformed/corrupt subscription (e.g. truncated key data) throws
        // during minishlink/web-push's encryption step *before* any HTTP send —
        // outside the library's own per-request failure handling. Uncaught,
        // that would abort the caller's loop over every other due user/event
        // in the same scheduled-command tick. Never let one bad subscription
        // take the rest of the fleet down with it.
        try {
            foreach ($this->webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))->delete();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
