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
        'only_for_module_users',
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
            'only_for_module_users' => 'boolean',
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
     * Every module an announcement can be scoped to (see only_for_module_users
     * below) — AppModules::CATALOG plus Planer, which deliberately isn't part
     * of that catalog (it has its own dedicated `planner_enabled` toggle
     * rather than being hideable through the same "Module" settings card —
     * see AppModules's own docblock on why a feature with its own on/off
     * switch never gets a second one there). Kept in one place so
     * linkableModules(), isScopableModule() and the route→module reverse
     * lookup below can never disagree about which modules exist.
     *
     * @return array<string, array{label: string, route: string}>
     */
    public static function scopableModules(): array
    {
        return AppModules::CATALOG + [
            'planner' => ['label' => 'Planer', 'route' => 'planner'],
        ];
    }

    /**
     * Every internal page an announcement can link to — a superset of
     * scopableModules(), which itself excludes the Board and Settings (they
     * must always stay reachable via navigation, see AppModules's own
     * docblock). An announcement isn't bound by that "always reachable"
     * constraint, so it can point at either of those two as well — though
     * neither is ever scopable (see isScopableModule()), since everyone
     * always uses them.
     *
     * @return array<string, array{label: string, route: string}>
     */
    public static function linkableModules(): array
    {
        return self::scopableModules() + [
            'settings' => ['label' => 'Einstellungen', 'route' => 'settings'],
            'app' => ['label' => 'Board', 'route' => 'app'],
        ];
    }

    /**
     * Whether $moduleKey has a genuine "hasn't used it" state worth scoping
     * an announcement's audience around. The Board and Settings are always
     * on for everyone, so offering the "only for module users" toggle there
     * would be a no-op — never offered for those two.
     */
    public static function isScopableModule(string $moduleKey): bool
    {
        return array_key_exists($moduleKey, self::scopableModules());
    }

    /**
     * The scopable-module key whose page this route name belongs to, or null
     * if the route isn't one of them — the reverse of scopableModules()'s own
     * `route` field. Used by App\Http\Middleware\RecordModuleVisit to decide
     * whether the current request is a visit worth recording, without that
     * middleware needing its own copy of the module→route map.
     */
    public static function moduleKeyForRouteName(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::scopableModules() as $key => $meta) {
            if ($meta['route'] === $routeName) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether $user has ever visited $moduleKey's page (see ModuleVisit /
     * RecordModuleVisit) — the real usage signal behind only_for_module_users,
     * deliberately not a settings/visibility check: a module can be enabled
     * and still never opened, and this is specifically about the latter.
     */
    public static function isModuleInUseBy(User $user, string $moduleKey): bool
    {
        return ModuleVisit::query()
            ->where('user_id', $user->id)
            ->where('module_key', $moduleKey)
            ->exists();
    }

    /**
     * Every scopable module key $user has never visited — the complement of
     * isModuleInUseBy() across the whole catalog, computed once per user so
     * scopeUnseenBy() can filter with a single whereNotIn() instead of one
     * exists() query per row.
     *
     * @return list<string>
     */
    protected static function modulesNotInUseBy(User $user): array
    {
        $visited = ModuleVisit::query()->where('user_id', $user->id)->pluck('module_key')->all();

        return array_values(array_diff(array_keys(self::scopableModules()), $visited));
    }

    /**
     * How many people in total, and how many of them have ever visited
     * $moduleKey's page — feeds the admin editor's live reach estimate so
     * "only for module users" is a real number, not a guess, before
     * publishing. A plain count of module_visits rows for this key already
     * is the distinct-visitor count, since (user_id, module_key) is unique.
     *
     * @return array{total: int, inUse: int}
     */
    public static function moduleReachCounts(string $moduleKey): array
    {
        return [
            'total' => User::query()->count(),
            'inUse' => ModuleVisit::query()->where('module_key', $moduleKey)->count(),
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
        $notInUse = self::modulesNotInUseBy($user);

        return $query->published()
            ->where('published_at', '>=', $user->created_at)
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->id))
            // Only a row that's both scoped (only_for_module_users) *and*
            // tied to a module this user hasn't visited gets excluded here —
            // whereNull guards a (should-never-happen, but validated nowhere
            // at the DB level) scoped row with no related_module at all, so
            // it fails open rather than silently vanishing for everyone.
            ->when($notInUse !== [], fn (Builder $q) => $q->where(function (Builder $q2) use ($notInUse) {
                $q2->where('only_for_module_users', false)
                    ->orWhereNull('related_module')
                    ->orWhereNotIn('related_module', $notInUse);
            }))
            ->orderBy('published_at')
            ->orderBy('id');
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
