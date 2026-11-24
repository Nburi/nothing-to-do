<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step 4.3: a small bundle of tasks of one list (all To-dos or all Tasks). The board shows its
 * next two. A group that turned into a project (migration 2026_11_17_000004) carries
 * project_id and is no longer a group; its row and note cards stay for the export.
 */
class TaskGroup extends Model
{
    public const NAME_MAX = 120;

    /** The lists a new group can hold. */
    public const LISTS = ['todos', 'tasks'];

    protected $fillable = ['name', 'list', 'sort_order'];

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
        return $this->hasMany(Task::class, 'group_id')->whereNull('project_id');
    }

    /** Groups that are still groups (not turned into a project). */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)->whereNull('project_id');
    }
}
