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
        'external_url',
        'external_link_label',
        'highlight_selector',
        'created_by',
        'is_published',
        'published_at',
    ];

    /** Shown on the "ansehen" link/button when no custom label was given. */
    public const DEFAULT_EXTERNAL_LINK_LABEL = 'Mehr erfahren';

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

    /**
     * Every internal page an announcement can link to — a superset of
     * AppModules::CATALOG, which deliberately excludes the Board and
     * Settings (they must always stay reachable via navigation, see that
     * class's own docblock). An announcement isn't bound by that "always
     * reachable" constraint, so it can point at either of those two as well.
     *
     * @return array<string, array{label: string, route: string}>
     */
    public static function linkableModules(): array
    {
        return AppModules::CATALOG + [
            'settings' => ['label' => 'Einstellungen', 'route' => 'settings'],
            'app' => ['label' => 'Board', 'route' => 'app'],
        ];
    }

    /** The catalog label for related_module, or null if this announcement isn't tied to one. */
    public function relatedModuleLabel(): ?string
    {
        return $this->related_module !== null
            ? (self::linkableModules()[$this->related_module]['label'] ?? null)
            : null;
    }

    /** The route the "Ansehen" link points at, or null if there's nothing to link to. */
    public function relatedRouteName(): ?string
    {
        return $this->related_module !== null
            ? (self::linkableModules()[$this->related_module]['route'] ?? null)
            : null;
    }

    /**
     * related_module (an internal page) and external_url are mutually
     * exclusive — enforced by App\Livewire\Admin\AnnouncementEditor::save(),
     * not here. True only once a raw URL is actually set.
     */
    public function isExternalLink(): bool
    {
        return $this->related_module === null && $this->external_url !== null;
    }

    /**
     * The href for this announcement's "ansehen" link, or null when it has
     * none. An internal link carries the highlight_selector along as a
     * `?highlight=` query param, read client-side (see resources/js/app.js)
     * to scroll to and flash a specific element after navigating there —
     * meaningless for an external site, so never added to one.
     */
    public function linkHref(): ?string
    {
        if ($this->related_module !== null) {
            $routeName = $this->relatedRouteName();

            if ($routeName === null) {
                return null;
            }

            $url = route($routeName);

            return $this->highlight_selector
                ? $url.(str_contains($url, '?') ? '&' : '?').'highlight='.urlencode($this->highlight_selector)
                : $url;
        }

        return $this->external_url;
    }

    /** The label for linkHref(), without the trailing "→" (added by the view). */
    public function linkLabel(): ?string
    {
        if ($this->related_module !== null) {
            $moduleLabel = $this->relatedModuleLabel();

            return $moduleLabel !== null ? $moduleLabel.' ansehen' : null;
        }

        if ($this->external_url !== null) {
            return $this->external_link_label ?: self::DEFAULT_EXTERNAL_LINK_LABEL;
        }

        return null;
    }

    /**
     * How many users have dismissed (i.e. seen) this announcement — prefers
     * the eager-loaded withCount('dismissedBy') attribute the admin list
     * query already selects, falling back to a live count anywhere else.
     */
    public function dismissedCount(): int
    {
        return $this->dismissed_by_count ?? $this->dismissedBy()->count();
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
