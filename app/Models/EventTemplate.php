<?php

namespace App\Models;

use Database\Factories\EventTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reusable appointment blueprint for fast entry in the Brief / schedule
 * (e.g. "Schule", "Lauftraining", "Zahnarzt"). Recurring templates auto-
 * materialise a concrete ScheduleEvent on each matching weekday.
 */
class EventTemplate extends Model
{
    /** @use HasFactory<EventTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'duration',
        'default_start',
        'is_recurring',
        'recurrence',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'duration' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ScheduleEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class, 'template_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** ISO weekdays (1=Mon … 7=Sun) the template repeats on. */
    public function recurrenceDays(): array
    {
        if (! $this->recurrence) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($d) => (int) trim($d),
            explode(',', $this->recurrence)
        )));
    }

    /** True when a recurring template should appear on the given date. */
    public function occursOn(Carbon $date): bool
    {
        return $this->is_recurring && in_array($date->dayOfWeekIso, $this->recurrenceDays(), true);
    }
}
