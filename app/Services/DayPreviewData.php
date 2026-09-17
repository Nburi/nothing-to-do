<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;

/**
 * Read-only aggregation for the Tagesüberblick (App\Livewire\DayPreview).
 *
 * Deliberately a new, standalone service rather than reusing TaskBoard's or
 * Schedule's own computed properties: those hang off each component's own
 * live state (current week, current list, current list-concept board
 * shape), while this reads "today" flat and once, the same simplification
 * TaskSuggestor/DayPlanner and the MCP server's get_board already make.
 *
 * Every read here is concept-agnostic (ignores `tasks.list`, matching
 * ListConcepts::for() being irrelevant to "what's flagged for today") and
 * module-aware (App\Services\AppModules is the single gate the rest of the
 * app already uses — a hidden module's tile simply isn't returned).
 */
class DayPreviewData
{
    /** How many schedule blocks the Liste card shows before collapsing to "+N weitere". */
    public const SCHEDULE_CAP = 4;

    /** How many rows a Fällig/Für-heute/Agenda tile shows before collapsing. */
    public const LIST_CAP = 3;

    /**
     * @return array{visible: bool, blocks: list<array{title: string, time: string, token: string}>, hasMore: bool, moreCount: int}
     */
    public static function schedule(User $user): array
    {
        if (! AppModules::isVisible($user, 'schedule')) {
            return ['visible' => false, 'blocks' => [], 'hasMore' => false, 'moreCount' => 0];
        }

        $events = ScheduleEvent::query()
            ->forUser($user)
            ->visible()
            ->forDay($user->localToday())
            ->ordered()
            ->get();

        $blocks = $events->take(self::SCHEDULE_CAP)->map(fn (ScheduleEvent $event) => [
            'title' => $event->displayTitle(),
            'time' => "{$event->start_time}–{$event->end_time}",
            'token' => $event->colorToken(),
        ])->all();

        return [
            'visible' => true,
            'blocks' => $blocks,
            'hasMore' => $events->count() > self::SCHEDULE_CAP,
            'moreCount' => max(0, $events->count() - self::SCHEDULE_CAP),
        ];
    }

    /**
     * Overdue + due-today + due-soon, merging Task deadlines with Agenda
     * homework/exams — the same two sources Schedule::deadlineItems() already
     * merges for the Zeitplan's all-day strip, simplified here to real dates
     * only (no preview-offset lookahead, that's a Zeitplan-specific concept).
     *
     * @return array{count: int, items: list<array{title: string, tag: string, bucket: string}>, hasMore: bool, moreCount: int}
     */
    public static function due(User $user): array
    {
        $today = $user->localToday();
        $soonCutoff = $today->copy()->addDays(Task::URGENCY_DAYS);
        $items = collect();

        Task::query()->forUser($user)->active()
            ->where(fn ($q) => $q->whereNotNull('deadline')->orWhereNotNull('due_date'))
            ->get()
            ->each(function (Task $task) use ($items, $soonCutoff, $today) {
                $date = $task->effectiveDate();

                if ($date === null || $date->greaterThan($soonCutoff)) {
                    return;
                }

                $items->push([
                    'title' => $task->title,
                    'tag' => $task->effectiveDateLabel(),
                    'bucket' => $task->isOverdue() ? 'overdue' : ($date->isSameDay($today) ? 'today' : 'soon'),
                    'sortDate' => $date,
                ]);
            });

        if (AppModules::isVisible($user, 'agenda')) {
            AgendaEntry::query()->visibleTo($user)->openFor($user)->get()
                ->each(function (AgendaEntry $entry) use ($items, $soonCutoff, $today) {
                    if ($entry->date->greaterThan($soonCutoff)) {
                        return;
                    }

                    $items->push([
                        'title' => $entry->title,
                        'tag' => $entry->dateLabel(),
                        'bucket' => $entry->isOverdue() ? 'overdue' : ($entry->date->isSameDay($today) ? 'today' : 'soon'),
                        'sortDate' => $entry->date,
                    ]);
                });
        }

        $bucketRank = ['overdue' => 0, 'today' => 1, 'soon' => 2];
        $sorted = $items->sort(function (array $a, array $b) use ($bucketRank) {
            return $bucketRank[$a['bucket']] <=> $bucketRank[$b['bucket']]
                ?: $a['sortDate']->timestamp <=> $b['sortDate']->timestamp;
        })->values();

        return [
            'count' => $sorted->count(),
            'items' => $sorted->take(self::LIST_CAP)
                ->map(fn (array $i) => ['title' => $i['title'], 'tag' => $i['tag'], 'bucket' => $i['bucket']])
                ->all(),
            'hasMore' => $sorted->count() > self::LIST_CAP,
            'moreCount' => max(0, $sorted->count() - self::LIST_CAP),
        ];
    }

    /**
     * @return array{count: int, items: list<array{title: string}>, hasMore: bool, moreCount: int}
     */
    public static function todayTasks(User $user): array
    {
        $tasks = Task::query()->forUser($user)->onBoard()->active()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();

        return [
            'count' => $tasks->count(),
            'items' => $tasks->take(self::LIST_CAP)->map(fn (Task $t) => ['title' => $t->title])->all(),
            'hasMore' => $tasks->count() > self::LIST_CAP,
            'moreCount' => max(0, $tasks->count() - self::LIST_CAP),
        ];
    }

