<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single date whose day frame differs from the user's default (or from that
 * weekday's own override) — "heute schlafe ich aus". One row per date, the
 * same shape as SchedulePause and for the same reason.
 *
 * `date` is cast with an explicit format rather than a bare 'date': a bare
 * cast round-trips through a full datetime string on Eloquent writes while a
 * query-builder write stores a plain Y-m-d, and an exact-equality read across
 * that mismatch silently matches nothing (see CLAUDE.md §10). Every read here
 * additionally goes through whereDate(), as defence in depth.
 */
class ScheduleDayBound extends Model
{
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
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

    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->whereDate('date', $date);
    }

    public function scopeForRange(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $start = $start instanceof Carbon ? $start->toDateString() : $start;
        $end = $end instanceof Carbon ? $end->toDateString() : $end;

        return $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end);
    }
}
