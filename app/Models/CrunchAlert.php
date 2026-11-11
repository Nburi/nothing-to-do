<?php

namespace App\Models;

use App\Planning\Crunch\AlertState;
use App\Planning\Crunch\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stored early-warning state of one user (refactor step 1.4). Written
 * only through App\Services\Planning\CrunchWatch.
 */
class CrunchAlert extends Model
{
    protected $fillable = ['active', 'level', 'crunch_date', 'missing_minutes', 'raised_on', 'cleared_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'level' => Level::class,
            'crunch_date' => 'date',
            'missing_minutes' => 'integer',
            'raised_on' => 'date',
            'cleared_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toState(): AlertState
    {
        return new AlertState(
            $this->active,
            $this->level,
            $this->crunch_date?->toDateString(),
            $this->missing_minutes,
            $this->raised_on?->toDateString(),
        );
    }
}
