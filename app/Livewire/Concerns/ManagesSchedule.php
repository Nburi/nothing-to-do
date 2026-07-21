<?php

namespace App\Livewire\Concerns;

use App\Models\ScheduleEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Shared appointment/category + template mutations for the timeline. Used by
 * the Schedule page. Every write is resolved through the owner relationship —
 * an id alone is never trusted.
 */
trait ManagesSchedule
{
    /** Smallest event the grid will allow (minutes). */
    public const MIN_EVENT = 10;

    /** Inline event form (shared between create and edit). */
    public bool $showEventForm = false;

    public ?int $editingEventId = null;

    /** 'appointment' (free-text Termin) or 'category' (linked to an EventCategory). */
    public string $eventKind = 'appointment';

    public string $eventTitle = '';

    public string $eventDate = '';

    public string $eventStart = '08:00';

    public string $eventEnd = '09:00';

    public string $eventColor = 'contour';

    public ?int $eventCategoryId = null;

    public bool $eventRecurring = false;

    /** ISO weekdays (1=Mon … 7=Sun) a recurring block repeats on. */
    public array $eventDays = [];

    public bool $eventSaveAsTemplate = false;

    protected function userEvent(int $id): ScheduleEvent
    {
        return auth()->user()->scheduleEvents()->findOrFail($id);
    }

    /** The user's quick-add templates. */
    #[Computed]
    public function templates(): Collection
    {
        return auth()->user()->eventTemplates()->ordered()->get();
    }

