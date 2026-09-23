<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesDeadlineItems;
use App\Livewire\Concerns\ManagesSchedule;
use App\Models\SchedulePause;
use App\Models\ScheduleEvent;
use App\Services\DeadlineItems;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Zeitplan')]
class Schedule extends Component
{
    use ManagesDeadlineItems;
    use ManagesSchedule;

    /** The visible window of a day on the timeline (minutes from midnight). */
    public const DAY_START = 6 * 60;   // 06:00

    public const DAY_END = 23 * 60;    // 23:00

    /** Monday of the visible week. */
    public string $weekStart = '';

    /** The single day shown on mobile. */
    public string $focusedDate = '';

    /**
     * The event the "Zeitplan" header badge (HeaderBadges::scheduleBadge())
     * pointed at, if the page was reached via its ?event= link — the badge
     * doesn't just navigate here, it proves it by landing you on the exact
     * block it was showing, briefly highlighted (see partials/schedule-event.blade.php
     * and the .badge-jump-highlight keyframe in app.css).
     */
    public ?int $highlightEventId = null;

    public function mount(): void
    {
        $today = auth()->user()->localToday();
        $this->weekStart = $today->copy()->startOfWeek()->toDateString();
        $this->focusedDate = $today->toDateString();

        $eventId = request()->query('event');

        if ($eventId !== null && ctype_digit((string) $eventId)) {
            // Best-effort only: a stale/foreign/deleted id just means no
            // highlight, never a broken page load.
            $event = ScheduleEvent::forUser(auth()->user())->visible()->find((int) $eventId);

            if ($event !== null) {
                $this->highlightEventId = $event->id;
                $this->focusDate($event->date);
            }
        }
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
        $weekStart = Carbon::parse($this->weekStart)->startOfDay();

        return DeadlineItems::forRange(auth()->user(), $weekStart, $weekStart->copy()->endOfWeek());
    }

    /** Deadline items for the mobile single-day view. */
    #[Computed]
    public function focusedDeadlineItems(): Collection
    {
        return $this->deadlineItems->get($this->focusedDate, collect());
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
