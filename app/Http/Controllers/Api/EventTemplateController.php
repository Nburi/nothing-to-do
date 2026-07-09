<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventTemplateResource;
use App\Http\Resources\ScheduleEventResource;
use App\Models\ScheduleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = $request->user()->eventTemplates()->ordered()->get();

        return EventTemplateResource::collection($templates)->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->eventTemplates()->whereKey($id)->delete();

        return response()->json(null, 204);
    }

    /** Drop / tap a template onto a day → a concrete event. */
    public function apply(Request $request, int $id): JsonResponse
    {
        $template = $request->user()->eventTemplates()->findOrFail($id);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $startTime = $data['start_time'] ?? ($template->default_start ?: '08:00');

        $event = $request->user()->scheduleEvents()->create([
            'category_id' => $template->category_id,
            'title' => $template->displayName(),
            'color' => $template->colorToken(),
            'date' => $data['date'],
            'start_time' => $startTime,
            'end_time' => ScheduleEvent::fromMinutes(ScheduleEvent::toMinutes($startTime) + $template->duration),
        ]);

        return (new ScheduleEventResource($event->fresh()))->response()->setStatusCode(201);
    }
}
