<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesProjectModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectsPayload;
use App\Http\Resources\TaskResource;
use App\Models\TaskGroup;
use App\Services\Projects\ProjectEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Step 4.3: task groups (a small bundle of steps in one list) through the API. No delete yet, as in the app. */
class TaskGroupController extends Controller
{
    use ResolvesProjectModels;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ProjectsPayload::bundles($request->user(), 'group')]);
    }

    public function store(Request $request, ProjectEditor $editor): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.TaskGroup::NAME_MAX],
            'list' => ['sometimes', Rule::in(TaskGroup::LISTS)],
            'steps' => ['sometimes', 'array', 'max:50'],
            'steps.*' => ['required', 'string', 'max:500'],
        ]);

        $group = $editor->createGroup($request->user(), trim($data['name']), $data['list'] ?? 'tasks');
        foreach ($data['steps'] ?? [] as $title) {
            $editor->addGroupStep($request->user(), $group, trim($title));
        }

        return response()->json(['data' => ProjectsPayload::group($request->user(), $group)], 201);
    }

    public function show(Request $request, int $group): JsonResponse
    {
        return response()->json(['data' => ProjectsPayload::group($request->user(), $this->ownedGroup($request->user(), $group))]);
    }

    public function update(Request $request, int $group, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedGroup($request->user(), $group);
        $data = $request->validate(['name' => ['required', 'string', 'max:'.TaskGroup::NAME_MAX]]);
        $editor->rename($model, trim($data['name']));

        return response()->json(['data' => ProjectsPayload::group($request->user(), $model->refresh())]);
    }

    /** Dissolve the group: its tasks stay on the board as plain tasks of their list; its note cards go. */
    public function destroy(Request $request, int $group, ProjectEditor $editor): JsonResponse
    {
        $editor->deleteGroup($this->ownedGroup($request->user(), $group));

        return response()->json(null, 204);
    }

    public function storeStep(Request $request, int $group, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedGroup($request->user(), $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:500']]);

        $step = $editor->addGroupStep($request->user(), $model, trim($data['title']));

        return (new TaskResource($step->refresh()))->response()->setStatusCode(201);
    }
}
