<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Planning\EveningNudge;
use App\Services\Planning\NudgeHold;
use App\Services\PushNotifier;
use App\Support\NarrowAbility;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Spike 0.1 (R2): an iPhone Shortcut automation ("when I arrive at home", by location) calls this
 * with a narrow token; the server answers with a Web Push to every device of the user. The JSON
 * answer tells the Shortcut what happened, and every call is logged so a missing nudge can be
 * traced to the phone (no call), the rules (called, held back), the push service (sent, not
 * delivered) or the device (delivered, not shown).
 *
 * An arrival is the evening's nudge sent early, not an extra push. The location trigger fires again
 * when he leaves and comes back or only passes by, so EveningNudge decides: one nudge per evening
 * (evenings.nudge_sent_at, shared with the time-based nudge), none on a weekday without a nudge
 * time, none on paused days, none once tonight's reset was started or closed, none during a focus
 * session, none long before the nudge time. Held back is answered with 200 and the reason.
 */
class ArrivalController extends Controller
{
    /** Same copy as the spike; the link opens the calm reset like the time-based nudge does. */
    private const PUSH = [
        'title' => 'Du bist zuhause',
        'body' => 'Zehn Minuten zurücksetzen, dann zeigt dir das Board, womit du anfängst.',
        'url' => EveningNudge::URL,
    ];

    public function __invoke(Request $request, EveningNudge $nudge): JsonResponse
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

        $now = CarbonImmutable::now();
        $hold = $nudge->holdOnArrival($user, $now);

        if ($hold !== null) {
            return $this->heldBack($user, $devices, $hold);
        }

        try {
            // Built only now. A missing or broken push setup throws here, before tonight's nudge is claimed,
            // so fixing the keys and arriving again the same evening still works.
            $push = app(PushNotifier::class);
        } catch (\Throwable $e) {
            Log::warning('Arrival ping without a working push setup', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json([
                'devices' => $devices,
                'delivered' => 0,
                'sent' => false,
                'reason' => 'push_unavailable',
                'message' => 'Mitteilungen sind auf dem Server nicht eingerichtet. Bitte melde das dem Betreiber.',
            ], 503);
        }

        // Two arrivals at once, or the scheduler at the same moment: exactly one gets tonight's nudge.
        if (! $nudge->claim($user, $now)) {
            return $this->heldBack($user, $devices, NudgeHold::AlreadySent);
        }

        try {
            $results = $push->sendDebug($user, self::PUSH);
        } catch (\Throwable $e) {
            // Tonight's nudge is spent, like a failing push of the scheduler: logged, not retried.
            Log::warning('Arrival push could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $results = [];
        }

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
            'sent' => true,
            'reason' => null,
            'message' => $delivered > 0
                ? 'Mitteilung gesendet.'
                : 'Die Mitteilung konnte nicht zugestellt werden. Prüfe in der App unter Einstellungen die Test-Mitteilung.',
        ]);
    }

    private function heldBack(User $user, int $devices, NudgeHold $hold): JsonResponse
    {
        Log::info('Arrival ping held back', ['user_id' => $user->id, 'devices' => $devices, 'reason' => $hold->value]);

        return response()->json([
            'devices' => $devices,
            'delivered' => 0,
            'sent' => false,
            'reason' => $hold->value,
            'message' => $hold->message(),
        ]);
    }
}
