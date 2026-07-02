<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesSchedule;
use App\Models\ScheduleEvent;
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
        $this->weekStart = Carbon::today()->startOfWeek()->toDateString();
        $this->focusedDate = Carbon::today()->toDateString();
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
        $this->focusDate(Carbon::today());
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
