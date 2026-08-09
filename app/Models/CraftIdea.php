<?php

namespace App\Models;

use Database\Factories\CraftIdeaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CraftIdea extends Model
{
    /** @use HasFactory<CraftIdeaFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'where_to_begin',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_done', false);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('is_done', true);
    }
}
