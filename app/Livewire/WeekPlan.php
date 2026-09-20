<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesDayBounds;
use App\Models\EventTemplate;
use App\Models\SchedulePause;
use App\Models\ScheduleEvent;
use App\Services\DayWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The week plan: an abstract Mon–Sun canvas (no calendar dates) for editing
 * the recurring EventTemplate rows that the Zeitplan already auto-fills onto
 * every real week via ScheduleEvent::materializeRange(). This page is a new
 * front end for that existing mechanism, not a new materialisation path —
 * editing here writes EventTemplate directly, the same rows the Zeitplan's
 * own "Wiederholen" checkbox already creates.
 */
#[Layout('layouts.app')]
class WeekPlan extends Component
{
    use ManagesDayBounds;

    /** Smallest block the grid will allow (minutes) — mirrors ManagesSchedule::MIN_EVENT. */
    public const MIN_EVENT = 10;

    /** Inline block form (shared between create and edit), keyed by weekday instead of date. */
    public bool $showEventForm = false;

    public ?int $editingEventId = null;

    /** 'appointment' (free-text Termin) or 'category' (linked to an EventCategory). */
    public string $eventKind = 'appointment';

    public string $eventTitle = '';

    public string $eventStart = '08:00';

    public string $eventEnd = '09:00';

    public string $eventColor = 'contour';

    /** Weg-/Pufferzeit in minutes, carried onto every occurrence this template materialises. */
    public int $eventBufferBefore = 0;

    public int $eventBufferAfter = 0;

    public ?int $eventCategoryId = null;

    /** ISO weekdays (1=Mon … 7=Sun) this block applies to — always visible, never optional here. */
    public array $eventDays = [];

    public bool $showPauseForm = false;

    public string $pauseFrom = '';

    public string $pauseTo = '';

    public string $pauseNote = '';

    protected function userTemplate(int $id): EventTemplate
    {
        return auth()->user()->eventTemplates()->findOrFail($id);
    }

    /** Every recurring template, bucketed by the ISO weekdays it occurs on. */
    #[Computed]
    public function templatesByWeekday(): array
    {
        $templates = auth()->user()->eventTemplates()->recurring()->ordered()->get();

        $buckets = [];
        for ($day = 1; $day <= 7; $day++) {
            $buckets[$day] = collect();
        }

        foreach ($templates as $template) {
            foreach ($template->recurrenceDays() as $day) {
                if (isset($buckets[$day])) {
                    $buckets[$day]->push($template);
                }
            }
        }

        return $buckets;
    }

    /**
     * Each weekday's own Tagesrahmen (its override, else the default), keyed
     * by ISO weekday. This is the page where "so sieht mein normaler Samstag
     * aus" is answered, so it is also where a weekday's frame is edited.
     *
     * @return array<int, array{start: int, end: int, source: string}>
     */
    #[Computed]
    public function weekdaySettings(): array
    {
        $user = auth()->user();
        $settings = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $settings[$weekday] = DayWindow::settingForWeekday($user, $weekday);
        }

