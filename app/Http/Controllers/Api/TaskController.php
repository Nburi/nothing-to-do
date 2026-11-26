<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Planning\Size;
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
     * (include completed tasks alongside active ones), size, area_id, and
     * project_id / group_id / stage_id (the steps of a project, group or stage).
     * By default only active tasks are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'today' => ['sometimes', 'boolean'],
            'completed' => ['sometimes', 'boolean'],
            'size' => ['sometimes', Rule::enum(Size::class)],
            'area_id' => ['sometimes', 'integer'],
            'project_id' => ['sometimes', 'integer'],
            'group_id' => ['sometimes', 'integer'],
            'stage_id' => ['sometimes', 'integer'],
        ]);

        $query = $request->user()->tasks()->newQuery();

        foreach (['size', 'area_id', 'project_id', 'stage_id'] as $column) {
            if (isset($data[$column])) {
                $query->where($column, $data[$column]);
            }
        }
        if (isset($data['group_id'])) {
            // A project step keeps the group it came from; the group only counts while the step is not in a project.
            $query->whereNull('project_id')->where('group_id', $data['group_id']);
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
        return (new TaskResource($this->userTask($request, $id)->load('sessions')))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'deadline' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'hard_deadline' => ['sometimes', 'nullable', 'date'],
            'soft_deadline' => ['sometimes', 'nullable', 'date'],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
            'area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')->where('user_id', $request->user()->id)],
            'first_step' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'repeat_rule' => ['sometimes', 'nullable', Rule::in(array_keys(Task::REPEAT_RULES))],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $task = $request->user()->tasks()->create(TaskMutator::attributesForCreate($data));
        $task = TaskMutator::applyRefactorFields($task, $data);

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
            'title' => ['sometimes', 'string', 'max:500'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'hard_deadline' => ['sometimes', 'nullable', 'date'],
            'soft_deadline' => ['sometimes', 'nullable', 'date'],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
            'area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')->where('user_id', $request->user()->id)],
            'first_step' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'repeat_rule' => ['sometimes', 'nullable', Rule::in(array_keys(Task::REPEAT_RULES))],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'is_completed' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
        ]);

        $task = TaskMutator::applyUpdate($task, $request->user(), $data);
        $task = TaskMutator::applyRefactorFields($task, $data);

        return (new TaskResource($task))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // A real delete (with its parts and day plans): the REST API has no undo. The shell's soft delete is ItemEditor::delete().
        $this->userTask($request, $id)->forceDelete();

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
