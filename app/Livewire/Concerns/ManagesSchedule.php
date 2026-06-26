<?php

namespace App\Livewire\Concerns;

use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Shared appointment + template mutations for the timeline. Used by both the
 * Schedule page and the Brief so the two surfaces edit the day identically.
 * Every write is resolved through the owner relationship — an id alone is
 * never trusted.
 */
trait ManagesSchedule
{
    /** Topografie colour tokens an appointment may use. */
    public const EVENT_COLORS = ['contour', 'overprint', 'forest', 'signal', 'ink'];

    /** Smallest event the grid will allow (minutes). */
    public const MIN_EVENT = 10;

    /** Inline event form (shared between create and edit). */
    public bool $showEventForm = false;

    public ?int $editingEventId = null;

    public string $eventTitle = '';

    public string $eventDate = '';

    public string $eventStart = '08:00';

    public string $eventEnd = '09:00';

    public string $eventColor = 'contour';

    public bool $eventRecurring = false;

    /** ISO weekdays (1=Mon … 7=Sun) a recurring appointment repeats on. */
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

    public function openEventForm(?string $date = null): void
    {
        $this->reset(['editingEventId', 'eventTitle', 'eventColor', 'eventRecurring', 'eventDays', 'eventSaveAsTemplate']);
        $this->eventColor = 'contour';
        $this->eventDate = $date ?: Carbon::today()->toDateString();
        $this->eventStart = '08:00';
        $this->eventEnd = '09:00';
        $this->showEventForm = true;
    }

    public function startEditEvent(int $id): void
    {
        $event = $this->userEvent($id);

        $this->editingEventId = $event->id;
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
        $this->eventTitle = trim($this->eventTitle);

        $data = $this->validate([
            'eventTitle' => ['required', 'string', 'max:255'],
            'eventDate' => ['required', 'date'],
            'eventStart' => ['required', 'date_format:H:i'],
            'eventEnd' => ['required', 'date_format:H:i', 'after:eventStart'],
            'eventColor' => ['required', Rule::in(self::EVENT_COLORS)],
            'eventRecurring' => ['boolean'],
            'eventDays' => ['array'],
            'eventDays.*' => ['integer', 'between:1,7'],
        ]);

        if ($this->editingEventId !== null) {
            $event = $this->userEvent($this->editingEventId);
            $origTemplate = $event->template_id;
            $origDate = $event->date->toDateString();

            // Moving a recurring occurrence to another day detaches it into a one-off
            // and tombstones the original slot, so materialisation never regenerates
            // a duplicate there. Editing it in place keeps the series link.
            $movedFromSeries = $origTemplate !== null && $origDate !== $data['eventDate'];

            $event->update([
                'title' => $data['eventTitle'],
                'date' => $data['eventDate'],
                'start_time' => $data['eventStart'],
                'end_time' => $data['eventEnd'],
                'color' => $data['eventColor'],
                'template_id' => $movedFromSeries ? null : $event->template_id,
            ]);

            if ($movedFromSeries) {
                auth()->user()->scheduleEvents()->create([
                    'template_id' => $origTemplate,
                    'type' => ScheduleEvent::TYPE_APPOINTMENT,
                    'date' => $origDate,
                    'start_time' => $data['eventStart'],
                    'end_time' => $data['eventEnd'],
                    'is_cancelled' => true,
                    'source' => 'manual',
                ]);
            }

            $this->cancelEventForm();

            return;
        }

        $date = Carbon::parse($data['eventDate']);
        $duration = ScheduleEvent::toMinutes($data['eventEnd']) - ScheduleEvent::toMinutes($data['eventStart']);

        if ($data['eventRecurring']) {
            // A recurring appointment is a recurring template that materialises.
            $days = $data['eventDays'] ?: [$date->dayOfWeekIso];

            $template = auth()->user()->eventTemplates()->create([
                'name' => $data['eventTitle'],
                'color' => $data['eventColor'],
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
            if ($this->eventSaveAsTemplate) {
                auth()->user()->eventTemplates()->create([
                    'name' => $data['eventTitle'],
                    'color' => $data['eventColor'],
                    'duration' => $duration,
                    'default_start' => $data['eventStart'],
                    'is_recurring' => false,
                ]);
            }

            auth()->user()->scheduleEvents()->create([
                'type' => ScheduleEvent::TYPE_APPOINTMENT,
                'title' => $data['eventTitle'],
                'color' => $data['eventColor'],
                'date' => $data['eventDate'],
                'start_time' => $data['eventStart'],
                'end_time' => $data['eventEnd'],
                'source' => 'manual',
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

        $event->update([
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($startMin + $duration),
        ]);
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

        $this->userEvent($id)->update([
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($endMin),
        ]);
    }

    /** Drop / tap a template onto a day → a concrete appointment. */
    public function applyTemplate(int $templateId, string $date, ?string $start = null): void
    {
        $template = auth()->user()->eventTemplates()->findOrFail($templateId);

        $startTime = $start && preg_match('/^\d{2}:\d{2}$/', $start)
            ? $start
            : ($template->default_start ?: '08:00');

        // A placed copy is independent (no template_id) — it won't be swept by
        // series materialisation or removed if the template is later deleted.
        auth()->user()->scheduleEvents()->create([
            'type' => ScheduleEvent::TYPE_APPOINTMENT,
            'title' => $template->name,
            'color' => $template->color,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => ScheduleEvent::fromMinutes(ScheduleEvent::toMinutes($startTime) + $template->duration),
            'source' => 'manual',
        ]);
    }

    public function deleteTemplate(int $id): void
    {
        auth()->user()->eventTemplates()->whereKey($id)->delete();
    }
}
