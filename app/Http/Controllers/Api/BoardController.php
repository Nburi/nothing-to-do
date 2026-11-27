<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Planning\BoardOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Api parity for the shared board order (step 4.8): tasks, projects and groups sit in one order per
 * user (`board_position`, lower = further up). References are "task:12", "project:3", "group:4"
 * (the `ref` of a card is on every project and group payload).
 */
class BoardController extends Controller
{
    /**
     * Put the entries in the given order. They swap the places they already hold, so everything that
     * is not in the list keeps its place (the same local drop as on the board). Unknown, foreign and
     * finished entries are skipped. Unlike a drop on the board this does not add the resting cards.
     */
    public function order(Request $request, BoardOrder $order): JsonResponse
    {
        $data = $request->validate([
            'refs' => ['required', 'array', 'min:1', 'max:200'],
            'refs.*' => ['string', 'regex:/^(task|project|group):\d+$/'],
        ]);

        return response()->json(['data' => ['changed' => $order->place($request->user(), $data['refs'])]]);
    }

    /** "Nach oben holen": the entry becomes the first of the whole board. */
    public function top(Request $request, BoardOrder $order): JsonResponse
    {
        $data = $request->validate(['ref' => ['required', 'string', 'regex:/^(task|project|group):\d+$/']]);

        abort_unless($order->toTop($request->user(), $data['ref']), 404);

        return response()->json(['data' => ['ref' => $data['ref']]]);
    }
}
