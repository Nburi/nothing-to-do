<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser's Web Push subscription (one row per device/browser a user has
 * granted notification permission on). Delivery goes through PushNotifier,
 * which uses endpoint/p256dh/auth_token to address the browser's push
 * service directly — no open tab required.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth_token',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * Re-subscribing the same endpoint (e.g. after permission was re-granted) refreshes the existing row.
     * A browser-level push subscription isn't tied to any one account, so on a shared/kiosk browser a
     * different user logging in and enabling notifications legitimately takes over the same endpoint —
     * only the currently logged-in account should keep receiving it. That reassignment is made explicit
     * here (delete the other owner's row first) rather than left as a side effect of updateOrCreate()
     * matching by hash alone, which would otherwise reassign it silently under the wrong owner's identity.
     */
    public static function storeFor(User $user, string $endpoint, string $p256dh, string $authToken, ?string $userAgent): self
    {
        $hash = self::hashEndpoint($endpoint);

        self::where('endpoint_hash', $hash)->where('user_id', '!=', $user->id)->delete();

        return self::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth_token' => $authToken,
                'user_agent' => $userAgent,
            ],
        );
    }
}
