<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventCategoryResource;
use App\Models\ScheduleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = $request->user()->eventCategories()->ordered()->get();

        return EventCategoryResource::collection($categories)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'pomodoro_enabled' => ['sometimes', 'boolean'],
        ]);

        $category = $request->user()->eventCategories()->create([
            'name' => trim($data['name']),
            'color' => $data['color'],
            'pomodoro_enabled' => $data['pomodoro_enabled'] ?? false,
            'sort_order' => $request->user()->eventCategories()->count(),
        ]);

        return (new EventCategoryResource($category->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = $request->user()->eventCategories()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'pomodoro_enabled' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim($data['name']);
        }
        if (array_key_exists('color', $data)) {
            $updates['color'] = $data['color'];
        }
        if (array_key_exists('pomodoro_enabled', $data)) {
            $updates['pomodoro_enabled'] = $data['pomodoro_enabled'];
        }

        $category->update($updates);

        return (new EventCategoryResource($category->fresh()))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->eventCategories()->whereKey($id)->delete();

        return response()->json(null, 204);
    }
}
