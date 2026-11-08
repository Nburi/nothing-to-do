<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Models\User;
use App\Planning\Crunch\SlipAction;
use App\Planning\Crunch\SoftSlip;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Refactor step 1.4: applies the soft-slip rule (G2, see SoftSlip) to a
 * user's open To-dos and Tasks. Idempotent: a task handled today has a soft
 * date in the future or has left the work lists, so a second run changes
 * nothing. Writes through Eloquent, so the old due_date column stays mirrored.
 */
final class SoftSlips
{
    /** @return array{rolled:int, later:int, dropped:int} */
    public function apply(User $user, ?CarbonImmutable $now = null): array
    {
        $today = UserClock::now($user, $now)->toDateString();
        $counts = ['rolled' => 0, 'later' => 0, 'dropped' => 0];

        $tasks = Task::forUser($user)->active()->whereIn('list', Task::WORK_LISTS)
            ->where(fn ($q) => $q->whereDate('soft_deadline', '<', $today)->orWhere(fn ($q) => $q->whereNull('soft_deadline')->whereDate('due_date', '<', $today)))
            ->get();

        foreach ($tasks as $task) {
            $decision = SoftSlip::decide($task->softDeadline()?->toDateString(), (int) $task->soft_slips, $task->hardDeadline() !== null, $today);

            match ($decision->action) {
                SlipAction::RollOver => $task->update(['soft_deadline' => $decision->newSoftDeadline, 'soft_slips' => 1]),
                SlipAction::ToLater => $task->update(['list' => Task::LATER, 'is_today' => false, 'today_date' => null, 'soft_slips' => 2]),
                SlipAction::DropSoftDate => $task->update(['soft_deadline' => null, 'soft_slips' => 2]),
                SlipAction::None => null,
            };

            $key = match ($decision->action) {
                SlipAction::RollOver => 'rolled',
                SlipAction::ToLater => 'later',
                SlipAction::DropSoftDate => 'dropped',
                SlipAction::None => null,
            };
            if ($key !== null) {
                $counts[$key]++;
            }
        }

        return $counts;
    }
}
