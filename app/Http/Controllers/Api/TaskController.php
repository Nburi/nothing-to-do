<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /** Always resolve a task through the owner relationship — never trust an id alone. */
    protected function userTask(Request $request, int $id): Task
    {
        return $request->user()->tasks()->findOrFail($id);
    }

    /**
     * List tasks. Filters: list=inbox|todos|tasks|projects, project_id, today=1,
     * completed=1 (include completed tasks alongside active ones). By default
     * only active tasks are returned and project-assigned tasks are excluded
     * unless project_id is given explicitly.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'project_id' => ['sometimes', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'today' => ['sometimes', 'boolean'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $query = $request->user()->tasks()->newQuery();

        if (isset($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        } else {
            $query->onBoard();
        }

        if (isset($data['list'])) {
            $query->inList($data['list']);
        }

        if ($request->boolean('today')) {
            $query->where('is_today', true);
        }

        if (! $request->boolean('completed')) {
            $query->where('is_completed', false);
        }

        $tasks = $query->boardOrdered()->get();

        return TaskResource::collection($tasks)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return (new TaskResource($this->userTask($request, $id)))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'list' => ['sometimes', Rule::in(Task::BOARD_LISTS)],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'deadline' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'is_important' => ['sometimes', 'boolean'],
        ]);

        $title = trim($data['title']);

        $attributes = [
            'title' => $title,
            'deadline' => $data['deadline'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'is_important' => $data['is_important'] ?? false,
            'sort_order' => 0,
        ];

        if (! empty($data['project_id'])) {
            $attributes['project_id'] = $data['project_id'];
            $attributes['list'] = 'projects';
        } else {
            $attributes['list'] = $data['list'] ?? 'inbox';
        }

        $task = $request->user()->tasks()->create($attributes);

        return (new TaskResource($task->fresh()))->response()->setStatusCode(201);
    }

    /**
     * Partial update — covers every board mutation the native app exposes on a
     * task: rename, reschedule, toggle important/completed, move between
     * lists, focus for today, and assign to / release from a project.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->userTask($request, $id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'is_important' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
        ]);

        $updates = [];

        if (array_key_exists('title', $data)) {
            $updates['title'] = trim($data['title']);
        }
        if (array_key_exists('deadline', $data)) {
            $updates['deadline'] = $data['deadline'];
        }
        if (array_key_exists('due_date', $data)) {
            $updates['due_date'] = $data['due_date'];
        }
        if (array_key_exists('is_important', $data)) {
            $updates['is_important'] = $data['is_important'];
        }
        if (array_key_exists('is_completed', $data)) {
            $updates['is_completed'] = $data['is_completed'];
            $updates['completed_at'] = $data['is_completed'] ? now() : null;
        }

        // Resolve the list/project destination together, same as the edit sheet:
        // an explicit project_id always wins and forces list=projects.
        if (array_key_exists('project_id', $data)) {
            if ($data['project_id'] !== null) {
                $updates['project_id'] = $data['project_id'];
                $updates['list'] = 'projects';
                $updates['is_today'] = false;
            } else {
                $updates['project_id'] = null;
                $updates['list'] = $data['list'] ?? 'inbox';
            }
        } elseif (array_key_exists('list', $data)) {
            $updates['list'] = $data['list'];
            $updates['project_id'] = null;
            if (! in_array($data['list'], Task::TODAY_LISTS, true)) {
                $updates['is_today'] = false;
            }
        }

        if (array_key_exists('is_today', $data)) {
            $finalList = $updates['list'] ?? $task->list;
            $finalProjectId = array_key_exists('project_id', $updates) ? $updates['project_id'] : $task->project_id;

            if (in_array($finalList, Task::TODAY_LISTS, true) && $finalProjectId === null) {
                $updates['is_today'] = $data['is_today'];
            }
        }

        $task->update($updates);

        return (new TaskResource($task->fresh()))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->userTask($request, $id)->delete();

        return response()->json(null, 204);
    }

    /**
     * Persist a full drag-reorder of one board zone (mirrors the board's
     * drag & drop): the destination list/today flag plus the ids now sitting
     * in it, in order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'list' => ['required', Rule::in([...Task::BOARD_LISTS, 'projects'])],
            'today' => ['sometimes', 'boolean'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $list = $data['list'];
        $today = in_array($list, ['inbox', 'projects'], true) ? false : ($data['today'] ?? false);

        foreach (array_values($data['ids']) as $position => $id) {
            $task = $request->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue;
            }

            $updates = [
                'list' => $list,
                'is_today' => $today,
                'sort_order' => $position,
            ];

            if ($list === 'projects') {
                $updates['project_id'] = null;
            }

            $task->update($updates);
        }

        return response()->json(['status' => 'ok']);
    }
}
