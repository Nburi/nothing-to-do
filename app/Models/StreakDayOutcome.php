<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable, one-time decision about a single local calendar day's streak
 * outcome — see App\Services\ProgressStats::recordOutcome()/dailyOutcomeMap()
 * for how this is written and read, and the migration for why it exists at
 * all (a decided day must never un-decide itself).
 */
class StreakDayOutcome extends Model
{
    public const OUTCOME_PERFECT = 'perfect';

    public const OUTCOME_FROZEN = 'frozen';

    /**
     * In-memory only — never persisted to this table. A day with an
     * attempted-but-incomplete today-set (see ProgressStats::dailyOutcomeMap())
     * gets this value purely so perfectDayRate()'s denominator keeps its
     * original "of the days you attempted a today-list at all" meaning,
     * rather than silently narrowing to just perfect+frozen days once those
     * two became persistable concepts.
     */
    public const OUTCOME_BROKEN = 'broken';

    protected $fillable = ['user_id', 'date', 'outcome', 'reason'];

    protected function casts(): array
    {
        return [
            // Explicit :Y-m-d format, not a bare 'date' cast — same reasoning
            // as TaskDayPlan::planned_date (see CLAUDE.md's today_date trap):
            // every read here is an exact-string lookup keyed by toDateString().
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
}
