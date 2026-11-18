<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step 4.3: one stage ("Etappe") of a project's roadmap. Stages run in sort_order; the steps of
 * a stage wait until every step of the stages before it is done. The date is an optional target.
 */
class ProjectStage extends Model
{
    public const NAME_MAX = 120;

    protected $fillable = ['name', 'target_date', 'sort_order'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'stage_id');
    }
}