    /**
     * Null (tile hidden entirely) when the module is off or nothing's due
     * soon — the same "zero footprint when empty" rule every other optional
     * dashboard card in this app already follows.
     *
     * @return array{items: list<array{title: string, subject: string, dateLabel: string}>, hasMore: bool, moreCount: int}|null
     */
    public static function agenda(User $user): ?array
    {
        if (! AppModules::isVisible($user, 'agenda')) {
            return null;
        }

        $entries = AgendaEntry::homeworkPreviewFor($user);

        if ($entries->isEmpty()) {
            return null;
        }

        return [
            'items' => $entries->take(self::LIST_CAP)->map(fn (AgendaEntry $e) => [
                'title' => $e->title,
                'subject' => $e->subject,
                'dateLabel' => $e->dateLabel(),
            ])->all(),
            'hasMore' => $entries->count() > self::LIST_CAP,
            'moreCount' => max(0, $entries->count() - self::LIST_CAP),
        ];
    }

    /**
     * Only offered when today has no is_today tasks — a deliberately simple
     * trigger (see the plan's GERATEN note: a real free-time-capacity check
     * would need the Planer's per-day capacity concept, more moving parts
     * than a first version needs) — and only when the crafts module is on
     * and at least one open idea exists.
     *
     * @return array{title: string, note: ?string}|null
     */
    public static function craftIdea(User $user, bool $todayIsEmpty): ?array
    {
        if (! $todayIsEmpty || ! AppModules::isVisible($user, 'crafts')) {
            return null;
        }

        $idea = $user->craftIdeas()->open()->inRandomOrder()->first();

        return $idea === null ? null : ['title' => $idea->title, 'note' => $idea->where_to_begin];
    }

    /** How many roadmap nodes (done + current + upcoming combined) the emergency card draws at most. */
    public const ROADMAP_CAP = 8;

    /**
     * Null when no emergency project is active. `nodes` is one ordered list —
     * real completed tasks (by completed_at, so a genuine sequence, not an
     * anonymous count) followed by the real remaining active tasks — each
     * tagged `done`/`current`/`upcoming`. A project with more history than
     * ROADMAP_CAP allows collapses its oldest done nodes behind one leading
     * `overflow` node rather than fabricating or dropping data silently.
     *
     * @return array{projectName: string, done: int, total: int, nodes: list<array{kind: string, title?: string, count?: int}>}|null
     */
    public static function emergency(User $user): ?array
    {
        if (! $user->isInEmergencyMode()) {
            return null;
        }

        /** @var Project|null $project */
        $project = $user->projects()->find($user->emergency_project_id);

        if ($project === null) {
            return null;
        }

        $doneTasks = $project->tasks()->where('is_completed', true)->orderBy('completed_at')->get();
        $active = $project->activeTasks;
        $doneCount = $doneTasks->count();
        $total = $doneCount + $active->count();

        $upcoming = $active->take(self::ROADMAP_CAP);
        $doneShown = max(0, min($doneCount, self::ROADMAP_CAP - $upcoming->count()));
        $doneOverflow = $doneCount - $doneShown;

        $nodes = collect();

        if ($doneOverflow > 0) {
            $nodes->push(['kind' => 'overflow', 'count' => $doneOverflow]);
        }

        foreach ($doneTasks->slice(-$doneShown) as $task) {
            $nodes->push(['kind' => 'done', 'title' => $task->title]);
        }

        foreach ($upcoming->values() as $i => $task) {
            $nodes->push(['kind' => $i === 0 ? 'current' : 'upcoming', 'title' => $task->title]);
        }

        return [
            'projectName' => $project->name,
            'done' => $doneCount,
            'total' => $total,
            'nodes' => $nodes->all(),
        ];
    }

    /** @return array{today: int, goal: int} */
    public static function goal(User $user): array
    {
        return [
            'today' => ProgressStats::todayCount($user),
            'goal' => $user->dailyTaskGoal(),
        ];
    }

    /**
     * A short, live summary for the morning push notification (see
     * App\Console\Commands\SendDayPreviewNotifications) — never a static
     * config string, since "how many tasks/terms today" is the entire point.
     * Reuses the exact same reads the page itself renders from, so the
     * notification body can never drift from what opening the page shows.
     */
    public static function notificationSummary(User $user): string
    {
        $today = self::todayTasks($user);
        $schedule = self::schedule($user);
        $due = self::due($user);

        $parts = [];

        if ($today['count'] > 0) {
            $parts[] = $today['count'] === 1 ? '1 Aufgabe für heute' : "{$today['count']} Aufgaben für heute";
        }

        if ($schedule['visible']) {
            $blockCount = count($schedule['blocks']) + $schedule['moreCount'];

            if ($blockCount > 0) {
                $parts[] = $blockCount === 1 ? '1 Termin' : "{$blockCount} Termine";
            }
        }

        if ($due['count'] > 0) {
            $parts[] = $due['count'] === 1 ? '1 Aufgabe fällig' : "{$due['count']} Aufgaben fällig";
        }

        if ($parts === []) {
            return 'Nichts Dringendes — ein ruhiger Tag.';
        }

        return implode(', ', $parts).'.';
    }
}
