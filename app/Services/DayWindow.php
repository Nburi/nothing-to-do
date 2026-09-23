<?php

namespace App\Services;

use App\Models\EventTemplate;
use App\Models\ScheduleDayBound;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The visible window of a day on every timeline grid — "Tagesrahmen".
 *
 * Stateless, like PomodoroCycle/TaskSuggestor/DayPlanner. Three tiers,
 * resolved in this order:
 *
 *   1. this concrete date   (schedule_day_bounds, edited in the Zeitplan)
 *   2. this weekday         (users.weekday_day_bounds, edited in the Wochenplan)
 *   3. the user's default   (users.day_start_time / day_end_time, in Settings)
 *
 * Two things sit on top of the resolved setting before anything renders:
 *
 *  - a NIGHT_MARGIN of 30 minutes on each side stays visible but dimmed, so
 *    the day has a horizon rather than a hard cut, and so there is somewhere
 *    to drag a block that belongs just outside today's frame;
 *  - the frame then expands further, to whole hours, around anything that
 *    would otherwise be invisible — a block (including its Weg-/Pufferzeit)
 *    outside the frame must never silently disappear. The *stored* setting is
 *    never touched by that expansion.
 *
 * The desktop grid keeps a roughly constant pixel height and derives its
 * scale from the span instead of the other way round (it used to be a fixed
 * 0.6 px/min). A shorter day therefore makes every block taller, which is
 * what makes short blocks legible at all — see ppm().
 */
final class DayWindow
{
    public const DEFAULT_START = '06:00';

    public const DEFAULT_END = '23:00';

    /** Minutes kept visible, but dimmed, on each side of the setting. */
    public const NIGHT_MARGIN = 30;

    /** A frame narrower than this is not a day, it's a sliver. */
    public const MIN_SPAN = 120;

    /**
     * Earliest a day may be set to start / latest it may be set to end. The
     * frame itself can still reach 00:00 and 24:00 through the night margin
     * and the expansion above — these only bound what a user can *choose*,
     * which keeps every stored value a plain "HH:MM" with no 24:00 special
     * case anywhere.
     */
    public const EARLIEST_START = 0;

    public const LATEST_END = 23 * 60 + 30;

    /** Target height of the desktop week grid, in px — the scale follows from it. */
    public const DESKTOP_GRID_HEIGHT = 640;

    public const MIN_PPM = 0.45;

    public const MAX_PPM = 1.1;

    /**
     * Below this height a block cannot hold its own title row plus the edit
     * pencil, and the Signature Moment lifts it to exactly this. Kept in sync
     * by hand with LIFT_MIN_PX in resources/js/app.js, which is where it is
     * actually applied — this constant exists so the relationship is
     * documented and testable somewhere in PHP.
     *
     * It is 4px above the `@container (min-height: 46px)` threshold in
     * resources/css/app.css on purpose: a container query sizes against the
     * container's content box, this is a border box, and the body's 1px border
     * top and bottom would otherwise leave a lifted block two pixels short of
     * the very tier it was lifted for.
     */
    public const LIFT_MIN_PX = 50;

    /** The @container threshold the lift has to clear (content box). */
    public const TIER_FULL_PX = 46;

    // ── Reading the setting ──────────────────────────────────────────

    /**
     * The user's own default, clamped. Never consults an override.
     *
     * @return array{start: int, end: int}
     */
    public static function defaultSetting(User $user): array
    {
        return self::clamp(
            self::toMinutes($user->day_start_time, self::toMinutes(self::DEFAULT_START, 360)),
            self::toMinutes($user->day_end_time, self::toMinutes(self::DEFAULT_END, 1380)),
        ) ?? ['start' => 6 * 60, 'end' => 23 * 60];
    }

    /**
     * The setting for one ISO weekday (1 = Monday): its own override, else
     * the default.
     *
     * @return array{start: int, end: int, source: string}
     */
    public static function settingForWeekday(User $user, int $weekday): array
    {
        $overrides = is_array($user->weekday_day_bounds) ? $user->weekday_day_bounds : [];
        $row = $overrides[(string) $weekday] ?? $overrides[$weekday] ?? null;

        if (is_array($row) && isset($row['start'], $row['end'])) {
            $clamped = self::clamp(self::toMinutes($row['start'], -1), self::toMinutes($row['end'], -1));

            if ($clamped !== null) {
                return $clamped + ['source' => 'weekday'];
            }
        }

        return self::defaultSetting($user) + ['source' => 'default'];
    }

    /**
     * The setting for one concrete date: its own row, else that weekday's
     * override, else the default.
     *
     * `$override` lets a caller that already loaded the day's row (a week
     * view loads all seven at once) pass it in rather than re-querying per
     * day. Pass `false` to state explicitly that there is none.
     *
     * @return array{start: int, end: int, source: string}
     */
    public static function settingForDate(User $user, Carbon|string $date, ScheduleDayBound|false|null $override = null): array
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($override === null) {
            $override = ScheduleDayBound::forUser($user)->forDate($date)->first() ?? false;
        }

        if ($override instanceof ScheduleDayBound) {
            $clamped = self::clamp(self::toMinutes($override->start_time, -1), self::toMinutes($override->end_time, -1));

            if ($clamped !== null) {
                return $clamped + ['source' => 'date'];
            }
        }

