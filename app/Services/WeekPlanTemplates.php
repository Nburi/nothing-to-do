<?php

namespace App\Services;

use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;

/**
 * The part of the Wochenplan that is not UI: pushing an edited recurring
 * template onto the occurrences it has already materialised. Extracted from
 * App\Livewire\WeekPlan so the MCP tools edit a block through exactly the same
 * rule the page does (stateless, like PomodoroCycle/DayWindow).
 */
class WeekPlanTemplates
{
    /**
     * Apply $template's current shape to its still-linked, not-cancelled,
     * STRICTLY FUTURE occurrences: update them to the new title/colour/time/
     * buffers, or delete the row outright when the edited weekday mask no longer
     * includes that occurrence's weekday (not tombstoned — materialisation is
     * presence-based, so a later re-added weekday regenerates cleanly).
     *
     * Today's occurrence and every past one are deliberately never touched: a
     * template edit must not rewrite history or disturb something already under way.
     */
    public static function refreshOccurrences(User $user, EventTemplate $template): void
    {
        $today = $user->localToday();

        $user->scheduleEvents()
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
}
