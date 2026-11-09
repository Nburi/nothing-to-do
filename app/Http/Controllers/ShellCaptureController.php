<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\Planning\Capture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The capture line of the new app (R3, step 2.3). Plain JSON instead of a Livewire call, so the
 * Alpine island keeps the typed text and shows a calm message when the connection drops, and a
 * Livewire re-render never eats what is being typed. Same Capture service as the API (step 1.5).
 */
class ShellCaptureController extends Controller
{
    /** How long "Rückgängig" can take a fresh capture back. */
    public const UNDO_MINUTES = 10;

    public function store(Request $request, Capture $capture): JsonResponse
    {
        $task = $capture->inbox($request->user(), $request->input('text'));

        return response()->json([
            'id' => $task->id,
            'title' => $task->title,
            'inbox' => Task::forUser($request->user())->active()->inList('inbox')->count(),
            'message' => __('capture.saved'),
        ], 201);
    }

    /** Undo a capture: only an untouched Inbox item, only right after it was captured. */
    public function destroy(Request $request, int $task): JsonResponse
    {
        $row = Task::forUser($request->user())
            ->whereKey($task)
            ->where('list', 'inbox')
            ->where('is_completed', false)
            ->where('created_at', '>=', now()->subMinutes(self::UNDO_MINUTES))
            ->first();

        if ($row === null) {
            return response()->json(['message' => __('capture.undo_gone')], 404);
        }

        $row->delete();

        return response()->json(['message' => __('capture.undone')]);
    }
}
