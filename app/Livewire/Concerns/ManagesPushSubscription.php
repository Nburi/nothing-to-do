<?php

namespace App\Livewire\Concerns;

use App\Models\PushSubscription;

/**
 * Store and remove this browser's Web Push subscription. Shared by the old settings page and
 * the new shell's nudge card (step 3.3), so both validate endpoints the same way.
 */
trait ManagesPushSubscription
{
    /** Persists a browser's Web Push subscription so the server can notify it even with every tab closed. */
    public function subscribeToPush(string $endpoint, string $p256dh, string $authToken): void
    {
        $data = validator(
            ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'authToken' => $authToken],
            [
                'endpoint' => ['required', 'url:https', function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->isPublicHttpsHost($value)) {
                        $fail('The endpoint must be a public push-service URL.');
                    }
                }],
                'p256dh' => ['required', 'string'],
                'authToken' => ['required', 'string'],
            ],
        )->validate();

        PushSubscription::storeFor(auth()->user(), $data['endpoint'], $data['p256dh'], $data['authToken'], request()->userAgent());
    }

    /** Removes this browser's subscription — the server stops pushing to it. */
    public function unsubscribeFromPush(string $endpoint): void
    {
        auth()->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();
    }

    /**
     * Defence-in-depth against SSRF: the server later makes a real, VAPID-signed outbound HTTP request to
     * whatever endpoint is stored (PushNotifier -> minishlink/web-push), so a tampered client can't be
     * allowed to point that request at an internal/loopback host by supplying a crafted endpoint here — a
     * genuine browser-issued push endpoint is always a public host on a real push service. This only
     * catches IP-literal hosts, not a hostname that resolves to a private address later (DNS rebinding) —
     * an accepted residual risk given this app's scale.
     */
    private function isPublicHttpsHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === '' || strtolower($host) === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}
