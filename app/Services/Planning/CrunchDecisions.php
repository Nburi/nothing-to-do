<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Models\User;
use App\Support\TaskMutator;

/**
 * Refactor step 1.4: the crunch decisions that are pure data changes (R5).
 * The crunch screen that offers them is step 4.2.
 *
 * - drop: the task moves to Later instead of being deleted (decided
 *   2026-11-08). Nothing is lost; it comes back as a free-time idea.
 * - shrink: one size smaller (Block → Long → Short → Quick).
 *
 * "Move" and "ask for more time" are edits of the dates the user makes
 * themself; they need no service of their own.
 */
final class CrunchDecisions
{
    private const SMALLER = ['block' => 'long', 'long' => 'short', 'short' => 'quick'];

    public function drop(User $user, int $taskId): Task
    {
        $task = $this->ownOpenTask($user, $taskId);

        return TaskMutator::applyUpdate($task, $user, ['list' => Task::LATER]);
    }

    public function shrink(User $user, int $taskId): Task
    {
        $task = $this->ownOpenTask($user, $taskId);
        $smaller = self::SMALLER[$task->effectiveSize()->value] ?? null;

        if ($smaller !== null) {
            $task->update(['size' => $smaller]);
        }

        return $task;
    }

    private function ownOpenTask(User $user, int $taskId): Task
    {
        return Task::forUser($user)->active()->findOrFail($taskId);
    }
}
