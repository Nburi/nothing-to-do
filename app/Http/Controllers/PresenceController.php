<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The presence heartbeat. Deliberately a plain controller rather than a
 * Livewire action: this fires once a minute per open tab and only needs to
 * write one column, so it should not pay for a component hydrate/render cycle.
 *
 * The client only calls this while the tab is actually in the foreground (see
 * the heartbeat block in resources/js/app.js), which is what makes "online"
 * mean "using the app" rather than "has a tab open somewhere since Tuesday".
 */
class PresenceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        auth()->user()->touchPresence();

        return response()->json(['ok' => true]);
    }
}
