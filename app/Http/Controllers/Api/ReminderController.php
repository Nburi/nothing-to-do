<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Services\Reminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Nicht vergessen" over the API (Apple Shortcuts: "Siri, erinnere mich morgen an die Turnschuhe").
 * Every rule — validation, the cap, what moving a reminder resets — lives in App\Services\Reminders,
 * so this is the same behaviour as the sheet and QuickCapture. A foreign id is a 404.
 */
class ReminderController extends Controller
{
    private function owned(Request $request, int $id): Reminder
    {
        return $request->user()->reminders()->findOrFail($id);
    }

    /** Upcoming reminders (today on), earliest first; `?done=0` leaves out the ones already ticked off. */
    public function index(Request $request): JsonResponse
    {
        $reminders = Reminders::upcoming($request->user());

        if ($request->has('done') && ! $request->boolean('done')) {
            $reminders = $reminders->reject(fn (Reminder $r) => $r->isAcknowledged())->values();
        }

        return ReminderResource::collection($reminders)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $reminder = Reminders::create($request->user(), $request->only(['title', 'remind_on', 'notify_evening_before', 'notify_on_day']));

        return (new ReminderResource($reminder))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return (new ReminderResource($this->owned($request, $id)))->response();
    }

    /** Edit fields, or tick it off / reopen it with `acknowledged` (true = "Hab ich"). */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reminder = $this->owned($request, $id);
        $request->validate(['acknowledged' => ['sometimes', 'boolean']]);

        $fields = $request->only(['title', 'remind_on', 'notify_evening_before', 'notify_on_day']);

        if ($fields !== []) {
            $reminder = Reminders::update($user, $reminder, $fields);
        }

        if ($request->has('acknowledged')) {
            $reminder = Reminders::acknowledge($reminder, $request->boolean('acknowledged'));
        }

        return (new ReminderResource($reminder))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Reminders::delete($this->owned($request, $id));

        return response()->json(null, 204);
    }
}
