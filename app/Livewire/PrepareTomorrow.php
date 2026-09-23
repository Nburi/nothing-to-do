<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesDeadlineItems;
use App\Livewire\Concerns\ManagesSchedule;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\DayWindow;
use App\Services\DeadlineItems;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The "Vorbereitung" ritual that replaced "Aufräumen": three steps run back
 * to back — empty the inbox, flag what's on for the target day, then lay out
 * that day's time blocks. Which day is "the target day" is a per-user setting
 * (`prepare_time_of_day`, Settings' Vorbereitung card): "evening" (default)
 * targets tomorrow, "morning" targets today — see `User::prepareTargetDate()`.
 * Steps 1–2 keep Cleanup's exact swipe-stack mechanics (ordering/phase/
 * "später" still live client-side, see the `prepare` Alpine store in app.js);
 * step 3 is the existing Zeitplan timeline verbatim (ManagesSchedule is fully
 * date-agnostic — every mutation already takes the target date as a
 * parameter), just pointed at `targetDate` instead of always tomorrow.
 */
#[Layout('layouts.app')]
#[Title('Vorbereiten')]
class PrepareTomorrow extends Component
{
    use ManagesTasks;
    use ManagesDeadlineItems;
    use ManagesSchedule;

    #[Computed]
    public function targetDate(): Carbon
    {
        return auth()->user()->prepareTargetDate();
    }

    /** 'heute' or 'morgen' — drives every "für ..." label in the view. */
    #[Computed]
    public function targetWord(): string
    {
        return auth()->user()->prepare_time_of_day === 'morning' ? 'heute' : 'morgen';
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

    /** Tasks already flagged for the target day's focus — the reminder tray on the schedule step. */
    #[Computed]
    public function targetFlagged(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->where('is_today', true)
            ->boardOrdered()
            ->get();
    }

    /** The target day's timeline — recurring series materialised on read, same as Schedule::render(). */
    #[Computed]
    public function targetEvents(): Collection
    {
        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forDay($this->targetDate)
            ->ordered()
            ->with('category')
            ->get();
    }

    /**
     * The target day's all-day chips — deadlines, Wunschtermine, homework/exams, Planer placements and
     * advance previews of later deadlines — the same strip the Zeitplan shows above its hour grid, so
     * planning the day's blocks happens with everything that's due in view.
     */
    #[Computed]
    public function targetDeadlineItems(): Collection
    {
        return DeadlineItems::forRange(auth()->user(), $this->targetDate, $this->targetDate)
            ->get($this->targetDate->toDateString(), collect());
    }

    /** Inbox triage: file a task into To-Dos or Tasks. */
    public function assignList(int $id, string $list): void
    {
        if (! in_array($list, ['todos', 'tasks'], true)) {
            return;
        }

        $this->userTask($id)->update(['list' => $list]);
    }

    /** Review pass: flag a task for the target day's focus. */
    public function markToday(int $id): void
    {
        $task = $this->userTask($id);

        if (! in_array($task->list, Task::TODAY_LISTS, true)) {
            return;
        }

        $task->update([
            'is_today' => true,
            'today_date' => $task->todayDateFor(true, $this->targetDate),
        ]);
    }

    /**
     * Stamps today as "prepared" — called once, when the wizard actually
     * reaches the done screen (both "Später planen" and "Fertig" fire this),
     * never just from visiting the page. This is the one signal the
     * automatic-reminder system (in-app banner + push fallback) reads to know
     * whether today's ritual still needs doing.
     */
    public function finish(): void
    {
        auth()->user()->update(['prepared_on' => auth()->user()->localToday()->toDateString()]);
    }

    public function render()
    {
        ScheduleEvent::materializeRange(auth()->user(), $this->targetDate, $this->targetDate->copy());

        $setting = DayWindow::settingForDate(auth()->user(), $this->targetDate);

        return view('livewire.prepare-tomorrow', [
            // The target day's own Tagesrahmen, expanded around whatever is
            // already on it — step 3 is the Zeitplan's timeline verbatim, so it
            // has to agree with the Zeitplan about how long that day is.
            'frame' => DayWindow::frame(
                $setting['start'],
                $setting['end'],
                DayWindow::rangesFrom($this->targetEvents),
            ),
        ]);
    }
}
