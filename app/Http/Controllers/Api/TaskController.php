<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Support\TaskMutator;
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
     * List tasks. Filters: list=inbox|todos|tasks, today=1, completed=1
     * (include completed tasks alongside active ones). By default only active
     * tasks are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'today' => ['sometimes', 'boolean'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $query = $request->user()->tasks()->newQuery();

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
            'deadline' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
        ]);

        $task = $request->user()->tasks()->create(TaskMutator::attributesForCreate($data));

        return (new TaskResource($task->fresh()))->response()->setStatusCode(201);
    }

    /**
     * Partial update — covers every board mutation the native app exposes on a
     * task: rename, reschedule, toggle important/completed, move between
     * lists and focus for today.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->userTask($request, $id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
        ]);

        $task = TaskMutator::applyUpdate($task, $request->user(), $data);

        return (new TaskResource($task))->response();
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
            'list' => ['required', Rule::in(Task::BOARD_LISTS)],
            'today' => ['sometimes', 'boolean'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $list = $data['list'];
        $today = $list === 'inbox' ? false : ($data['today'] ?? false);
        $targetDate = $today ? $request->user()->localToday() : null;

        foreach (array_values($data['ids']) as $position => $id) {
            $task = $request->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue;
            }

            $updates = [
                'list' => $list,
                'is_today' => $today,
                'today_date' => $task->todayDateFor($today, $targetDate),
                'sort_order' => $position,
            ];

            $task->update($updates);
        }

        return response()->json(['status' => 'ok']);
    }
}
