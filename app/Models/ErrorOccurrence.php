<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rendered HTML error page — a fact in time, never edited after it's
 * written. See App\Services\ErrorStats for how these are created and read,
 * and CLAUDE.md, "Fehler-Statistiken".
 */
class ErrorOccurrence extends Model
{
    protected $fillable = [
        'status_code',
        'exception_class',
        'message',
        'path',
        'method',
        'user_id',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfStatus(Builder $query, ?int $statusCode): Builder
    {
        return $statusCode !== null ? $query->where('status_code', $statusCode) : $query;
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
