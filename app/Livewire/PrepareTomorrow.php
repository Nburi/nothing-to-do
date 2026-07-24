<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesSchedule;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\ScheduleEvent;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The end-of-day ritual that replaced "Aufräumen": three steps run back to
 * back — empty the inbox, flag what's on for tomorrow, then lay out
 * tomorrow's time blocks. Steps 1–2 keep Cleanup's exact swipe-stack
 * mechanics (ordering/phase/"später" still live client-side, see the
 * `prepare` Alpine store in app.js); step 3 is the existing Zeitplan
 * timeline verbatim (ManagesSchedule is fully date-agnostic — every mutation
 * already takes the target date as a parameter), just pointed at tomorrow
 * instead of today.
 */
#[Layout('layouts.app')]
class PrepareTomorrow extends Component
{
    use ManagesTasks;
    use ManagesSchedule;

    /** The visible window of the mini timeline (minutes from midnight) — same span as Zeitplan. */
    public const DAY_START = 6 * 60;

    public const DAY_END = 23 * 60;

    #[Computed]
    public function tomorrow(): Carbon
    {
        return auth()->user()->localToday()->addDay();
    }

    #[Computed]
    public function inboxQueue(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->inList('inbox')
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function reviewQueue(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->whereIn('list', Task::TODAY_LISTS)
            ->boardOrdered()
            ->get();
    }

    /** Tasks already flagged for tomorrow's focus — the reminder tray on the schedule step. */
    #[Computed]
    public function tomorrowFlagged(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();
    }

    /** Tomorrow's timeline — recurring series materialised on read, same as Schedule::render(). */
    #[Computed]
    public function tomorrowEvents(): Collection
    {
        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forDay($this->tomorrow)
            ->ordered()
            ->with('category')
            ->get();
    }

    /** Inbox triage: file a task into To-Dos or Tasks. */
    public function assignList(int $id, string $list): void
    {
        if (! in_array($list, ['todos', 'tasks'], true)) {
            return;
        }

        $this->userTask($id)->update(['list' => $list]);
    }

    /** Review pass: flag a task for tomorrow's focus. */
    public function markToday(int $id): void
    {
        $task = $this->userTask($id);

        if (! in_array($task->list, Task::TODAY_LISTS, true)) {
            return;
        }

        $task->update(['is_today' => true]);
    }

    public function render()
    {
        ScheduleEvent::materializeRange(auth()->user(), $this->tomorrow, $this->tomorrow->copy());

        return view('livewire.prepare-tomorrow', [
            'dayStart' => self::DAY_START,
            'dayEnd' => self::DAY_END,
        ]);
    }
}
