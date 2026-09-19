<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskGroupResource;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Support\TaskMutator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Task groups — the middle size between a task and a project (CLAUDE.md, "Task-Gruppen"). A group
 * is only ever created *together with* its first tasks (an empty group has no reason to exist, same
 * as QuickCapture's group target), and membership afterwards is changed through the task itself:
 * PATCH /tasks/{id} with a `group_id` (or null to release it). Both go through TaskMutator, so
 * every invariant (a task is in a project or a group, never both; a group that drops to one task or
 * none dissolves) holds exactly as it does in the app.
 */
class TaskGroupController extends Controller
{
    protected function userGroup(Request $request, int $id): TaskGroup
    {
        return $request->user()->taskGroups()->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $groups = $request->user()->taskGroups()
            ->ordered()
            ->withCount([
                'tasks as active_count' => fn ($q) => $q->where('is_completed', false),
                'tasks as done_count' => fn ($q) => $q->where('is_completed', true),
            ])
            ->get();

        return TaskGroupResource::collection($groups)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $group = $request->user()->taskGroups()
            ->withCount([
                'tasks as active_count' => fn ($q) => $q->where('is_completed', false),
                'tasks as done_count' => fn ($q) => $q->where('is_completed', true),
            ])
            ->with('tasks')
            ->findOrFail($id);

        return (new TaskGroupResource($group))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')->where('user_id', $user->id)->whereNull('project_id')],
        ]);

        $group = $user->taskGroups()->create([
            'name' => trim($data['name']),
            'sort_order' => 0,
        ]);

        foreach ($data['task_ids'] as $taskId) {
            TaskMutator::applyUpdate(Task::forUser($user)->findOrFail($taskId), $user, ['group_id' => $group->id]);
        }

        return (new TaskGroupResource($group->fresh()->loadMissing('tasks')))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = $this->userGroup($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        if (array_key_exists('name', $data)) {
            $group->update(['name' => trim($data['name'])]);
        }

        return (new TaskGroupResource($group->fresh()))->response();
    }

    /** Dissolves the group: its tasks stay exactly where they are and simply become loose again. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->userGroup($request, $id)->delete();

        return response()->json(null, 204);
    }
}
