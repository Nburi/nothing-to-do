<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a Web Push notification to every subscription a user has (multiple
 * devices/browsers are all notified). Delivery is synchronous — this app has
 * no queue worker running, and the volume here (a handful of subscriptions
 * per user) doesn't warrant one. Any subscription the push service reports
 * as gone (410/404 — permission revoked, browser data cleared, etc.) is
 * pruned immediately. Any other delivery failure (wrong VAPID config, a TLS
 * handshake failure, a rejected payload, ...) is logged — a report that
 * merely didn't throw is not the same as one that actually delivered.
 */
class PushNotifier
{
    public function __construct(private readonly WebPush $webPush) {}

    /** @param array{title: string, body: string, url?: string} $payload */
    public function notify(User $user, array $payload): void
    {
        $this->send($user, $payload);
    }

    /**
     * Same as notify(), but returns a per-subscription delivery result instead of firing and
     * forgetting — backs Settings' "send a test notification" debug control, so a silent
     * failure (the exact class of bug that made real notifications never arrive despite every
     * scheduled command completing without error) is visible immediately instead of only
     * discoverable by reading server logs.
     *
     * @param  array{title: string, body: string, url?: string}  $payload
     * @return list<array{endpoint: string, user_agent: ?string, success: bool, status: ?int, reason: string}>
     */
    public function sendDebug(User $user, array $payload): array
    {
        return $this->send($user, $payload);
    }

    /** @return list<array{endpoint: string, user_agent: ?string, success: bool, status: ?int, reason: string}> */
    private function send(User $user, array $payload): array
    {
        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');

        if ($subscriptions->isEmpty()) {
            return [];
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

        $results = [];

        // One malformed/corrupt subscription (e.g. truncated key data) throws
        // during minishlink/web-push's encryption step *before* any HTTP send —
        // outside the library's own per-request failure handling. Uncaught,
        // that would abort the caller's loop over every other due user/event
        // in the same scheduled-command tick. Never let one bad subscription
        // take the rest of the fleet down with it.
        try {
            foreach ($this->webPush->flush() as $report) {
                $results[] = $this->handleReport($report, $subscriptions);
            }
        } catch (\Throwable $e) {
            report($e);
            $results[] = [
                'endpoint' => null,
                'user_agent' => null,
                'success' => false,
                'status' => null,
                'reason' => 'Unerwarteter Fehler: '.$e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<string, PushSubscription>  $subscriptions  keyed by endpoint
     * @return array{endpoint: string, user_agent: ?string, success: bool, status: ?int, reason: string}
     */
    private function handleReport(MessageSentReport $report, Collection $subscriptions): array
    {
        $endpoint = $report->getEndpoint();

        if ($report->isSubscriptionExpired()) {
            PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();
        } elseif (! $report->isSuccess()) {
            // Not "expired" and not thrown — e.g. a rejected/malformed request, a TLS/network
            // failure, or a push-service-side error. A completed report is not the same as a
            // delivered notification; log it so this doesn't go unnoticed indefinitely.
            Log::warning('Push notification delivery failed', [
                'endpoint' => $endpoint,
                'status' => $report->getResponse()?->getStatusCode(),
                'reason' => $report->getReason(),
            ]);
        }

        return [
            'endpoint' => $endpoint,
            'user_agent' => $subscriptions->get($endpoint)?->user_agent,
            'success' => $report->isSuccess(),
            'status' => $report->getResponse()?->getStatusCode(),
            'reason' => $report->getReason(),
        ];
    }
}
