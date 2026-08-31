<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The catalog of mental models the board can be rendered through — "3 Things"
 * (the original, still the default), plus three more that are planned but not
 * yet built (see PLAN_LIST_CONCEPTS.md). Deliberately built as a stateless
 * catalog (mirrors AppModules::CATALOG/HeaderBadges::CATALOG) rather than a
 * hardcoded switch scattered across TaskBoard, so a later concept session has
 * exactly one array entry + one `@case` to add, nothing else to wire up.
 *
 * Unlike AppModules/HeaderBadges, every catalog entry carries an `available`
 * flag: a concept can be listed (so Settings can show it as "bald verfügbar"
 * and a user knows it's coming) without being selectable yet, because its
 * board partial and TaskBoard computed properties don't exist. `for()`/
 * `isValid()` are the two places that flag is enforced — every other consumer
 * (Settings' row list, the "coming soon" preview) reads it straight off the
 * catalog. A concept session flips its own entry's `available` to `true` in
 * the same commit that adds its `@case` and board partial — never before,
 * since a stored `list_concept` value only ever needs to survive a *later*
 * `available` flip (a value stored while unavailable can't happen, since
 * `setListConcept()` also enforces `isValid()`), so there's no separate
 * migration/backfill step when a concept goes from planned to real.
 */
class ListConcepts
{
    /**
     * label = Settings row label. description = one line explaining the
     * concept's shape. available = whether it can actually be selected right
     * now (its board partial + TaskBoard computed properties exist).
     *
     * @var array<string, array{label: string, description: string, available: bool}>
     */
    public const CATALOG = [
        'three_things' => [
            'label' => '3 Things',
            'description' => 'To-Do, Task oder Projekt — sortiert nach Grösse, mit Inbox-Triage.',
            'available' => true,
        ],
        'simple' => [
            'label' => 'Simple',
            'description' => 'Eine einzige flache Liste. Kein Inbox-Umweg, keine Triage.',
            'available' => false,
        ],
        'eisenhower' => [
            'label' => 'Eisenhower-Matrix',
            'description' => 'Wichtig × Dringend — vier Quadranten statt Listen.',
            'available' => false,
        ],
        'kanban' => [
            'label' => 'Kanban',
            'description' => 'Backlog, In Arbeit, Erledigt — als Spalten statt Listen.',
            'available' => true,
        ],
    ];

    /**
     * The self-healing read every consumer (TaskBoard's render path, the
     * Settings picker's "current" flag) goes through — a stored value left
     * over from a concept that isn't deployed yet, was ever removed, or is
     * simply garbage always falls back to 'three_things' instead of trying to
     * render nothing.
     */
    public static function for(User $user): string
    {
        return self::isValid($user->list_concept) ? $user->list_concept : 'three_things';
    }

    /**
     * Whether $key is something a user could actually be switched to right
     * now — in the catalog *and* available. Used both by
     * Settings::setListConcept() and by for()'s own fallback check, so the
     * two can never disagree about what's a valid choice (same dual-
     * consistency shape as AppModules::isValidLandingPage()).
     */
    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::CATALOG) && self::CATALOG[$key]['available'];
    }

    /**
     * The catalog in a fixed order, each row carrying whether it's the user's
     * current choice — what Settings' "Listen-Konzept" card renders.
     *
     * @return list<array{key: string, label: string, description: string, available: bool, current: bool}>
     */
    public static function rowsFor(User $user): array
    {
        $current = self::for($user);

        return collect(self::CATALOG)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'available' => $meta['available'],
                'current' => $key === $current,
            ])
            ->values()
            ->all();
    }

    /**
     * QuickCapture's one concept-driven hook: which target its panel opens on
     * by default. Every concept except 'simple' (no Inbox-triage step, so
     * capture always writes a real task) wants the existing Inbox default.
     * 'simple' isn't selectable yet (see CATALOG), so this always returns
     * 'inbox' today — the branch exists so the 'simple' concept session has
     * nothing left to wire up here when it lands.
     */
    public static function defaultCaptureList(User $user): string
    {
        return self::for($user) === 'simple' ? 'tasks' : 'inbox';
    }

    /**
     * Shared read behind the Settings picker's signature moment: a handful of
     * the user's own real, currently active board tasks — the same raw
     * material every concept's thumbnail (and, eventually, its real board)
     * renders from, per PLAN_LIST_CONCEPTS.md §3's data-mapping table. Never
     * mock data, so switching concepts always previews *your* list, not a
     * generic sample.
     *
     * @return Collection<int, Task>
     */
    public static function previewTasksFor(User $user, int $limit = 6): Collection
    {
        return Task::query()
            ->forUser($user)
            ->active()
            ->onBoard()
            ->boardOrdered()
            ->limit($limit)
            ->get(['id', 'title', 'list', 'is_important', 'is_today', 'deadline', 'due_date']);
    }
}
