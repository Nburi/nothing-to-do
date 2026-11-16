<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\TaskSession;
use App\Services\DayPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Planer's "Aufteilen" over the API: spread one task across several days,
 * end the split, tick a single part. Everything funnels through DayPlanner, so
 * the app's own rules hold here too (Planer on, horizon, one part per day,
 * finished parts never touched) and a foreign id is simply a 404.
 */
class TaskSplitController extends Controller
{
    /**
     * Splits (or re-splits) a task. Give the days yourself (`dates`), or give
     * `parts` and let the app propose them — the same proposal the sheet makes.
     * `total_minutes` is the work still to be planned, divided over the open
     * parts; it defaults to the task's estimate (or its open parts' minutes).
     */
    public function update(Request $request, int $task): JsonResponse
    {
        $user = $request->user();
        $model = $user->tasks()->findOrFail($task);

        $data = $request->validate([
            'dates' => ['sometimes', 'array', 'min:1', 'max:'.DayPlanner::HORIZON_DAYS],
            'dates.*' => ['date_format:Y-m-d'],
            'parts' => ['sometimes', 'integer', 'min:1', 'max:'.DayPlanner::HORIZON_DAYS],
            'total_minutes' => ['sometimes', 'integer', 'min:'.DayPlanner::MIN_PART_MINUTES, 'max:'.(DayPlanner::MAX_PART_MINUTES * DayPlanner::HORIZON_DAYS)],
        ]);

        if (! isset($data['dates']) && ! isset($data['parts'])) {
            return response()->json(['message' => 'Give either "dates" or "parts".'], 422);
        }

        $context = DayPlanner::splitContext($user, $model);

        if ($context === null) {
            return response()->json(['message' => 'This task cannot be split: the Planer must be on and the task an open To-Do, Task or project task.'], 422);
        }

        $total = $data['total_minutes'] ?? $context['defaultTotal'];
        $dates = $data['dates'] ?? DayPlanner::suggestSplitDates($user, $model, $data['parts'], $total);

        if (! DayPlanner::saveSplit($user, $model, $dates, $total)) {
            return response()->json(['message' => 'Could not split: pick at least two different days within the next '.DayPlanner::HORIZON_DAYS.' days (finished parts count).'], 422);
        }

        return (new TaskResource($model->fresh()->load('sessions')))->response();
    }

    /** Ends the split: every part goes and the task returns to the backlog. */
    public function destroy(Request $request, int $task): JsonResponse
    {
        $user = $request->user();

        DayPlanner::unsplit($user, $user->tasks()->findOrFail($task));

        return response()->json(null, 204);
    }

    /** Ticks one part off (`done: true`) or back open (`done: false`). Never completes the task itself. */
    public function part(Request $request, int $session): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['done' => ['required', 'boolean']]);

        $part = TaskSession::query()->whereHas('task', fn ($q) => $q->forUser($user))->findOrFail($session);
        $result = DayPlanner::completeSession($user, $part->id, (bool) $data['done']);

        abort_if($result === null, 404);

        return (new TaskResource($part->task->fresh()->load('sessions')))->response();
    }
}
