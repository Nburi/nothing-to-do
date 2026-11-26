<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Models\User;
use App\Support\TaskMutator;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Step d-bis-wann (decision d-bis-wann, step 4.6): marking a task „Wichtig" that has no date offers one quiet,
 * optional question, „Bis wann?": today, tomorrow or this week. The answer is a *soft* deadline (a hard
 * one is a commitment the user has to choose on purpose).
 *
 * - Asked once per task, ever. `tasks.deadline_asked_at` is stamped the moment the question is shown, so
 *   ignoring it, answering it, unmarking and marking again, or marking on another device never asks twice.
 * - Only for open tasks outside Later (parked on purpose) that have no date of their own: no hard deadline,
 *   no soft deadline and no stage target date (planningSoftDeadline). Nothing is asked of a task that is dated.
 * - "This week" is the Sunday of the user's week; it is left out when it would be the same day as tomorrow
 *   (Saturday) or today (Sunday), so no two buttons ever say the same date.
 * - The answer goes through TaskMutator like every other write; undo puts the date back to none, but only
 *   while it still is the date this answer set.
 */
final class DeadlineOffer
{
    /** Called after the user marked $task „Wichtig". True when the question should be shown (and is now stamped). */
    public function open(Task $task, ?CarbonImmutable $now = null): bool
    {
        if (! $this->eligible($task)) {
            return false;
        }

        // A query-builder write: nothing else about the task changes, not even its updated_at.
        Task::query()->whereKey($task->id)->toBase()->update(['deadline_asked_at' => $now ?? CarbonImmutable::now()]);

        return true;
    }

    /**
     * The answers on offer, choice => date (Y-m-d, the user's local days).
     *
     * @return array<string, string>
     */
    public function choices(User $user, ?CarbonImmutable $now = null): array
    {
        $today = UserClock::now($user, $now)->startOfDay();
        $tomorrow = $today->addDay();
        $weekEnd = $today->addDays(7 - $today->isoWeekday());

        $choices = ['today' => $today->toDateString(), 'tomorrow' => $tomorrow->toDateString()];
        if ($weekEnd->greaterThan($tomorrow)) {
            $choices['week'] = $weekEnd->toDateString();
        }

        return $choices;
    }

    /** Write the soft deadline. False when the task got a date of its own in the meantime: it is left as it is. */
    public function choose(User $user, int $taskId, string $date): bool
    {
        $task = Task::forUser($user)->active()->findOrFail($taskId);

        if (! $this->undated($task)) {
            return false;
        }

        TaskMutator::applyRefactorFields($task, ['soft_deadline' => $date]);

        return true;
    }

    /** Take the answer back. False when the date was changed since: then it is not ours to remove. */
    public function undo(User $user, int $taskId, string $date): bool
    {
        $task = Task::forUser($user)->active()->findOrFail($taskId);

        if ($task->soft_deadline?->toDateString() !== $date) {
            return false;
        }

        TaskMutator::applyRefactorFields($task, ['soft_deadline' => null]);

        return true;
    }

    private function eligible(Task $task): bool
    {
        return ! $task->is_completed
            && $task->list !== Task::LATER
            && $task->deadline_asked_at === null
            && $this->undated($task);
    }

    private function undated(Task $task): bool
    {
        return $task->hardDeadline() === null && $task->planningSoftDeadline() === null;
    }
}
