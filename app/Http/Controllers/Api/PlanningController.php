<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanningPayload;
use App\Http\Resources\TaskResource;
use App\Planning\Battery;
use App\Services\FocusSession;
use App\Services\Planning\Capacity;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchWatch;
use App\Services\Planning\Suggestions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Refactor step 1.5 (API parity): the planning engines over REST, so a
 * Shortcut, a widget or an assistant can do what the app does. Every call is
 * scoped to the token's user; task ids are resolved through that user's rows
 * by the services (404 otherwise).
 */
class PlanningController extends Controller
{
    public function suggestion(Request $request, Suggestions $suggestions): JsonResponse
    {
        return response()->json(PlanningPayload::suggestion($suggestions->current($request->user())));
    }

    public function another(Request $request, Suggestions $suggestions): JsonResponse
    {
        return response()->json(PlanningPayload::suggestion($suggestions->another($request->user())));
    }

    public function battery(Request $request, Suggestions $suggestions): JsonResponse
    {
        $data = $request->validate(['battery' => ['present', 'nullable', Rule::enum(Battery::class)]]);
        $battery = $data['battery'] === null ? null : Battery::from($data['battery']);

        return response()->json(PlanningPayload::suggestion($suggestions->setBattery($request->user(), $battery)));
    }

    public function session(Request $request, FocusSession $sessions): JsonResponse
    {
        return $this->sessionResponse($request, $sessions, $sessions->current($request->user(), $now = CarbonImmutable::now()), $now);
    }

    public function sessionAction(Request $request, FocusSession $sessions, string $action): JsonResponse
    {
        $user = $request->user();
        $now = CarbonImmutable::now();

        $state = match ($action) {
            'start' => $sessions->start($user, $request->validate(['task_id' => ['sometimes', 'nullable', 'integer']])['task_id'] ?? null, $now),
            'done' => $sessions->done($user, $request->validate(['task_id' => ['required', 'integer']])['task_id'], $now),
            'pause' => $sessions->pause($user, $now),
            'resume' => $sessions->resume($user, $now),
            'stop' => $sessions->stop($user, $now),
        };

        return $this->sessionResponse($request, $sessions, $state, $now);
    }

    public function forecast(Request $request, Capacity $capacity, CrunchWatch $watch): JsonResponse
    {
        return response()->json(PlanningPayload::forecast($capacity->forecast($request->user()), $watch->state($request->user())));
    }

    public function drop(Request $request, CrunchDecisions $decisions, int $task): JsonResponse
    {
        return (new TaskResource($decisions->drop($request->user(), $task)))->response();
    }

    public function shrink(Request $request, CrunchDecisions $decisions, int $task): JsonResponse
    {
        return (new TaskResource($decisions->shrink($request->user(), $task)))->response();
    }

    private function sessionResponse(Request $request, FocusSession $sessions, $state, CarbonImmutable $now): JsonResponse
    {
        return response()->json(PlanningPayload::session($state, $sessions->remainingSeconds($request->user(), $now), $now));
    }
}
