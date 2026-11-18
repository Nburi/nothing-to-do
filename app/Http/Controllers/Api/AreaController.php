<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\ScheduleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Refactor step 1.5 (API parity, R10): areas are an open, user-defined list.
 * Deleting an area unlinks it from categories and tasks; no task is deleted
 * (the foreign keys null out / cascade the pivot only).
 */
class AreaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->areas()->ordered()->get()->map(fn (Area $a) => $this->payload($a))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, creating: true);
        $area = $request->user()->areas()->create($data + ['sort_order' => (int) $request->user()->areas()->max('sort_order') + 1]);

        return response()->json(['data' => $this->payload($area)], 201);
    }

    public function update(Request $request, int $area): JsonResponse
    {
        $model = $request->user()->areas()->findOrFail($area);
        $model->update($this->validated($request, creating: false));

        return response()->json(['data' => $this->payload($model)]);
    }

    public function destroy(Request $request, int $area): JsonResponse
    {
        $request->user()->areas()->findOrFail($area)->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:60'],
            'color' => [$creating ? 'sometimes' : 'sometimes', Rule::in(ScheduleEvent::EVENT_COLORS)],
        ]);
    }

    private function payload(Area $area): array
    {
        return ['id' => $area->id, 'name' => $area->name, 'color' => $area->color, 'sort_order' => $area->sort_order];
    }
}
