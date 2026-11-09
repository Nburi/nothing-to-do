<?php

namespace App\Services\Planning;

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Support\TaskMutator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The daily sort (R3, step 2.3): one Inbox item goes to To-do, Task or Later with a size,
 * optional hard and soft dates, an area and a first step.
 *
 * - A missing size counts as Short (G1); To-dos and Tasks get it written, so the load forecast
 *   sees it. Later asks for no size: it is for ideas, not commitments.
 * - Later keeps a soft date but never a hard one (a hard deadline is a commitment).
 * - The item lands at the end of its new list.
 *
 * Writes go through TaskMutator, the same path as the REST API and MCP.
 */
final class InboxSort
{
    public const TARGETS = ['todos', 'tasks', 'later'];

    /** Fields a sort changes, for undo. */
    private const SNAPSHOT = ['list', 'size', 'hard_deadline', 'soft_deadline', 'area_id', 'first_step', 'sort_order'];

    /**
     * @param  array<string, mixed>  $input  list, size, hard_deadline, soft_deadline, area_id, first_step
     * @return array{task: Task, before: array<string, mixed>}
     */
    public function sort(User $user, int $taskId, array $input): array
    {
        $task = Task::forUser($user)->active()->where('list', 'inbox')->findOrFail($taskId);
        $data = $this->validate($user, $input);
        $before = $this->snapshot($task);

        $later = $data['list'] === Task::LATER;
        $fields = [
            'size' => $later ? ($data['size'] ?? null) : ($data['size'] ?? Size::Short->value),
            'hard_deadline' => $later ? null : ($data['hard_deadline'] ?? null),
            'soft_deadline' => $data['soft_deadline'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'first_step' => $data['first_step'] ?? null,
        ];

        $task = DB::transaction(function () use ($task, $user, $data, $fields) {
            $last = Task::forUser($user)->inList($data['list'])->max('sort_order');
            $task = TaskMutator::applyUpdate($task, $user, ['list' => $data['list']]);
            $task = TaskMutator::applyRefactorFields($task, $fields);
            $task->update(['sort_order' => $last === null ? 0 : $last + 1]);

            return $task->fresh();
        });

        return ['task' => $task, 'before' => $before];
    }

    /** Put a sorted item back exactly as it was (the toast's "Rückgängig"). */
    public function undo(User $user, int $taskId, array $before): Task
    {
        $task = Task::forUser($user)->active()->findOrFail($taskId);
        $restore = array_intersect_key($before, array_flip(self::SNAPSHOT));
        $task->update($restore);

        return $task->fresh();
    }

    /**
     * Validated input with German messages per field.
     *
     * @return array<string, mixed>
     */
    public function validate(User $user, array $input): array
    {
        $today = $user->localToday()->toDateString();

        return Validator::make($input, [
            'list' => ['required', Rule::in(self::TARGETS)],
            'size' => ['nullable', Rule::enum(Size::class)],
            'hard_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'soft_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'area_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')->where('user_id', $user->id)],
            'first_step' => ['nullable', 'string', 'max:255'],
        ], [
            'list.required' => __('sort.errors.list'),
            'list.in' => __('sort.errors.list'),
            'size.enum' => __('sort.errors.size'),
            'hard_deadline.date_format' => __('sort.errors.date'),
            'soft_deadline.date_format' => __('sort.errors.date'),
            'hard_deadline.after_or_equal' => __('sort.errors.past'),
            'soft_deadline.after_or_equal' => __('sort.errors.past'),
            'area_id.integer' => __('sort.errors.area'),
            'area_id.exists' => __('sort.errors.area'),
            'first_step.max' => __('sort.errors.first_step'),
        ])->validate();
    }

    /** @return array<string, mixed> */
    private function snapshot(Task $task): array
    {
        $out = [];
        foreach (self::SNAPSHOT as $field) {
            $value = $task->getAttributes()[$field] ?? null;
            $out[$field] = $value;
        }

        return $out;
    }
}
