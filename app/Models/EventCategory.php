<?php

namespace App\Models;

use App\Planning\Category;
use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable, user-configured category (e.g. "Schule", "Training", "Arbeiten").
 * A schedule event or template can point to one instead of carrying its own
 * free-text title/colour — renaming or recolouring a category live-updates
 * every block that references it, past and future. Optionally drives the
 * dashboard's Pomodoro focus timer.
 */
class EventCategory extends Model
{
    /** `task_source` values the pick engine understands (step 5.4): suggest the next steps of a project or group. */
    public const SOURCE_PROJECT = 'project';

    public const SOURCE_GROUP = 'group';

    /** @use HasFactory<EventCategoryFactory> */
    use HasFactory;

    // Step 4.1: deleting is undoable. A deleted category drops out of every list and the engine;
    // its blocks keep their stored name and colour and no longer count as work time.
    use SoftDeletes;

    protected $fillable = [
        'name',
        'color',
        'pomodoro_enabled',
        'sort_order',
        'task_source',
        'linked_text',
    ];

    protected function casts(): array
    {
        return [
            'pomodoro_enabled' => 'boolean',
            'sort_order' => 'integer',
            'linked_project_id' => 'integer',
            'linked_group_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ScheduleEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class, 'category_id');
    }

    /** @return HasMany<EventTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(EventTemplate::class, 'category_id');
    }

    /**
     * User-defined custom fields for this category (e.g. "Trainingstyp",
     * "Dauer") — see CategoryAttribute. Named "customAttributes", not
     * "attributes", to stay clear of Eloquent's own internal $attributes
     * property.
     *
     * @return HasMany<CategoryAttribute, $this>
     */
    public function customAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class, 'event_category_id')->ordered();
    }

    /** Individually pinned tasks for task_source = 'tasks', in suggestion order. */
    public function pinnedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'category_task_links', 'category_id', 'task_id')
            ->withPivot('sort_order')
            ->orderBy('category_task_links.sort_order');
    }

    /**
     * Moves one pinned task a step earlier ('up') or later ('down') in suggestion order — a no-op
     * at either end or for a task that isn't pinned. Rewrites the whole pivot order so sort_order
     * stays a clean 0..n-1 sequence, whatever gaps earlier detaches left behind.
     */
    public function movePinnedTask(int $taskId, string $direction): void
    {
        $ids = $this->pinnedTasks()->pluck('tasks.id')->all();
        $index = array_search($taskId, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->pinnedTasks()->sync(
            collect($ids)->mapWithKeys(fn (int $id, int $order) => [$id => ['sort_order' => $order]])->all()
        );
    }

    /**
     * Points the category at a project or a group, or clears that (step 5.4): in its blocks the pick engine
     * then suggests the next steps of it first (after deadline work). Nothing else is deleted: a text hint
     * or pinned tasks stay stored, they are just no longer the active source. Clearing only touches a
     * project or group link.
     *
     * @param  'project'|'group'|null  $kind  null clears
     */
    public function linkSteps(?string $kind, ?int $id): void
    {
        if ($kind === null || $id === null) {
            if (in_array($this->task_source, [self::SOURCE_PROJECT, self::SOURCE_GROUP], true)) {
                $this->forceFill(['task_source' => null, 'linked_project_id' => null, 'linked_group_id' => null])->save();
            }

            return;
        }

        $this->forceFill([
            'task_source' => $kind === self::SOURCE_PROJECT ? self::SOURCE_PROJECT : self::SOURCE_GROUP,
            'linked_project_id' => $kind === self::SOURCE_PROJECT ? $id : null,
            'linked_group_id' => $kind === self::SOURCE_PROJECT ? null : $id,
        ])->save();
    }

    /** "project:12", "group:5" or "" for the link select of the category editor. */
    public function stepsLinkValue(): string
    {
        return match (true) {
            $this->task_source === self::SOURCE_PROJECT && $this->linked_project_id !== null => 'project:'.$this->linked_project_id,
            $this->task_source === self::SOURCE_GROUP && $this->linked_group_id !== null => 'group:'.$this->linked_group_id,
            default => '',
        };
    }

    /** Short display label for the current link, shown next to a category in Settings. Null when nothing is linked. */
    public function taskSourceLabel(): ?string
    {
        return match ($this->task_source) {
            'tasks' => match ($count = $this->pinnedTasks()->count()) {
                0 => 'Keine Aufgabe ausgewählt',
                1 => '1 Aufgabe',
                default => "{$count} Aufgaben",
            },
            'text' => $this->linked_text,
            default => null,
        };
    }

    /**
     * The quiet "list just finished" notice text for the focus card (Signature
     * Moment: TaskBoard::linkedSourceNotice() only calls this once the linked
     * source's remaining count has actually hit 0) — null for 'text' and for
     * a link whose target was deleted, since neither has a real "done" state.
     */
    public function taskSourceFinishedMessage(): ?string
    {
        return match ($this->task_source) {
            'tasks' => 'Die ausgewählten Aufgaben sind fertig.',
            default => null,
        };
    }

    /**
     * Clears every linked_ column and every pinned task regardless of the
     * target task_source — called before setting a new source so switching
     * target (e.g. pinned tasks -> text) never leaves a stale FK or leftover pins
     * behind.
     */
    public function clearTaskLink(): void
    {
        $this->pinnedTasks()->detach();
        $this->update([
            'task_source' => null,
            'linked_text' => null,
        ]);
    }

    /**
     * The task areas that may be suggested inside this category's blocks
     * (REQUIREMENTS § 4, category ↔ area link). Empty = only tasks without an
     * area (engine decision E5).
     *
     * @return BelongsToMany<Area, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'category_area')->withTimestamps();
    }

    /**
     * Replace the linked areas. Ids that are not the category owner's own areas
     * are dropped silently — a link can never point into another account.
     *
     * @param  list<int>  $areaIds
     */
    public function syncAreas(array $areaIds): void
    {
        $owned = Area::query()
            ->where('user_id', $this->user_id)
            ->whereIn('id', array_map('intval', $areaIds))
            ->pluck('id')
            ->all();

        $this->areas()->sync($owned);
        $this->unsetRelation('areas');
    }

    /** This category as the planning engine sees it (App\Planning). */
    public function toPlanningCategory(): Category
    {
        return new Category(
            id: $this->id,
            pomodoro: (bool) $this->pomodoro_enabled,
            areaIds: $this->areas->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            name: $this->name,
            linkedProjectId: $this->task_source === self::SOURCE_PROJECT ? $this->linked_project_id : null,
            linkedGroupId: $this->task_source === self::SOURCE_GROUP ? $this->linked_group_id : null,
        );
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
