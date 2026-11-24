<?php

namespace App\Services\Planning;

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Support\TaskMutator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Editing, moving and deleting one loose item of the board (step 4.9): a To-do, Task, Inbox item or
 * an idea in Later. Editing and moving are the same write (a move is an edit of `list` only), so
 * both are validated, snapshotted for "Rückgängig" and placed in one way.
 *
 * - A move to another list puts the item at the bottom of that list and of the shared board
 *   order (step 4.8, C31), like a new arrival; an edit that keeps the list keeps its place.
 * - Later keeps a soft date but never a hard one (a hard deadline is a commitment), as in the sort.
 * - A date already in the past stays valid as long as it is not changed: an overdue task must
 *   still be editable (only a changed date has to lie in the future).
 * - Delete is a soft delete (`tasks.deleted_at`), so it can be taken back with every day plan and
 *   work session intact. The task of a running session cannot be deleted.
 *
 * Writes go through TaskMutator, the same path as the REST API, MCP and the sort.
 */
final class ItemEditor
{
    /** Fields an edit or a move changes, for undo. */
    private const SNAPSHOT = [
        'title', 'notes', 'list', 'size', 'hard_deadline', 'soft_deadline', 'area_id', 'first_step',
        'sort_order', 'board_position', 'is_today', 'today_date',
    ];

    private const DATES = ['hard_deadline', 'soft_deadline'];

    /**
     * Change the given fields; the ones left out keep their value.
     *
     * @param  array<string, mixed>  $input  title, notes, list, size, hard_deadline, soft_deadline, area_id, first_step
     * @return array{task: Task, before: array<string, mixed>, moved: bool}
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(User $user, int $taskId, array $input): array
    {
        $task = $this->find($user, $taskId);
        $data = $this->validate($user, $task, $input);
        $before = $this->snapshot($task);
        $moved = $data['list'] !== $task->list;
        $later = $data['list'] === Task::LATER;

        $task = DB::transaction(function () use ($task, $user, $data, $moved, $later) {
            $core = ['title' => $data['title'], 'notes' => $data['notes']];
            if ($moved) {
                $core['list'] = $data['list'];
            }

            $task = TaskMutator::applyUpdate($task, $user, $core);
            $task = TaskMutator::applyRefactorFields($task, [
                'size' => $data['size'],
                'hard_deadline' => $later ? null : $data['hard_deadline'],
                'soft_deadline' => $data['soft_deadline'],
                'area_id' => $data['area_id'],
                'first_step' => $data['first_step'],
            ]);

            if ($moved) {
                $last = Task::forUser($user)->inList($data['list'])->max('sort_order');
                $task->update([
                    'sort_order' => $last === null ? 0 : $last + 1,
                    'board_position' => BoardOrder::nextPosition($user->id),
                ]);
            }

            return $task->fresh();
        });

        return ['task' => $task, 'before' => $before, 'moved' => $moved];
    }

    /**
     * To another list, nothing else changes.
     *
     * @return array{task: Task, before: array<string, mixed>, moved: bool}
     */
    public function move(User $user, int $taskId, string $list): array
    {
        return $this->update($user, $taskId, ['list' => $list]);
    }

    /**
     * Soft delete. Returns the task as it was, for the toast and its undo.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws ItemLocked when the task is the one of the running session
     */
    public function delete(User $user, int $taskId, ?CarbonImmutable $now = null): Task
    {
        $task = $this->find($user, $taskId);

        $session = app(FocusSession::class)->current($user, $now ?? CarbonImmutable::now());
        if ($session->phase !== Phase::Idle && $session->taskId === $task->id) {
            throw new ItemLocked;
        }

        $task->delete();

        return $task;
    }

    /** Take a deleted item back, exactly where it was (the toast's "Rückgängig"). */
    public function restore(User $user, int $taskId): Task
    {
        $task = Task::forUser($user)->active()->onlyTrashed()->findOrFail($taskId);
        $task->restore();

        return $task->fresh();
    }

    /** Put an edited or moved item back exactly as it was. */
    public function undo(User $user, int $taskId, array $before): Task
    {
        $task = $this->find($user, $taskId);
        $task->update(array_intersect_key($before, array_flip(self::SNAPSHOT)));

        return $task->fresh();
    }

    /**
     * The values the editor opens with (what the form shows for this task).
     *
     * @return array{title: string, notes: ?string, list: string, size: ?string, hard_deadline: ?string, soft_deadline: ?string, area_id: ?int, first_step: ?string}
     */
    public function values(Task $task): array
    {
        return [
            'title' => $task->title,
            'notes' => $task->notes,
            'list' => $task->list,
            'size' => $task->size?->value,
            'hard_deadline' => $task->hard_deadline?->toDateString(),
            'soft_deadline' => $task->soft_deadline?->toDateString(),
            'area_id' => $task->area_id,
            'first_step' => $task->first_step,
        ];
    }

    /**
     * The merged values with German messages per field.
     *
     * @return array{title: string, notes: ?string, list: string, size: ?string, hard_deadline: ?string, soft_deadline: ?string, area_id: ?int, first_step: ?string}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(User $user, Task $task, array $input): array
    {
        $merged = array_merge($this->values($task), array_intersect_key($input, $this->values($task)));
        $merged['title'] = trim((string) $merged['title']);
        foreach (['notes', 'first_step', 'size', 'hard_deadline', 'soft_deadline'] as $optional) {
            $merged[$optional] = is_string($merged[$optional] ?? null) && trim($merged[$optional]) === '' ? null : ($merged[$optional] ?? null);
        }
        $merged['area_id'] = $merged['area_id'] === '' ? null : $merged['area_id'];

        $today = $user->localToday()->toDateString();
        $stored = $this->values($task);
        $rules = [
            'title' => ['required', 'string', 'max:'.Capture::MAX_LENGTH],
            'notes' => ['nullable', 'string', 'max:5000'],
            'list' => ['required', Rule::in(Task::LISTS)],
            'size' => ['nullable', Rule::enum(Size::class)],
            'area_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')->where('user_id', $user->id)],
            'first_step' => ['nullable', 'string', 'max:255'],
        ];
        foreach (self::DATES as $date) {
            $rules[$date] = ['nullable', 'date_format:Y-m-d'];
            // An unchanged date may lie in the past (an overdue task is still editable).
            if ($merged[$date] !== $stored[$date]) {
                $rules[$date][] = 'after_or_equal:'.$today;
            }
        }

        return Validator::make($merged, $rules, [
            'title.required' => __('items.errors.title'),
            'title.max' => __('items.errors.title_max', ['max' => Capture::MAX_LENGTH]),
            'notes.max' => __('items.errors.notes', ['max' => 5000]),
            'list.required' => __('items.errors.list'),
            'list.in' => __('items.errors.list'),
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

    private function find(User $user, int $taskId): Task
    {
        return Task::forUser($user)->active()->findOrFail($taskId);
    }

    /** @return array<string, mixed> */
    private function snapshot(Task $task): array
    {
        $out = [];
        foreach (self::SNAPSHOT as $field) {
            $out[$field] = $task->getAttributes()[$field] ?? null;
        }

        return $out;
    }
}
