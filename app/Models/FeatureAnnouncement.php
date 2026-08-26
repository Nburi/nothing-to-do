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
    /**
     * The four announcement flavours an admin can pick, each with its own
     * Topografie tone and short badge label for the toast's top-left pill.
     * 'release' reuses forest (this app's "go/positive" tone) and was the
     * toast's only look before this catalog existed; 'warning' reuses signal
     * for the same reason a warning should read as urgent, not merely
     * informative; 'maintenance' uses contour (already this app's
     * "something time-bound" tone, see the deadline-strip chips); 'info' is
     * deliberately toneless — the same neutral bg-line/text-ink-soft look
     * the editor's own "Entwurf" badge already uses — since it's the
     * calmest, most common case.
     *
     * @var array<string, array{label: string, tone: string, badge_label: string}>
     */
    public const TYPES = [
        'info' => ['label' => 'Info', 'tone' => 'ink', 'badge_label' => 'Neu'],
        'maintenance' => ['label' => 'Wartungsarbeiten', 'tone' => 'contour', 'badge_label' => 'Wartung'],
        'warning' => ['label' => 'Warnung', 'tone' => 'signal', 'badge_label' => 'Warnung'],
        'release' => ['label' => 'Release', 'tone' => 'forest', 'badge_label' => 'Release'],
    ];

    public const DEFAULT_TYPE = 'info';

    protected $fillable = [
        'title',
        'description',
        'type',
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

    /** This announcement's TYPES entry, falling back to the default for a stale/unknown value. */
    public function typeMeta(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::DEFAULT_TYPE];
    }

    public function typeLabel(): string
    {
        return $this->typeMeta()['label'];
    }

    public function typeBadgeLabel(): string
    {
        return $this->typeMeta()['badge_label'];
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

    /**
     * Published entries this user hasn't dismissed yet, oldest-published
     * first — and never anything published before the user's own
     * registration. Without that second condition, a brand-new account
     * would be handed the entire backlog of past announcements the moment
     * they first log in; an existing user is unaffected, since their
     * created_at predates virtually every announcement anyway. The
     * comparison is `>=`, not `>`, so a user who registers in the same
     * instant something is published still sees it — a real case (an
     * existing user browsing right as an admin publishes), not an
     * edge case to guard against.
     */
    public function scopeUnseenBy(Builder $query, User $user): Builder
    {
        return $query->published()
            ->where('published_at', '>=', $user->created_at)
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->id))
            ->orderBy('published_at')
            ->orderBy('id');
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
