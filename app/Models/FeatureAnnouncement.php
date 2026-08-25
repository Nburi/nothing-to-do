<?php

namespace App\Models;

use App\Services\AppModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An admin-authored "here's what's new" entry (see CLAUDE.md, Feature-Ankündigungen).
 * Shown once per user, in App\Livewire\FeatureAnnouncementToast, until they dismiss
 * it — "seen" lives in the feature_announcement_dismissals pivot, one row per
 * (announcement, person), the same shape agenda_entry_completions already uses for
 * "done" on a shared Agenda entry.
 */
class FeatureAnnouncement extends Model
{
    protected $fillable = [
        'title',
        'description',
        'related_module',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Every user who has dismissed this announcement. */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feature_announcement_dismissals')->withTimestamps();
    }

    /** The catalog label for related_module, or null if this announcement isn't tied to one. */
    public function relatedModuleLabel(): ?string
    {
        return $this->related_module !== null
            ? (AppModules::CATALOG[$this->related_module]['label'] ?? null)
            : null;
    }

    /** The route the "Ansehen" link points at, or null if there's nothing to link to. */
    public function relatedRouteName(): ?string
    {
        return $this->related_module !== null
            ? (AppModules::CATALOG[$this->related_module]['route'] ?? null)
            : null;
    }

    public function isDismissedBy(User $user): bool
    {
        return $this->dismissedBy()->whereKey($user->id)->exists();
    }

    /** Dismissing twice is a no-op, never a second row (see the pivot's own unique index). */
    public function dismissFor(User $user): void
    {
        $this->dismissedBy()->syncWithoutDetaching([$user->id]);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Published entries this user hasn't dismissed yet, oldest-published first. */
    public function scopeUnseenBy(Builder $query, User $user): Builder
    {
        return $query->published()
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->id))
            ->orderBy('published_at')
            ->orderBy('id');
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
