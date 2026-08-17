<?php

namespace App\Models;

use Database\Factories\SchedulePauseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A single date on which the week plan's recurring templates are suppressed
 * (holidays, sick days, ...). One row per date rather than a date range —
 * see ScheduleEvent::scopeVisible(), the only place this is actually
 * enforced, for why that keeps the check a single correlated subquery
 * instead of a range-overlap comparison, and lets one day inside a longer
 * pause be switched back on independently.
 */
class SchedulePause extends Model
{
    /** @use HasFactory<SchedulePauseFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForRange(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $start = $start instanceof Carbon ? $start->toDateString() : $start;
        $end = $end instanceof Carbon ? $end->toDateString() : $end;

        return $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('date');
    }

    /**
     * Insert one row per date in [start, end] (inclusive). insertOrIgnore +
     * the (user,date) unique index makes this idempotent — a range that
     * overlaps an already-paused date just skips that one row.
     */
    public static function pauseRange(User $user, Carbon $start, Carbon $end, ?string $note): void
    {
        $now = now();
        $rows = [];

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $rows[] = [
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'note' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            self::insertOrIgnore($rows);
        }
    }

    /**
     * Collapse a date-ascending list of pauses into consecutive [start, end]
     * ranges for display (e.g. 15 daily rows -> one "14.7.–3.8." line). A
     * gap of even one day starts a new range, so a day switched back on
     * inside a longer pause correctly splits it into two.
     *
     * @param  Collection<int, self>  $pauses  ordered by date ascending
     * @return array<int, array{start: Carbon, end: Carbon, note: ?string}>
     */
    public static function collapseToRanges(Collection $pauses): array
    {
        $ranges = [];
        $current = null;

        foreach ($pauses as $pause) {
            if ($current !== null && $pause->date->isSameDay($current['end']->copy()->addDay())) {
                $current['end'] = $pause->date;
                continue;
            }

            if ($current !== null) {
                $ranges[] = $current;
            }

            $current = ['start' => $pause->date, 'end' => $pause->date, 'note' => $pause->note];
        }

        if ($current !== null) {
            $ranges[] = $current;
        }

        return $ranges;
    }
}
