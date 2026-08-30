<?php

namespace App\Models;

use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, user-configured category (e.g. "Schule", "Training", "Arbeiten").
 * A schedule event or template can point to one instead of carrying its own
 * free-text title/colour — renaming or recolouring a category live-updates
 * every block that references it, past and future. Optionally drives the
 * dashboard's Pomodoro focus timer.
 */
class EventCategory extends Model
{
    /** @use HasFactory<EventCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'pomodoro_enabled',
        'sort_order',
        'task_source',
        'linked_project_id',
        'linked_group_id',
        'linked_agenda_entry_id',
        'linked_text',
    ];

    protected function casts(): array
    {
        return [
            'pomodoro_enabled' => 'boolean',
            'sort_order' => 'integer',
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

    public function linkedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'linked_project_id');
    }

    public function linkedGroup(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'linked_group_id');
    }

    public function linkedAgendaEntry(): BelongsTo
    {
        return $this->belongsTo(AgendaEntry::class, 'linked_agenda_entry_id');
    }

    /** Individually pinned tasks for task_source = 'tasks', in suggestion order. */
    public function pinnedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'category_task_links', 'category_id', 'task_id')
            ->withPivot('sort_order')
            ->orderBy('category_task_links.sort_order');
    }

    /** Short display label for the current link, shown next to a category in Settings. Null when nothing is linked. */
    public function taskSourceLabel(): ?string
    {
        return match ($this->task_source) {
            'project' => $this->linkedProject?->name,
            'group' => $this->linkedGroup?->name,
            'tasks' => match ($count = $this->pinnedTasks()->count()) {
                0 => 'Keine Aufgabe ausgewählt',
                1 => '1 Aufgabe',
                default => "{$count} Aufgaben",
            },
            'agenda_entry' => $this->linkedAgendaEntry?->title,
            'agenda_generic' => 'Hausaufgaben erledigen',
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
            'project' => $this->linkedProject !== null ? "{$this->linkedProject->name} ist fertig." : null,
            'group' => $this->linkedGroup !== null ? "{$this->linkedGroup->name} ist fertig." : null,
            'tasks' => 'Die ausgewählten Aufgaben sind fertig.',
            'agenda_entry' => $this->linkedAgendaEntry !== null ? "{$this->linkedAgendaEntry->title} ist fertig." : null,
            'agenda_generic' => 'Die Hausaufgaben sind fertig.',
            default => null,
        };
    }

    /**
     * Clears every linked_ column and every pinned task regardless of the
     * target task_source — called before setting a new source so switching
     * target (e.g. project -> text) never leaves a stale FK or leftover pins
     * behind.
     */
    public function clearTaskLink(): void
    {
        $this->pinnedTasks()->detach();
        $this->update([
            'task_source' => null,
            'linked_project_id' => null,
            'linked_group_id' => null,
            'linked_agenda_entry_id' => null,
            'linked_text' => null,
        ]);
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
