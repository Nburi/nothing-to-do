<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which day a task is planned for, and its order among that day's other
 * tasks. One row per task (task_id unique) — a task lives on at most one
 * day at a time, the same "one contiguous slot" rule the block-based Planer
 * had for blocks. See App\Services\DayPlanner for the read/write logic.
 */
class TaskDayPlan extends Model
{
    protected $fillable = [
        'task_id',
        'planned_date',
        'sort_order',
        'source',
    ];

    protected function casts(): array
    {
        return [
            // Explicit :Y-m-d format (not a bare 'date' cast) so every write
            // through Eloquent stores the same plain string App\Services\
            // DayPlanner's own upsert() calls already write directly — a bare
            // 'date' cast round-trips through a full datetime string instead
            // (see CLAUDE.md's Known Issues entry on this exact trap, first
            // hit by tasks.today_date), which would make an exact-match read
            // against a row seeded any other way silently miss it.
            'planned_date' => 'date:Y-m-d',
            'sort_order' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** whereDate(), not where() — robust regardless of how a given row's date ended up stored (see the cast note above). */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('planned_date', $date);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