        return self::settingForWeekday($user, $date->dayOfWeekIso);
    }

    // ── Turning a setting into a render frame ────────────────────────

    /**
     * Turn one resolved setting into a render frame.
     *
     * A grid that shows several days at once passes the *widest* setting among
     * them — a week grid has one hour gutter, so its columns cannot each have
     * their own scale. The callers assemble that union themselves, from the
     * same `daySettings`/`weekdaySettings` they already compute for the header
     * chips and the per-column night bands. A grid showing a single day (the
     * mobile Zeitplan, the mobile Wochenplan, Vorbereitung Schritt 3) passes
     * that day's own setting instead.
     *
     * @param  iterable<array{0: int, 1: int}>  $ranges  occupied [startMin, endMin] pairs that must stay visible
     * @return array{start: int, end: int, settingStart: int, settingEnd: int, expanded: bool}
     */
    public static function frame(int $settingStart, int $settingEnd, iterable $ranges = []): array
    {
        $start = max(0, $settingStart - self::NIGHT_MARGIN);
        $end = min(1440, $settingEnd + self::NIGHT_MARGIN);
        $expanded = false;

        foreach ($ranges as $range) {
            [$lo, $hi] = $range;

            if ($lo < $start) {
                $start = max(0, intdiv((int) $lo, 60) * 60);
                $expanded = true;
            }

            if ($hi > $end) {
                $end = min(1440, (int) ceil($hi / 60) * 60);
                $expanded = true;
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'settingStart' => $settingStart,
            'settingEnd' => $settingEnd,
            'expanded' => $expanded,
        ];
    }

    /**
     * Occupied [start, end] minute pairs, Weg-/Pufferzeit included — a
     * travel time reaching before the frame starts has to widen it too, or
     * half of it would be cut off with nothing saying so.
     *
     * @param  iterable<ScheduleEvent|EventTemplate>  $items
     * @return array<int, array{0: int, 1: int}>
     */
    public static function rangesFrom(iterable $items): array
    {
        $ranges = [];

        foreach ($items as $item) {
            $ranges[] = [$item->occupiedStartMinutes(), $item->occupiedEndMinutes()];
        }

        return $ranges;
    }

    /** Px per minute for the desktop week grid, so the grid's own height stays roughly constant. */
    public static function ppm(int $span): float
    {
        if ($span <= 0) {
            return self::MIN_PPM;
        }

        return max(self::MIN_PPM, min(self::MAX_PPM, self::DESKTOP_GRID_HEIGHT / $span));
    }

    // ── Writing ──────────────────────────────────────────────────────

    public static function setDefault(User $user, string $start, string $end): void
    {
        $clamped = self::clamp(self::toMinutes($start, -1), self::toMinutes($end, -1));

        if ($clamped === null) {
            return;
        }

        $user->update([
            'day_start_time' => self::format($clamped['start']),
            'day_end_time' => self::format($clamped['end']),
        ]);
    }

    public static function setWeekday(User $user, int $weekday, string $start, string $end): void
    {
        $clamped = self::clamp(self::toMinutes($start, -1), self::toMinutes($end, -1));

        if ($clamped === null || $weekday < 1 || $weekday > 7) {
            return;
        }

        $overrides = is_array($user->weekday_day_bounds) ? $user->weekday_day_bounds : [];
        $overrides[(string) $weekday] = [
            'start' => self::format($clamped['start']),
            'end' => self::format($clamped['end']),
        ];

        $user->update(['weekday_day_bounds' => $overrides]);
    }

    public static function clearWeekday(User $user, int $weekday): void
    {
        $overrides = is_array($user->weekday_day_bounds) ? $user->weekday_day_bounds : [];
        unset($overrides[(string) $weekday], $overrides[$weekday]);

        // An emptied-out map goes back to null, so "never touched" and "every
        // override removed again" are the same stored state.
        $user->update(['weekday_day_bounds' => $overrides === [] ? null : $overrides]);
    }

    public static function setDate(User $user, Carbon|string $date, string $start, string $end): void
    {
        $clamped = self::clamp(self::toMinutes($start, -1), self::toMinutes($end, -1));

        if ($clamped === null) {
            return;
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $user->scheduleDayBounds()->updateOrCreate(
            ['date' => $date->toDateString()],
            ['start_time' => self::format($clamped['start']), 'end_time' => self::format($clamped['end'])],
        );
    }

    public static function clearDate(User $user, Carbon|string $date): void
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $user->scheduleDayBounds()->forDate($date)->delete();
    }

    // ── Small helpers ────────────────────────────────────────────────

    public static function toMinutes(?string $hm, int $fallback): int
    {
        if ($hm === null || ! preg_match('/^\d{1,2}:\d{2}$/', $hm)) {
            return $fallback;
        }

        return ScheduleEvent::toMinutes($hm);
    }

    public static function format(int $minutes): string
    {
        $minutes = max(0, min(1440, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** A readable "06:00–23:00" for a chip or a caption. */
    public static function label(int $start, int $end): string
    {
        return self::format($start).'–'.self::format($end);
    }

    /**
     * Bring a pair into range, or reject it outright when either end could
     * not be parsed at all (the -1 fallback above).
     *
     * @return array{start: int, end: int}|null
     */
    private static function clamp(int $start, int $end): ?array
    {
        if ($start < 0 || $end < 0) {
            return null;
        }

        $start = max(self::EARLIEST_START, min(self::LATEST_END - self::MIN_SPAN, $start));
        $end = max($start + self::MIN_SPAN, min(self::LATEST_END, $end));

        return ['start' => $start, 'end' => $end];
    }
}
