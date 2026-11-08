<?php

namespace App\Models;

use App\Planning\Session\SessionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stored session of one user (refactor step 1.7). Read and written only
 * through App\Services\FocusSession, which applies the pure SessionEngine.
 */
class PomodoroSession extends Model
{
    protected $fillable = ['state'];

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessionState(): SessionState
    {
        return $this->state ? SessionState::fromArray($this->state) : SessionState::idle();
    }
}
