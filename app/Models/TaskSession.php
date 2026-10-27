<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part of a task that was split across several days — "Aufsatz, Teil
 * 2 von 3, am Mittwoch, 45 Minuten". At most one part per task per day. See
 * App\Services\DayPlanner (splitTask / completeSession / syncAnchor) for the
 * read/write logic and the task_day_plans "anchor" it keeps in step.
 */
class TaskSession extends Model
{
    protected $fillable = [
        'task_id',
        'planned_date',
        'minutes',
        'sort_order',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            // Explicit :Y-m-d, same reason as TaskDayPlan (CLAUDE.md, the
            // today_date trap): an exact-day read must never depend on which
            // write path stored the row.
            'planned_date' => 'date:Y-m-d',
            'minutes' => 'integer',
            'sort_order' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('done_at');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('planned_date', $date);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
