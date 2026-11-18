<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Planning\Capture;
use App\Support\NarrowAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Refactor step 1.5 (R3, risk C10): POST /api/capture { "text": "…" }.
 * Meant for the iPhone Shortcut / Action Button, with a token that can do
 * nothing else (NarrowAbility::CAPTURE). A full API token may capture too.
 * The answer is short on purpose: the Shortcut shows `message` as is.
 */
class CaptureController extends Controller
{
    public function __invoke(Request $request, Capture $capture): JsonResponse
    {
        if (! $request->user()->tokenCan(NarrowAbility::CAPTURE)) {
            return response()->json(['message' => __('capture.token_cannot_capture')], 403);
        }

        $task = $capture->inbox($request->user(), $request->input('text'));

        return response()->json([
            'id' => $task->id,
            'title' => $task->title,
            'list' => $task->list,
            'message' => __('capture.saved'),
        ], 201);
    }
}
