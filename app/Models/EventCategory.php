<?php

namespace App\Models;

use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, user-configured category (e.g. "Schule", "Training", "Arbeiten").
 * A schedule event or template can point to one instead of carrying its own
 * free-text title/colour — renaming or recolouring a category live-updates
 * every block that references it, past and future.
 */
class EventCategory extends Model
{
    /** @use HasFactory<EventCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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
        return $this->hasMany(ScheduleEvent::class, 'category_id');
    }

    /** @return HasMany<EventTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(EventTemplate::class, 'category_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
