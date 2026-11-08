<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushNotifier;
use App\Support\NarrowAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Spike 0.1 (R2): an iPhone Shortcut automation ("when I join my home Wi-Fi")
 * calls this with a narrow token; the server answers with a Web Push to every
 * device of the user. The JSON answer tells the Shortcut what happened, and
 * every call is logged so a missing nudge can be traced to the phone (no
 * call), the push service (call, not delivered) or the device (delivered,
 * not shown).
 *
 * Deliberately no "once per evening" rule yet: the spike test is leaving and
 * rejoining the Wi-Fi several times. That rule is step 3.3 (A13).
 */
class ArrivalController extends Controller
{
    public function __invoke(Request $request, PushNotifier $push): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan(NarrowAbility::ARRIVAL)) {
            return response()->json(['message' => 'Dieses Token darf keine Ankunft melden.'], 403);
        }

        $devices = $user->pushSubscriptions()->count();

        if ($devices === 0) {
            Log::info('Arrival ping without push subscription', ['user_id' => $user->id]);

            return response()->json([
                'devices' => 0,
                'delivered' => 0,
                'message' => 'Auf keinem Gerät sind Mitteilungen erlaubt. Öffne die App auf dem iPhone über den Home-Bildschirm und erlaube Mitteilungen in den Einstellungen.',
            ], 409);
        }

        $results = $push->sendDebug($user, [
            'title' => 'Du bist zuhause',
            'body' => 'Zehn Minuten zurücksetzen, dann zeigt dir das Board, womit du anfängst.',
            'url' => '/',
        ]);

        $delivered = count(array_filter($results, fn (array $r) => $r['success']));

        Log::info('Arrival ping', [
            'user_id' => $user->id,
            'devices' => $devices,
            'delivered' => $delivered,
            'statuses' => array_map(fn (array $r) => $r['status'], $results),
        ]);

        return response()->json([
            'devices' => $devices,
            'delivered' => $delivered,
            'message' => $delivered > 0
                ? 'Mitteilung gesendet.'
                : 'Die Mitteilung konnte nicht zugestellt werden. Prüfe in der App unter Einstellungen die Test-Mitteilung.',
        ]);
    }
}
