<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task area (REQUIREMENTS § 4): an open, user-defined list such as School,
 * Sport, Own projects. Separate from week-plan categories, but linked to them
 * through `category_area`: a category only suggests tasks of its linked areas.
 *
 * Deleting an area unlinks it everywhere (pivot cascades) and sets its tasks'
 * `area_id` to null — the tasks themselves are never lost.
 */
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = ['name', 'color', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return BelongsToMany<EventCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(EventCategory::class, 'category_area')->withTimestamps();
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
