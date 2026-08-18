<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesSchedule;
use App\Models\AgendaEntry;
use App\Models\SchedulePause;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\ProgressStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Schedule extends Component
{
    use ManagesSchedule;

    /** The visible window of a day on the timeline (minutes from midnight). */
    public const DAY_START = 6 * 60;   // 06:00

    public const DAY_END = 23 * 60;    // 23:00

    /** Monday of the visible week. */
    public string $weekStart = '';

    /** The single day shown on mobile. */
    public string $focusedDate = '';

    public function mount(): void
    {
        $today = auth()->user()->localToday();
        $this->weekStart = $today->copy()->startOfWeek()->toDateString();
        $this->focusedDate = $today->toDateString();
    }

    /** The seven Carbon dates of the visible week. */
    #[Computed]
    public function weekDays(): array
    {
        $start = Carbon::parse($this->weekStart);

        return collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i))->all();
    }

    /** Visible events for the week, grouped by Y-m-d and ordered by start time. */
    #[Computed]
    public function events(): Collection
    {
        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->endOfWeek();

        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forRange($start, $end)
            ->ordered()
            ->get()
            ->groupBy(fn (ScheduleEvent $e) => $e->date->toDateString());
    }

    /** Events for the mobile single-day view. */
    #[Computed]
    public function focusedEvents(): Collection
    {
        return $this->events->get($this->focusedDate, collect());
    }

    /** Paused (Wochenplan "Ferien") dates within the visible week, as Y-m-d strings. */
    #[Computed]
    public function pausedDates(): array
    {
        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->endOfWeek();

        return SchedulePause::forUser(auth()->user())
            ->forRange($start, $end)
            ->get()
            ->map(fn (SchedulePause $pause) => $pause->date->toDateString())
            ->all();
    }

    /**
     * Task deadlines/Wunschtermine and Agenda homework/exams for the visible week, grouped by
     * Y-m-d — the "all-day" strip above the hour grid, since none of these carry a time. Each
     * source item contributes one entry on its actual date, plus (only for a *hard* date — a task
     * deadline, or any Agenda entry, never a soft Wunschtermin) a second, `isPreview` entry
     * `deadline_preview_days` earlier, when that setting is enabled — see
     * Settings::saveDeadlinePreview(). Completed/done items are excluded entirely (Task::active(),
     * AgendaEntry::openFor()), consistent with how Board and Agenda hide finished work.
     */
    #[Computed]
    public function deadlineItems(): Collection
    {
        $user = auth()->user();
        $weekStart = Carbon::parse($this->weekStart)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek();
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

        return $items
            ->filter(fn (array $item) => $item['date']->between($weekStart, $weekEnd))
            ->sortBy('title')
            ->sortBy(fn (array $item) => $item['isPreview'] ? 1 : 0)
            ->groupBy(fn (array $item) => $item['date']->toDateString());
    }

    /** Deadline items for the mobile single-day view. */
    #[Computed]
    public function focusedDeadlineItems(): Collection
    {
        return $this->deadlineItems->get($this->focusedDate, collect());
    }

    /**
     * Ticks a task off straight from the Zeitplan strip. Deliberately duplicates
     * ManagesTasks::toggleComplete() rather than pulling in the whole trait (its edit-sheet state
     * isn't needed here) — the same small-duplication call already made between
     * Task::effectiveDateLabel() and AgendaEntry::dateLabel().
     */
    public function toggleDeadlineTaskDone(int $id): void
    {
        $task = auth()->user()->tasks()->findOrFail($id);
        $done = ! $task->is_completed;
        $user = auth()->user();

        $before = $done ? ProgressStats::todayCount($user) : null;

        $task->update([
            'is_completed' => $done,
            'completed_at' => $done ? now() : null,
        ]);

        if ($done && ($celebration = ProgressStats::celebrationFor($user, $before)) !== null) {
            $this->dispatch('celebrate', kind: $celebration['kind'], label: $celebration['label']);
        }
    }

    /** Ticks an Agenda entry off for this person only — see AgendaEntry::toggleDoneFor(). */
    public function toggleDeadlineAgendaDone(int $id): void
    {
        AgendaEntry::visibleTo(auth()->user())->findOrFail($id)->toggleDoneFor(auth()->user());
    }

    public function prevWeek(): void
    {
        $this->shiftWeek(-7);
    }

    public function nextWeek(): void
    {
        $this->shiftWeek(7);
    }

    private function shiftWeek(int $days): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addDays($days)->toDateString();
        $this->focusedDate = Carbon::parse($this->focusedDate)->addDays($days)->toDateString();
    }

    public function prevDay(): void
    {
        $this->focusDate(Carbon::parse($this->focusedDate)->subDay());
    }

    public function nextDay(): void
    {
        $this->focusDate(Carbon::parse($this->focusedDate)->addDay());
    }

    public function focusDate(Carbon|string $date): void
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $this->focusedDate = $date->toDateString();
        $this->weekStart = $date->copy()->startOfWeek()->toDateString();
    }

    public function goToday(): void
    {
        $this->focusDate(auth()->user()->localToday());
    }

    public function render()
    {
        // Recurring series exist on demand: fill the visible week before reading.
        ScheduleEvent::materializeRange(
            auth()->user(),
            Carbon::parse($this->weekStart),
            Carbon::parse($this->weekStart)->endOfWeek(),
        );

        return view('livewire.schedule', [
            'dayStart' => self::DAY_START,
            'dayEnd' => self::DAY_END,
        ]);
    }
}
