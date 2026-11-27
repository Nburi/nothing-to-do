<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanningPayload;
use App\Http\Resources\ScheduleEventResource;
use App\Http\Resources\TaskResource;
use App\Planning\Battery;
use App\Planning\Session\SessionState;
use App\Services\FocusSession;
use App\Services\Planning\Capacity;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchWatch;
use App\Services\Planning\Suggestions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * The crunch screen's "Verschieben": with `date` the hard deadline moves to that day (it has to
     * lie after the current one), without it the soft deadline moves a week on (or is dropped when
     * the hard one comes first). 422 when the task has no deadline of that kind.
     */
    public function move(Request $request, CrunchDecisions $decisions, int $task): JsonResponse
    {
        $data = $request->validate(['date' => ['sometimes', 'nullable', 'date_format:Y-m-d']]);

        try {
            $moved = isset($data['date'])
                ? $decisions->moveHard($request->user(), $task, $data['date'])
                : $decisions->moveSoft($request->user(), $task);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['date' => array_values($e->errors())[0] ?? []]);
        }

        return (new TaskResource($moved))->response();
    }

    /**
     * The crunch screen's "Mehr Zeit": one more block of a pomodoro category within the next 14
     * days. Undo = DELETE /schedule-events/{id}.
     */
    public function moreTime(Request $request, CrunchDecisions $decisions): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'category_id' => ['required', 'integer'],
        ]);

        try {
            $event = $decisions->addTime($request->user(), $data['date'], $data['start_time'], $data['end_time'], (int) $data['category_id']);
        } catch (ValidationException $e) {
            $map = ['addDate' => 'date', 'addEnd' => 'end_time', 'addCategory' => 'category_id'];
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [$map[$k] ?? $k => $m])->all());
        }

        return (new ScheduleEventResource($event->load('category')))->response()->setStatusCode(201);
    }

    private function sessionResponse(Request $request, FocusSession $sessions, SessionState $state, CarbonImmutable $now): JsonResponse
    {
        return response()->json(PlanningPayload::session($state, $sessions->remainingSeconds($request->user(), $now), $now));
    }
}
