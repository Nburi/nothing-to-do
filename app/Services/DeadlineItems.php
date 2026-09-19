<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "all-day strip" items — Task deadlines/Wunschtermine, Agenda homework/exams and Planer
 * placements — for a date range, grouped by Y-m-d. None of these carry a time, so they sit in a row
 * above an hour grid: the Zeitplan's week/day view and step 3 of the Vorbereitung both render them
 * through partials/schedule-deadline-strip.blade.php.
 *
 * Each source item contributes one entry on its actual date, plus (only for a *hard* date — a task
 * deadline, or any Agenda entry, never a soft Wunschtermin) a second, `isPreview` entry
 * `deadline_preview_days` earlier, when that setting is enabled — see Settings::saveDeadlinePreview().
 * Completed/done items are excluded entirely (Task::active(), AgendaEntry::openFor()), consistent with
 * how Board and Agenda hide finished work.
 */
class DeadlineItems
{
    /** @return Collection<string, Collection<int, array<string, mixed>>> keyed by Y-m-d */
    public static function forRange(User $user, Carbon $start, Carbon $end): Collection
    {
        $previewEnabled = (bool) $user->deadline_preview_enabled;
        $previewDays = max(0, (int) $user->deadline_preview_days);

        $items = collect();

        Task::forUser($user)->active()
            ->where(fn ($q) => $q->whereNotNull('deadline')->orWhereNotNull('due_date'))
            ->get()
            ->each(function (Task $task) use ($items, $previewEnabled, $previewDays) {
                $date = $task->effectiveDate();

                if ($date === null) {
                    return;
                }

                $isHard = $task->effectiveIsHard();
                $base = [
                    'kind' => 'task',
                    'subtype' => $isHard ? 'deadline' : 'due',
                    'id' => $task->id,
                    'title' => $task->title,
                ];

                $items->push($base + ['date' => $date->copy(), 'isPreview' => false]);

                if ($isHard && $previewEnabled && $previewDays > 0) {
                    $items->push($base + [
                        'date' => $date->copy()->subDays($previewDays),
                        'isPreview' => true,
                        'daysUntil' => $previewDays,
                    ]);
                }
            });

        AgendaEntry::visibleTo($user)->openFor($user)->get()
            ->each(function (AgendaEntry $entry) use ($items, $previewEnabled, $previewDays) {
                $base = [
                    'kind' => 'agenda',
                    'subtype' => $entry->type,
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'subject' => $entry->subject,
                ];

                $items->push($base + ['date' => $entry->date->copy(), 'isPreview' => false]);

                if ($previewEnabled && $previewDays > 0) {
                    $items->push($base + [
                        'date' => $entry->date->copy()->subDays($previewDays),
                        'isPreview' => true,
                        'daysUntil' => $previewDays,
                    ]);
                }
            });

        // Planer placements — "you scheduled work on this task for this day", not a deadline of
        // any kind. Pure visibility, deliberately never a preview copy (there's no advance-warning
        // concept for a day you picked yourself the way there is for a deadline). A task can show
        // both this and its own deadline chip on two different dates at once — "fällig Freitag,
        // aber ich hab mir Mittwoch dafür reserviert" is genuinely useful, not a duplicate.
        TaskDayPlan::query()
            ->whereHas('task', fn ($q) => $q->forUser($user)->active())
            ->with('task')
            ->get()
            ->each(fn (TaskDayPlan $plan) => $items->push([
                'kind' => 'task',
                'subtype' => 'planned',
                'id' => $plan->task_id,
                'title' => $plan->task->title,
                'date' => $plan->planned_date->copy(),
                'isPreview' => false,
            ]));

        return $items
            ->filter(fn (array $item) => $item['date']->between($start->copy()->startOfDay(), $end->copy()->endOfDay()))
            ->sortBy('title')
            ->sortBy(fn (array $item) => $item['isPreview'] ? 1 : 0)
            ->groupBy(fn (array $item) => $item['date']->toDateString());
    }
}
