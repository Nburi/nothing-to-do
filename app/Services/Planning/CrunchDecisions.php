<?php

namespace App\Services\Planning;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Support\TaskMutator;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The crunch decisions (R5). Step 1.4 built drop and shrink; step 4.2 adds the
 * rest the crunch screen offers, and their undo (CONVENTIONS 7: undoable instead
 * of confirmed).
 *
 * - drop: the task moves to Later instead of being deleted (decided
 *   2026-11-08). Nothing is lost; it comes back as a free-time idea.
 * - shrink: one size smaller (Block → Long → Short → Quick).
 * - moveSoft: a soft deadline one week later, in one tap (soft slips first).
 *   Past the hard deadline it is dropped instead: the hard date binds anyway.
 * - moveHard: a hard deadline only to a later date the user chose, never
 *   silently and never by a rule (Niels 2026-11-17).
 * - addTime ("Mehr Zeit", Niels' pick 2026-11-17): a one-off pomodoro block,
 *   "this week only", through WeekPlanEditor. Undo removes exactly that block.
 * - restore: puts back the fields an undo remembered (whitelisted, validated).
 */
final class CrunchDecisions
{
    private const SMALLER = ['block' => 'long', 'long' => 'short', 'short' => 'quick'];

    /** A soft deadline moves this many days when the user lets it slip. Same as the slip rule (G2). */
    public const SOFT_MOVE_DAYS = 7;

    /** "Mehr Zeit" adds a block within this many days from today. */
    public const MORE_TIME_DAYS = 14;

    /** What an undo may put back. */
    private const RESTORABLE = ['list', 'size', 'hard_deadline', 'soft_deadline', 'soft_slips'];

    public function __construct(private readonly WeekPlanEditor $editor = new WeekPlanEditor) {}

    public function drop(User $user, int $taskId): Task
    {
        $task = $this->ownOpenTask($user, $taskId);

        return TaskMutator::applyUpdate($task, $user, ['list' => Task::LATER]);
    }

    public function shrink(User $user, int $taskId): Task
    {
        $task = $this->ownOpenTask($user, $taskId);
        $smaller = self::smaller($task);

        if ($smaller !== null) {
            $task->update(['size' => $smaller]);
        }

        return $task;
    }

    /** The next size down, or null when the task is already Quick. */
    public static function smaller(Task $task): ?Size
    {
        $next = self::SMALLER[$task->effectiveSize()->value] ?? null;

        return $next === null ? null : Size::from($next);
    }

    /**
     * Where moveSoft() puts the soft deadline: one week later, at least tomorrow. Null when the
     * task has no soft deadline that binds (no soft date, or the hard one comes first).
     */
    public static function softMoveTarget(Task $task, string $today): ?string
    {
        $soft = $task->softDeadline()?->toDateString();
        $hard = $task->hardDeadline()?->toDateString();
        if ($soft === null || ($hard !== null && $hard <= $soft)) {
            return null;
        }

        $tomorrow = CarbonImmutable::parse($today)->addDay()->toDateString();

        return max(CarbonImmutable::parse($soft)->addDays(self::SOFT_MOVE_DAYS)->toDateString(), $tomorrow);
    }

    public function moveSoft(User $user, int $taskId, ?CarbonImmutable $now = null): Task
    {
        $task = $this->ownOpenTask($user, $taskId);
        $target = self::softMoveTarget($task, UserClock::now($user, $now)->toDateString());

        if ($target === null) {
            throw ValidationException::withMessages(['task' => __('shell.crunch.errors.not_soft')]);
        }

        $hard = $task->hardDeadline()?->toDateString();
        $task->update(['soft_deadline' => $hard !== null && $target >= $hard ? null : $target]);

        return $task;
    }

    public function moveHard(User $user, int $taskId, string $date, ?CarbonImmutable $now = null): Task
    {
        $task = $this->ownOpenTask($user, $taskId);
        $current = $task->hardDeadline()?->toDateString();
        $today = UserClock::now($user, $now)->toDateString();

        if ($current === null) {
            throw ValidationException::withMessages(['moveDate' => __('shell.crunch.errors.not_hard')]);
        }

        $after = max($current, $today);
        Validator::make(['moveDate' => $date], [
            'moveDate' => ['required', 'date_format:Y-m-d', 'after:'.$after, 'before:'.CarbonImmutable::parse($today)->addYears(2)->toDateString()],
        ], [
            'moveDate.required' => __('shell.crunch.errors.date_required'),
            'moveDate.date_format' => __('shell.crunch.errors.date_required'),
            'moveDate.after' => __('shell.crunch.errors.date_later', ['date' => \App\Support\Dates::full($after)]),
            'moveDate.before' => __('shell.crunch.errors.date_far'),
        ])->validate();

        $task->update(['hard_deadline' => $date]);

        return $task;
    }

    public function addTime(User $user, string $date, string $start, string $end, ?int $categoryId, ?CarbonImmutable $now = null): ScheduleEvent
    {
        $today = UserClock::now($user, $now)->toDateString();
        $last = CarbonImmutable::parse($today)->addDays(self::MORE_TIME_DAYS - 1)->toDateString();
        $pomodoro = EventCategory::forUser($user)->where('pomodoro_enabled', true)->pluck('id')->all();

        Validator::make(['addDate' => $date, 'addCategory' => $categoryId], [
            'addDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before_or_equal:'.$last],
            'addCategory' => ['required', Rule::in($pomodoro)],
        ], [
            'addDate.*' => __('shell.crunch.errors.add_date'),
            'addCategory.*' => __('shell.crunch.errors.add_category'),
        ])->validate();

        try {
            return $this->editor->addOnce($user, $date, $start, $end, $categoryId);
        } catch (ValidationException $e) {
            // The editor validates the times; say it in the screen's own field names.
            throw ValidationException::withMessages(['addEnd' => __('shell.crunch.errors.add_time')]);
        }
    }

    /** Undo of addTime(): removes exactly that one-off block. */
    public function removeTime(User $user, int $eventId): void
    {
        $user->scheduleEvents()->whereKey($eventId)->whereNull('template_id')->firstOrFail()->delete();
    }

    /**
     * Undo of drop / shrink / move: puts back what the screen remembered before the decision.
     *
     * @param  array<string, mixed>  $before
     */
    public function restore(User $user, int $taskId, array $before): Task
    {
        $task = $this->ownOpenTask($user, $taskId);
        $before = array_intersect_key($before, array_flip(self::RESTORABLE));

        Validator::make($before, [
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
            'hard_deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'soft_deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'soft_slips' => ['sometimes', 'integer', 'between:0,2'],
        ])->validate();

        if (array_key_exists('list', $before)) {
            $task = TaskMutator::applyUpdate($task, $user, ['list' => $before['list']]);
            unset($before['list']);
        }
        if ($before !== []) {
            $task->update($before);
        }

        return $task;
    }

    /** The fields restore() can put back, as they are now. */
    public static function snapshot(Task $task): array
    {
        return [
            'list' => $task->list,
            'size' => $task->size?->value,
            'hard_deadline' => $task->hardDeadline()?->toDateString(),
            'soft_deadline' => $task->softDeadline()?->toDateString(),
            'soft_slips' => (int) $task->soft_slips,
        ];
    }

    private function ownOpenTask(User $user, int $taskId): Task
    {
        return Task::forUser($user)->active()->findOrFail($taskId);
    }
}