        return $settings;
    }

    /**
     * One frame per weekday, for the mobile view — it shows a single weekday at
     * a time, so it is not bound by the shared scale the desktop grid needs
     * (seven columns, one hour gutter). Without this, setting one weekday
     * shorter changed nothing about the size of its blocks on the very page
     * where the setting was made.
     *
     * @return array<int, array{start: int, end: int, settingStart: int, settingEnd: int, expanded: bool}>
     */
    #[Computed]
    public function weekdayFrames(): array
    {
        $buckets = $this->templatesByWeekday;
        $frames = [];

        foreach ($this->weekdaySettings as $weekday => $setting) {
            $frames[$weekday] = DayWindow::frame(
                $setting['start'],
                $setting['end'],
                DayWindow::rangesFrom($buckets[$weekday] ?? collect()),
            );
        }

        return $frames;
    }

    /** Recomputed after a Tagesrahmen write, so the same request renders the new frame. */
    protected function afterDayBoundsChanged(): void
    {
        unset($this->weekdaySettings, $this->weekdayFrames);
    }

    /** The user's configured categories, for the block form's chip picker. */
    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->eventCategories()->ordered()->get();
    }

    /** Upcoming paused ranges, collapsed for display (see SchedulePause::collapseToRanges). */
    #[Computed]
    public function pausedRanges(): array
    {
        $today = auth()->user()->localToday();

        $pauses = SchedulePause::forUser(auth()->user())
            ->whereDate('date', '>=', $today->toDateString())
            ->ordered()
            ->get();

        return SchedulePause::collapseToRanges($pauses);
    }

    // ── Block form ───────────────────────────────────────────────────

    public function openEventForm(?int $weekday = null): void
    {
        $this->reset([
            'editingEventId', 'eventKind', 'eventTitle', 'eventColor', 'eventCategoryId', 'eventDays',
            'eventBufferBefore', 'eventBufferAfter',
        ]);
        $this->eventKind = 'appointment';
        $this->eventColor = 'contour';
        $this->eventStart = '08:00';
        $this->eventEnd = '09:00';
        $this->eventDays = $weekday !== null ? [$weekday] : [];
        $this->showEventForm = true;
    }

    public function startEditEvent(int $id): void
    {
        $template = $this->userTemplate($id);

        $this->editingEventId = $template->id;
        $this->eventKind = $template->category_id ? 'category' : 'appointment';
        $this->eventCategoryId = $template->category_id;
        $this->eventTitle = (string) $template->name;
        $this->eventStart = $template->default_start ?: '08:00';
        $this->eventEnd = ScheduleEvent::fromMinutes(ScheduleEvent::toMinutes($this->eventStart) + $template->duration);
        $this->eventBufferBefore = (int) $template->buffer_before;
        $this->eventBufferAfter = (int) $template->buffer_after;
        $this->eventColor = $template->colorToken();
        $this->eventDays = $template->recurrenceDays();
        $this->showEventForm = true;
    }

    public function saveEventForm(): void
    {
        $rules = [
            'eventKind' => ['required', 'in:appointment,category'],
            'eventStart' => ['required', 'date_format:H:i'],
            'eventEnd' => ['required', 'date_format:H:i', 'after:eventStart'],
            'eventBufferBefore' => ['integer', 'min:0', 'max:240'],
            'eventBufferAfter' => ['integer', 'min:0', 'max:240'],
            'eventDays' => ['required', 'array', 'min:1'],
            'eventDays.*' => ['integer', 'between:1,7'],
        ];

        if ($this->eventKind === 'category') {
            $rules['eventCategoryId'] = ['required', Rule::exists('event_categories', 'id')->where('user_id', auth()->id())];
        } else {
            $this->eventTitle = trim($this->eventTitle);
            $rules['eventTitle'] = ['required', 'string', 'max:255'];
            $rules['eventColor'] = ['required', Rule::in(ScheduleEvent::EVENT_COLORS)];
        }

        $data = $this->validate($rules);

        if ($this->eventKind === 'category') {
            $category = auth()->user()->eventCategories()->findOrFail($data['eventCategoryId']);
            $categoryId = $category->id;
            $title = $category->name;
            $color = $category->color;
        } else {
            $categoryId = null;
            $title = $data['eventTitle'];
            $color = $data['eventColor'];
        }

        $duration = ScheduleEvent::toMinutes($data['eventEnd']) - ScheduleEvent::toMinutes($data['eventStart']);
        $recurrence = implode(',', $data['eventDays']);

        if ($this->editingEventId !== null) {
            $template = $this->userTemplate($this->editingEventId);
            $template->update([
                'category_id' => $categoryId,
                'name' => $title,
                'color' => $color,
                'duration' => $duration,
                'buffer_before' => $data['eventBufferBefore'],
                'buffer_after' => $data['eventBufferAfter'],
                'default_start' => $data['eventStart'],
                'recurrence' => $recurrence,
            ]);
        } else {
            $template = auth()->user()->eventTemplates()->create([
                'category_id' => $categoryId,
                'name' => $title,
                'color' => $color,
                'duration' => $duration,
                'buffer_before' => $data['eventBufferBefore'],
                'buffer_after' => $data['eventBufferAfter'],
                'default_start' => $data['eventStart'],
                'is_recurring' => true,
                'recurrence' => $recurrence,
            ]);
        }

        $this->refreshMaterializedOccurrences($template);

        // The ripple only means something when it has more than one column to
        // jump across — a single-day block has nothing to visually connect.
        if (count($data['eventDays']) > 1) {
            $this->dispatch('weekplan-ripple', days: $data['eventDays']);
        }

        $this->cancelEventForm();
    }

    public function cancelEventForm(): void
    {
        $this->reset(['showEventForm', 'editingEventId', 'eventTitle', 'eventDays', 'eventBufferBefore', 'eventBufferAfter']);
    }

    /** Deletes the whole series — cascadeOnDelete retracts every materialised occurrence with it. */
    public function deleteEvent(int $id): void
    {
        $template = $this->userTemplate($id);
        $template->delete();

        if ($this->editingEventId === $id) {
            $this->cancelEventForm();
        }
    }

    /** Drag-to-move: keep the duration, shift the start (times snapped client-side). */
    public function moveEvent(int $id, string $start): void
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $start)) {
            return;
        }

        $template = $this->userTemplate($id);
        $startMin = max(0, min(24 * 60 - $template->duration, ScheduleEvent::toMinutes($start)));

        $template->update(['default_start' => ScheduleEvent::fromMinutes($startMin)]);

        $this->refreshMaterializedOccurrences($template);
    }

    /** Drag-to-resize: set both ends, guarding a minimum length. */
    public function resizeEvent(int $id, string $start, string $end): void
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return;
        }

        $startMin = ScheduleEvent::toMinutes($start);
        $endMin = ScheduleEvent::toMinutes($end);

        if ($endMin - $startMin < self::MIN_EVENT) {
            return;
        }

        $template = $this->userTemplate($id);
        $template->update([
            'default_start' => ScheduleEvent::fromMinutes($startMin),
            'duration' => $endMin - $startMin,
        ]);

        $this->refreshMaterializedOccurrences($template);
    }

    /** Draw a new category block on one weekday column (no form needed). */
    public function quickCreateCategoryBlock(int $categoryId, string $weekday, string $start, string $end): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($categoryId);
        $day = $this->validWeekday($weekday);

        if ($day === null) {
            return;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return;
        }
        if (ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start) < self::MIN_EVENT) {
            return;
        }

        auth()->user()->eventTemplates()->create([
            'category_id' => $category->id,
            'name' => $category->name,
            'color' => $category->color,
            'duration' => ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start),
            'default_start' => $start,
            'is_recurring' => true,
            'recurrence' => (string) $day,
        ]);
    }

    /** Draw a new free-text Termin block on one weekday column (no form needed). */
    public function quickCreateTermin(string $title, string $color, string $weekday, string $start, string $end): void
    {
        $title = trim($title);
        $day = $this->validWeekday($weekday);

        if ($title === '' || mb_strlen($title) > 255 || ! in_array($color, ScheduleEvent::EVENT_COLORS, true)) {
            return;
        }
        if ($day === null) {
            return;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return;
        }
        if (ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start) < self::MIN_EVENT) {
            return;
        }

        auth()->user()->eventTemplates()->create([
            'category_id' => null,
            'name' => $title,
            'color' => $color,
            'duration' => ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start),
            'default_start' => $start,
            'is_recurring' => true,
            'recurrence' => (string) $day,
        ]);
    }

    private function validWeekday(string $raw): ?int
    {
        return preg_match('/^[1-7]$/', $raw) ? (int) $raw : null;
    }

    /**
     * Propagate an edited/moved/resized template's new shape onto its
     * already-materialised future occurrences. Without this, a change here
     * would only take effect once a not-yet-viewed week materialises fresh —
     * the current/next week (already filled in the moment anyone opened the
     * Zeitplan) would silently keep showing the old time. Past and today's
     * occurrences are left untouched on purpose: editing a template should
     * never rewrite history or disturb something already under way. A
     * weekday dropped from the recurrence gets its now-orphaned occurrence
     * deleted outright rather than left behind — materializeRange() is
     * presence-based, so if that weekday is ever added back, it regenerates
     * cleanly with no leftover tombstone to work around.
     */
    private function refreshMaterializedOccurrences(EventTemplate $template): void
    {
        $today = auth()->user()->localToday();

        auth()->user()->scheduleEvents()
            ->where('template_id', $template->id)
            ->where('is_cancelled', false)
            ->whereDate('date', '>', $today->toDateString())
            ->get()
            ->each(function (ScheduleEvent $occurrence) use ($template) {
                if (! $template->occursOn($occurrence->date)) {
                    $occurrence->delete();

                    return;
                }

                $occurrence->update($occurrence->withNotifiedReset([
                    'category_id' => $template->category_id,
                    'title' => $template->displayName(),
                    'color' => $template->colorToken(),
                    'buffer_before' => (int) $template->buffer_before,
                    'buffer_after' => (int) $template->buffer_after,
                    'start_time' => $template->default_start,
                    'end_time' => ScheduleEvent::fromMinutes(
                        ScheduleEvent::toMinutes($template->default_start) + $template->duration
                    ),
                ]));
            });
    }

    // ── Pauses ───────────────────────────────────────────────────────

    public function openPauseForm(): void
    {
        $today = auth()->user()->localToday()->toDateString();
        $this->pauseFrom = $today;
        $this->pauseTo = $today;
        $this->pauseNote = '';
        $this->showPauseForm = true;
    }

    public function cancelPauseForm(): void
    {
        $this->reset(['showPauseForm', 'pauseFrom', 'pauseTo', 'pauseNote']);
    }

    public function savePauseRange(): void
    {
        $data = $this->validate([
            'pauseFrom' => ['required', 'date'],
            'pauseTo' => ['required', 'date', 'after_or_equal:pauseFrom'],
            'pauseNote' => ['nullable', 'string', 'max:255'],
        ]);

        $start = Carbon::parse($data['pauseFrom']);
        $end = Carbon::parse($data['pauseTo']);

        // A fat-fingered end date shouldn't silently create a year of pause rows.
        if ((int) $start->diffInDays($end) > 366) {
            $this->addError('pauseTo', 'Der Zeitraum darf höchstens ein Jahr umfassen.');

            return;
        }

        SchedulePause::pauseRange(auth()->user(), $start, $end, $data['pauseNote'] ?: null);

        $this->cancelPauseForm();
    }

    /** Switch one day back on, even inside a longer paused range. */
    public function unpauseDate(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }

        auth()->user()->schedulePauses()->whereDate('date', $date)->delete();

        // Bring the normal blocks back immediately rather than waiting for a
        // separate visit to the Zeitplan to trigger materialisation.
        ScheduleEvent::materializeRange(auth()->user(), Carbon::parse($date), Carbon::parse($date));
    }

    public function unpauseRange(string $from, string $to): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return;
        }

        auth()->user()->schedulePauses()->forRange($from, $to)->delete();

        ScheduleEvent::materializeRange(auth()->user(), Carbon::parse($from), Carbon::parse($to));
    }

    public function render()
    {
        $settings = $this->weekdaySettings;

        return view('livewire.week-plan', [
            // One frame for the whole Mon-Sun canvas, for the same reason the
            // Zeitplan's week grid shares one: seven columns, one hour gutter.
            'frame' => DayWindow::frame(
                min(array_column($settings, 'start')),
                max(array_column($settings, 'end')),
                DayWindow::rangesFrom(
                    collect($this->templatesByWeekday)->flatten()->unique('id')
                ),
            ),
        ]);
    }
}
