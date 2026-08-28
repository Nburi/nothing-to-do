<?php

namespace App\Models;

use App\Support\Markdown\Markdown;
use Database\Factories\TaskGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Called right after a task has left this group (moved elsewhere,
     * ungrouped, or deleted) — dissolves the group if that leaves it with one
     * task or none. A bundle of one is not a group, and leaving the user to
     * notice and clean up the leftover shell by hand would just be busywork;
     * the same rule that lets "Gruppe auflösen" release tasks non-destructively
     * applies here (see dissolveGroup() in GroupPage).
     *
     * Callers capture the task's *previous* group before changing/removing it
     * (`$oldGroup = $task->group;`), perform their own update however it needs
     * to happen, then call this on that captured instance — never on `$this`
     * after the fact, since by then group_id has already moved on.
     *
     * @return bool True if the group was dissolved.
     */
    public function pruneIfTooSmall(): bool
    {
        if ($this->tasks()->count() > 1) {
            return false;
        }

        $this->delete(); // cascades group_notes; nullOnDelete releases any last task

        return true;
    }

    /** The group's note cards, in display order. */
    public function notes(): HasMany
    {
        return $this->hasMany(GroupNote::class, 'task_group_id')->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Renders one note's Markdown to safe HTML via the app's shared renderer
     * (see App\Support\Markdown\Markdown) — same capability as every other
     * Markdown field in the app. Static so the live editor can render its
     * preview before anything is saved.
     */
    public static function renderNotes(string $text): string
    {
        return Markdown::toHtml($text);
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
