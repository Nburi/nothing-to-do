<?php

namespace App\Livewire\Concerns;

use App\Services\DayWindow;
use Illuminate\Support\Carbon;

/**
 * The "Tagesrahmen" popover, shared by the Zeitplan (one concrete date) and
 * the Wochenplan (one weekday). Both edit the same concept through the same
 * markup (partials/day-bounds-popover.blade.php) and the same writes, so the
 * two pages can never drift on what a day frame means.
 *
 * Which tier a given page edits is the only difference, and it rides in
 * $boundsScope: 'date' writes a schedule_day_bounds row, 'weekday' writes an
 * entry in users.weekday_day_bounds. Both are resolved back out by
 * DayWindow::settingForDate().
 */
trait ManagesDayBounds
{
    /** 'date' | 'weekday' while open, null while closed. */
    public ?string $boundsScope = null;

    /** The Y-m-d date, or the ISO weekday as a string, currently being edited. */
    public ?string $boundsKey = null;

    public string $boundsStart = '06:00';

    public string $boundsEnd = '23:00';

    /** True when the row being edited currently has an override of its own (so "zurücksetzen" has something to do). */
    public bool $boundsHasOverride = false;

    public function openDateBounds(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }

        $setting = DayWindow::settingForDate(auth()->user(), $date);

        $this->boundsScope = 'date';
        $this->boundsKey = $date;
        $this->boundsStart = DayWindow::format($setting['start']);
        $this->boundsEnd = DayWindow::format($setting['end']);
        $this->boundsHasOverride = $setting['source'] === 'date';
        $this->resetErrorBag(['boundsStart', 'boundsEnd']);
    }

    public function openWeekdayBounds(int $weekday): void
    {
        if ($weekday < 1 || $weekday > 7) {
            return;
        }

        $setting = DayWindow::settingForWeekday(auth()->user(), $weekday);

        $this->boundsScope = 'weekday';
        $this->boundsKey = (string) $weekday;
        $this->boundsStart = DayWindow::format($setting['start']);
        $this->boundsEnd = DayWindow::format($setting['end']);
        $this->boundsHasOverride = $setting['source'] === 'weekday';
        $this->resetErrorBag(['boundsStart', 'boundsEnd']);
    }

    public function saveDayBounds(): void
    {
        if ($this->boundsScope === null || $this->boundsKey === null) {
            return;
        }

        $data = $this->validate([
            'boundsStart' => ['required', 'date_format:H:i'],
            'boundsEnd' => ['required', 'date_format:H:i', 'after:boundsStart'],
        ]);

        $span = DayWindow::toMinutes($data['boundsEnd'], -1) - DayWindow::toMinutes($data['boundsStart'], -1);

        if ($span < DayWindow::MIN_SPAN) {
            $this->addError('boundsEnd', 'Ein Tag muss mindestens '.intdiv(DayWindow::MIN_SPAN, 60).' Stunden lang sein.');

            return;
        }

        $user = auth()->user();

        if ($this->boundsScope === 'date') {
            DayWindow::setDate($user, $this->boundsKey, $data['boundsStart'], $data['boundsEnd']);
        } else {
            DayWindow::setWeekday($user, (int) $this->boundsKey, $data['boundsStart'], $data['boundsEnd']);
        }

        $this->afterDayBoundsChanged();
        $this->cancelDayBounds();
    }

    /**
     * Drop this row's own override so it inherits the tier above it again —
     * a date falls back to its weekday, a weekday to the default. Nothing is
     * lost: the frame the user sees afterwards is the one they'd have had
     * without ever touching it.
     */
    public function resetDayBounds(): void
    {
        if ($this->boundsScope === null || $this->boundsKey === null) {
            return;
        }

        $user = auth()->user();

        if ($this->boundsScope === 'date') {
            DayWindow::clearDate($user, $this->boundsKey);
        } else {
            DayWindow::clearWeekday($user, (int) $this->boundsKey);
        }

        $this->afterDayBoundsChanged();
        $this->cancelDayBounds();
    }

    public function cancelDayBounds(): void
    {
        $this->reset(['boundsScope', 'boundsKey', 'boundsHasOverride']);
        $this->resetErrorBag(['boundsStart', 'boundsEnd']);
    }

    /**
     * Hook for a page that caches anything derived from the frame. The
     * default does nothing — every consumer today reads it fresh through a
     * computed property on the way out.
     */
    protected function afterDayBoundsChanged(): void
    {
        //
    }

    /**
     * Half-hour options for the two selects, as ['06:00' => '06:00', ...].
     * Built once per render rather than in the view, so the Zeitplan and the
     * Wochenplan can never offer different ranges.
     *
     * @return array<string, string>
     */
    public function dayBoundOptions(bool $forStart): array
    {
        $from = $forStart ? DayWindow::EARLIEST_START : DayWindow::EARLIEST_START + DayWindow::MIN_SPAN;
        $to = $forStart ? DayWindow::LATEST_END - DayWindow::MIN_SPAN : DayWindow::LATEST_END;

        $options = [];

        for ($minutes = $from; $minutes <= $to; $minutes += 30) {
            $options[DayWindow::format($minutes)] = DayWindow::format($minutes);
        }

        return $options;
    }

    /**
     * The frame a reset would actually land on — this row's own override
     * removed, everything below it left alone. Null when there is nothing to
     * reset. Named on the reset link itself, because "Wochentag verwenden" is
     * only true when that weekday has an override of its own; otherwise the
     * date falls all the way through to the default.
     */
    public function boundsFallbackLabel(): ?string
    {
        if (! $this->boundsHasOverride || $this->boundsKey === null) {
            return null;
        }

        $user = auth()->user();

        $setting = $this->boundsScope === 'weekday'
            ? DayWindow::defaultSetting($user)
            : DayWindow::settingForWeekday($user, Carbon::parse($this->boundsKey)->dayOfWeekIso);

        return DayWindow::label($setting['start'], $setting['end']);
    }

    /** A short "06:00–23:00" for the chip under a column header. */
    public function dayBoundsLabel(int $start, int $end): string
    {
        return DayWindow::label($start, $end);
    }

    /**
     * The human name of the row being edited, for the popover's own heading.
     * German weekday names are hardcoded rather than taken from Carbon's
     * locale — the same convention every other date label in this app
     * follows, because the app's own strings are German regardless of what
     * the framework locale happens to resolve to.
     */
    public function boundsHeading(): string
    {
        $names = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

        if ($this->boundsScope === 'weekday' && $this->boundsKey !== null) {
            return $names[(int) $this->boundsKey - 1] ?? 'Wochentag';
        }

        if ($this->boundsScope === 'date' && $this->boundsKey !== null) {
            $date = Carbon::parse($this->boundsKey);

            return ($names[$date->dayOfWeekIso - 1] ?? '').', '.$date->format('j.n.');
        }

        return 'Tagesrahmen';
    }
}