    /** The user's configured categories, for the event form's chip picker. */
    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->eventCategories()->ordered()->get();
    }

    public function openEventForm(?string $date = null): void
    {
        $this->reset(['editingEventId', 'eventKind', 'eventTitle', 'eventColor', 'eventCategoryId', 'eventRecurring', 'eventDays', 'eventSaveAsTemplate']);
        $this->eventKind = 'appointment';
        $this->eventColor = 'contour';
        $this->eventDate = $date ?: auth()->user()->localToday()->toDateString();
        $this->eventStart = '08:00';
        $this->eventEnd = '09:00';
        $this->showEventForm = true;
    }

    public function startEditEvent(int $id): void
    {
        $event = $this->userEvent($id);

        $this->editingEventId = $event->id;
        $this->eventKind = $event->isCategory() ? 'category' : 'appointment';
        $this->eventCategoryId = $event->category_id;
        $this->eventTitle = (string) $event->title;
        $this->eventDate = $event->date->toDateString();
        $this->eventStart = $event->start_time;
        $this->eventEnd = $event->end_time;
        $this->eventColor = $event->colorToken();
        // Recurrence is set at creation; editing touches this occurrence only.
        $this->eventRecurring = false;
        $this->eventDays = [];
        $this->eventSaveAsTemplate = false;
        $this->showEventForm = true;
    }

    public function saveEventForm(): void
    {
        $rules = [
            'eventKind' => ['required', 'in:appointment,category'],
            'eventDate' => ['required', 'date'],
            'eventStart' => ['required', 'date_format:H:i'],
            'eventEnd' => ['required', 'date_format:H:i', 'after:eventStart'],
            'eventRecurring' => ['boolean'],
            'eventDays' => ['array'],
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

        // Resolve the literal title/colour snapshot this save writes to the row.
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

        if ($this->editingEventId !== null) {
            $event = $this->userEvent($this->editingEventId);
            $origTemplate = $event->template_id;
            $origDate = $event->date->toDateString();

            // Moving a recurring occurrence to another day detaches it into a one-off
            // and tombstones the original slot, so materialisation never regenerates
            // a duplicate there. Editing it in place keeps the series link.
            $movedFromSeries = $origTemplate !== null && $origDate !== $data['eventDate'];

            $event->update($event->withNotifiedReset([
                'category_id' => $categoryId,
                'title' => $title,
                'date' => $data['eventDate'],
                'start_time' => $data['eventStart'],
                'end_time' => $data['eventEnd'],
                'color' => $color,
                'template_id' => $movedFromSeries ? null : $event->template_id,
            ]));

            if ($movedFromSeries) {
                auth()->user()->scheduleEvents()->create([
                    'template_id' => $origTemplate,
                    'date' => $origDate,
                    'start_time' => $data['eventStart'],
                    'end_time' => $data['eventEnd'],
                    'is_cancelled' => true,
                ]);
            }

            $this->cancelEventForm();

            return;
        }

        $date = Carbon::parse($data['eventDate']);
        $duration = ScheduleEvent::toMinutes($data['eventEnd']) - ScheduleEvent::toMinutes($data['eventStart']);

        if ($data['eventRecurring']) {
            // A recurring block is a recurring template that materialises.
            $days = $data['eventDays'] ?: [$date->dayOfWeekIso];

            $template = auth()->user()->eventTemplates()->create([
                'category_id' => $categoryId,
                'name' => $title,
                'color' => $color,
                'duration' => $duration,
                'default_start' => $data['eventStart'],
                'is_recurring' => true,
                'recurrence' => implode(',', $days),
            ]);

            ScheduleEvent::materializeRange(
                auth()->user(),
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek(),
            );

            // Guarantee the exact day the user added it on shows immediately.
            if ($template->occursOn($date)) {
                ScheduleEvent::materializeRange(auth()->user(), $date->copy()->startOfDay(), $date->copy()->startOfDay());
            }
        } else {
            // A category is already the reusable primitive — quick-add templates
            // only make sense for one-off, free-text Termine.
            if ($this->eventKind === 'appointment' && $this->eventSaveAsTemplate) {
                auth()->user()->eventTemplates()->create([
                    'name' => $title,
                    'color' => $color,
                    'duration' => $duration,
                    'default_start' => $data['eventStart'],
                    'is_recurring' => false,
                ]);
            }

            auth()->user()->scheduleEvents()->create([
                'category_id' => $categoryId,
                'title' => $title,
                'color' => $color,
                'date' => $data['eventDate'],
                'start_time' => $data['eventStart'],
                'end_time' => $data['eventEnd'],
            ]);
        }

        $this->cancelEventForm();
    }

    public function cancelEventForm(): void
    {
        $this->reset([
            'showEventForm', 'editingEventId', 'eventTitle',
            'eventRecurring', 'eventDays', 'eventSaveAsTemplate',
        ]);
    }

    /** Delete a one-off; cancel (tombstone) a recurring occurrence so it can't regenerate. */
    public function deleteEvent(int $id): void
    {
        $event = $this->userEvent($id);

        if ($event->template_id !== null) {
            $event->update(['is_cancelled' => true]);
        } else {
            $event->delete();
        }

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

        $event = $this->userEvent($id);
        $duration = $event->durationMinutes();
        $startMin = max(0, min(24 * 60 - $duration, ScheduleEvent::toMinutes($start)));

        $event->update($event->withNotifiedReset([
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($startMin + $duration),
        ]));
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

        $event = $this->userEvent($id);
        $event->update($event->withNotifiedReset([
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($endMin),
        ]));
    }

    /** Drop / tap a template onto a day → a concrete event. */
    public function applyTemplate(int $templateId, string $date, ?string $start = null): void
    {
        $template = auth()->user()->eventTemplates()->findOrFail($templateId);

        $startTime = $start && preg_match('/^\d{2}:\d{2}$/', $start)
            ? $start
            : ($template->default_start ?: '08:00');

        // A placed copy is independent (no template_id) — it won't be swept by
        // series materialisation or removed if the template is later deleted.
        auth()->user()->scheduleEvents()->create([
            'category_id' => $template->category_id,
            'title' => $template->displayName(),
            'color' => $template->colorToken(),
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => ScheduleEvent::fromMinutes(ScheduleEvent::toMinutes($startTime) + $template->duration),
        ]);
    }

    /** Quick-create a category block by drawing on the timeline (no form needed). */
    public function quickCreateCategoryBlock(int $categoryId, string $date, string $start, string $end): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($categoryId);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return;
        }
        if (ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start) < self::MIN_EVENT) {
            return;
        }

        auth()->user()->scheduleEvents()->create([
            'category_id' => $category->id,
            'title' => $category->name,
            'color' => $category->color,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /** Quick-create a free-text Termin by drawing on the timeline (no form needed). */
    public function quickCreateTermin(string $title, string $color, string $date, string $start, string $end): void
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 255 || ! in_array($color, ScheduleEvent::EVENT_COLORS, true)) {
            return;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return;
        }
        if (ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start) < self::MIN_EVENT) {
            return;
        }

        auth()->user()->scheduleEvents()->create([
            'category_id' => null,
            'title' => $title,
            'color' => $color,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function deleteTemplate(int $id): void
    {
        auth()->user()->eventTemplates()->whereKey($id)->delete();
    }
}
