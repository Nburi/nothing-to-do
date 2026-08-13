<?php

namespace App\Models;

use Database\Factories\TaskGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A bundle of tasks that belong together — the small, multi-step thing that is
 * too big for one task but too small to deserve a Project. A group owns no
 * lists of its own: its tasks keep the same inbox/todos/tasks value every other
 * task has, they just surface inside the group instead of loose on the board.
 */
class TaskGroup extends Model
{
    /** @use HasFactory<TaskGroupFactory> */
    use HasFactory;

    /** Used when a group is created by a gesture that has no name to offer yet. */
    public const DEFAULT_NAME = 'Neue Gruppe';

    protected $fillable = [
        'name',
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

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'group_id');
    }

    /** Active tasks in group order — the working set shown everywhere. */
    public function activeTasks(): HasMany
    {
        return $this->tasks()->active()->groupOrdered();
    }

    /** The group's note cards, in display order. */
    public function notes(): HasMany
    {
        return $this->hasMany(GroupNote::class, 'task_group_id')->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Renders one note's Markdown to safe HTML. Same options as the project
     * brainstorm field and the task notes (raw HTML stripped, unsafe link
     * schemes dropped), so nothing a note contains can inject. Static so the
     * live editor can render its preview before anything is saved.
     */
    public static function renderNotes(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }
}
