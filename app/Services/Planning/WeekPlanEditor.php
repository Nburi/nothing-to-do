<?php

namespace App\Services\Planning;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\ChangeScope;
use App\Support\UserClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Refactor step 1.2: the write side of the week plan (R4; risks C2, C3).
 * One service owns the rules; WeekPlanReader reads the result back. Same
 * per-date model and tables as the old app, so the old screens keep working:
 *
 * - A repeating block is an EventTemplate. One occurrence changed "this week
 *   only" is a schedule_events row with the template id for that date; a
 *   cancelled occurrence is such a row with is_cancelled (a tombstone).
 *   Moving an occurrence to another date leaves a tombstone and creates a
 *   one-off (the old app's rule).
 * - "Every week" changes the template from tomorrow on. Rows the old screens
 *   materialised for future dates follow the template, **unless the user
 *   changed that one date on purpose**: a row that no longer matches the old
 *   template is kept. (The old app overwrote those; that lost one-off edits.)
 *   Today and the past are never rewritten.
 * - Removing a weekday "every week" never deletes the template or its past
 *   rows; with no weekday left the template simply stops repeating.
 * - Pauses are one row per date (ranges up to a year), so a single day can
 *   be switched back on. They hide template blocks, never one-offs.
 *
 * Every id is resolved through the user's own rows; input is validated here,
 * not only in the form. Times are local "HH:MM", blocks stay inside one day.
 */
final class WeekPlanEditor
{
    public const MIN_MINUTES = 10;

    public const MAX_PAUSE_DAYS = 366;

    /**
     * A new repeating block.
     *
     * @param  list<int>  $weekdays  ISO 1 = Monday … 7 = Sunday
     */
    public function addRepeating(User $user, array $weekdays, string $start, string $end, ?int $categoryId, ?string $title = null, ?string $color = null): EventTemplate
    {
        $data = $this->validated(compact('weekdays', 'start', 'end', 'categoryId', 'title', 'color'), $user, weekdays: true);
        [$name, $colour] = $this->label($user, $data);

        return $user->eventTemplates()->create([
            'category_id' => $data['categoryId'],
            'name' => $name,
            'color' => $colour,
            'duration' => $this->minutes($data['end']) - $this->minutes($data['start']),
            'default_start' => $data['start'],
            'is_recurring' => true,
            'recurrence' => implode(',', $this->weekdays($data['weekdays'])),
        ]);
    }

    /** A block on one date only: a Termin (no category) or a one-off category block. */
    public function addOnce(User $user, string $date, string $start, string $end, ?int $categoryId, ?string $title = null, ?string $color = null): ScheduleEvent
    {
        $data = $this->validated(compact('date', 'start', 'end', 'categoryId', 'title', 'color'), $user, date: true);
        [$name, $colour] = $this->label($user, $data);

        return $user->scheduleEvents()->create([
            'category_id' => $data['categoryId'],
            'title' => $name,
            'color' => $colour,
            'date' => $data['date'],
            'start_time' => $data['start'],
            'end_time' => $data['end'],
        ]);
    }

    /**
     * Change one occurrence of a repeating block: new time (and category) for
     * this date only, or for the series.
     */
    public function changeOccurrence(User $user, int $templateId, string $date, string $start, string $end, ?int $categoryId, ChangeScope $scope): void
    {
        $template = $this->template($user, $templateId);
        $data = $this->validated(compact('date', 'start', 'end', 'categoryId'), $user, date: true);

        DB::transaction(function () use ($user, $template, $data, $scope) {
            if ($scope === ChangeScope::ThisWeekOnly) {
                $category = $data['categoryId'] === null ? null : EventCategory::forUser($user)->find($data['categoryId']);
                $this->occurrence($user, $template, $data['date'])->fill([
                    'category_id' => $data['categoryId'],
                    'title' => $category?->name ?? $template->displayName(),
                    'color' => $category?->color ?? $template->colorToken(),
                    'start_time' => $data['start'],
                    'end_time' => $data['end'],
                    'is_cancelled' => false,
                ])->save();

                return;
            }

            $before = $this->snapshot($template);
            $category = $data['categoryId'] === null ? null : EventCategory::forUser($user)->find($data['categoryId']);
            $template->update([
                'category_id' => $data['categoryId'],
                'name' => $category?->name ?? $template->name,
                'color' => $category?->color ?? $template->color,
                'default_start' => $data['start'],
                'duration' => $this->minutes($data['end']) - $this->minutes($data['start']),
            ]);
            $this->followTemplate($user, $template->fresh(), $before);
        });
    }

    /** Move one occurrence to another date (this week only): a tombstone on the old date, a one-off on the new one. */
    public function moveOccurrence(User $user, int $templateId, string $fromDate, string $toDate, string $start, string $end): ScheduleEvent
    {
        $template = $this->template($user, $templateId);
        $data = $this->validated(['date' => $toDate, 'from' => $fromDate, 'start' => $start, 'end' => $end], $user, date: true);

        return DB::transaction(function () use ($user, $template, $data) {
            $this->occurrence($user, $template, $data['from'])->fill(['is_cancelled' => true])->save();

            return $user->scheduleEvents()->create([
                'category_id' => $template->category_id,
                'title' => $template->displayName(),
                'color' => $template->colorToken(),
                'date' => $data['date'],
                'start_time' => $data['start'],
                'end_time' => $data['end'],
            ]);
        });
    }

    /** Cancel one occurrence (this week only), or stop the block on that weekday (every week). */
    public function cancelOccurrence(User $user, int $templateId, string $date, ChangeScope $scope): void
    {
        $template = $this->template($user, $templateId);
        $date = $this->validated(['date' => $date], $user, date: true)['date'];

        DB::transaction(function () use ($user, $template, $date, $scope) {
            if ($scope === ChangeScope::ThisWeekOnly) {
                $this->occurrence($user, $template, $date)->fill(['is_cancelled' => true])->save();

                return;
            }

            $before = $this->snapshot($template);
            $weekday = Carbon::parse($date)->dayOfWeekIso;
            $days = array_values(array_diff($template->recurrenceDays(), [$weekday]));
            $template->update($days === []
                ? ['is_recurring' => false]
                : ['recurrence' => implode(',', $days)]);
            $this->followTemplate($user, $template->fresh(), $before);
        });
    }

    /** Undo a "this week only" change: the date follows the template again. */
    public function restoreOccurrence(User $user, int $templateId, string $date): void
    {
        $template = $this->template($user, $templateId);
        $date = $this->validated(['date' => $date], $user, date: true)['date'];

        ScheduleEvent::forUser($user)->where('template_id', $template->id)->whereDate('date', $date)->delete();
    }

    /** Pause every date from $from to $to (holidays, sick days). */
    public function pause(User $user, string $from, string $to, ?string $note = null): void
    {
        $data = Validator::make(compact('from', 'to', 'note'), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'note' => ['nullable', 'string', 'max:255'],
        ])->after(function ($v) use ($from, $to) {
            if (! $v->errors()->any() && Carbon::parse($from)->diffInDays(Carbon::parse($to)) > self::MAX_PAUSE_DAYS) {
                $v->errors()->add('to', __('weekplan.pause_too_long'));
            }
        })->validate();

        SchedulePause::pauseRange($user, Carbon::parse($data['from']), Carbon::parse($data['to']), $data['note'] ?? null);
    }

    /** Switch dates back on (one date, or a range). */
    public function resume(User $user, string $from, ?string $to = null): void
    {
        $data = Validator::make(['from' => $from, 'to' => $to ?? $from], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        SchedulePause::forUser($user)->forRange($data['from'], $data['to'])->delete();
    }

    // ── internals ────────────────────────────────────────────────────

    /**
     * Future rows of the template that still show its old values follow the
     * new template; rows changed on purpose stay; rows on a weekday the
     * template no longer has go (tombstones too, so nothing lingers).
     *
     * @param  array{category_id:?int,start_time:string,end_time:string}  $before
     */
    private function followTemplate(User $user, EventTemplate $template, array $before): void
    {
        $today = UserClock::now($user)->toDateString();
        $start = $template->default_start ?: '08:00';
        $end = ScheduleEvent::fromMinutes($this->minutes($start) + (int) $template->duration);

        ScheduleEvent::forUser($user)
            ->where('template_id', $template->id)
            ->whereDate('date', '>', $today)
            ->get()
            ->each(function (ScheduleEvent $row) use ($template, $before, $start, $end) {
                if (! $template->occursOn($row->date)) {
                    $row->delete();

                    return;
                }

                $untouched = ! $row->is_cancelled
                    && $row->category_id === $before['category_id']
                    && $row->start_time === $before['start_time']
                    && $row->end_time === $before['end_time'];

                if ($untouched) {
                    $row->update($row->withNotifiedReset([
                        'category_id' => $template->category_id,
                        'title' => $template->displayName(),
                        'color' => $template->colorToken(),
                        'start_time' => $start,
                        'end_time' => $end,
                    ]));
                }
            });
    }

    /** @return array{category_id:?int,start_time:string,end_time:string} */
    private function snapshot(EventTemplate $template): array
    {
        $start = $template->default_start ?: '08:00';

        return [
            'category_id' => $template->category_id,
            'start_time' => $start,
            'end_time' => ScheduleEvent::fromMinutes($this->minutes($start) + (int) $template->duration),
        ];
    }

    /** The row that holds this date's occurrence, existing or new (not saved). */
    private function occurrence(User $user, EventTemplate $template, string $date): ScheduleEvent
    {
        $row = ScheduleEvent::forUser($user)->where('template_id', $template->id)->whereDate('date', $date)->first();
        if ($row !== null) {
            return $row;
        }

        $start = $template->default_start ?: '08:00';
        $row = new ScheduleEvent([
            'template_id' => $template->id,
            'category_id' => $template->category_id,
            'title' => $template->displayName(),
            'color' => $template->colorToken(),
            'date' => $date,
            'start_time' => $start,
            'end_time' => ScheduleEvent::fromMinutes($this->minutes($start) + (int) $template->duration),
            'is_cancelled' => false,
        ]);
        $row->user_id = $user->id;

        return $row;
    }

    private function template(User $user, int $id): EventTemplate
    {
        return EventTemplate::forUser($user)->findOrFail($id);
    }

    /** @return array{0:string,1:string} title and colour of a new block */
    private function label(User $user, array $data): array
    {
        if ($data['categoryId'] !== null) {
            $category = EventCategory::forUser($user)->findOrFail($data['categoryId']);

            return [$category->name, $category->color];
        }

        return [trim((string) $data['title']), $data['color'] ?? 'contour'];
    }

    private function validated(array $input, User $user, bool $weekdays = false, bool $date = false): array
    {
        $rules = [];
        if (array_key_exists('start', $input)) {
            $rules['start'] = ['required', 'date_format:H:i'];
            $rules['end'] = ['required', 'date_format:H:i', 'after:start'];
        }
        if ($date) {
            $rules['date'] = ['required', 'date_format:Y-m-d'];
        }
        if (array_key_exists('from', $input)) {
            $rules['from'] = ['required', 'date_format:Y-m-d'];
        }
        if ($weekdays) {
            $rules['weekdays'] = ['required', 'array', 'min:1'];
            $rules['weekdays.*'] = ['integer', 'between:1,7'];
        }
        if (array_key_exists('categoryId', $input)) {
            $rules['categoryId'] = ['nullable', 'integer', 'exists:event_categories,id,user_id,'.$user->id];
            if (array_key_exists('title', $input)) {
                $rules['title'] = ['required_without:categoryId', 'nullable', 'string', 'max:255'];
                $rules['color'] = ['nullable', 'in:'.implode(',', ScheduleEvent::EVENT_COLORS)];
            }
        }

        $validator = Validator::make($input, $rules);
        $validator->after(function ($v) use ($input) {
            if (isset($input['start'], $input['end']) && ! $v->errors()->any()
                && $this->minutes($input['end']) - $this->minutes($input['start']) < self::MIN_MINUTES) {
                $v->errors()->add('end', __('weekplan.too_short', ['minutes' => self::MIN_MINUTES]));
            }
        });

        return $validator->validate() + ['categoryId' => null, 'title' => null, 'color' => null];
    }

    /** @param  list<int|string>  $weekdays */
    private function weekdays(array $weekdays): array
    {
        $days = array_values(array_unique(array_map('intval', $weekdays)));
        sort($days);

        return $days;
    }

    private function minutes(string $hm): int
    {
        return ScheduleEvent::toMinutes($hm);
    }
}
