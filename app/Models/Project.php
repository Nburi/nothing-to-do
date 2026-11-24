<?php

namespace App\Models;

use App\Models\Concerns\HasBoardPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step 4.3: a bigger piece of work with steps in Inbox, To-dos and Tasks, a roadmap of stages and
 * notes. The board shows at most its next two steps (App\Services\Projects\NextSteps).
 *
 * The table comes from the old app (2026-06): `brainstorm` holds the notes (cards in 4.3b),
 * `external_url` and `deadline` are kept for the data export but not shown (Niels, 2026-11-17).
 */
class Project extends Model
{
    use HasBoardPosition;

    public const NAME_MAX = 120;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'deadline' => 'date'];
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

    /** @return HasMany<ProjectStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
