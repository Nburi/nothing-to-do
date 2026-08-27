<?php

namespace App\Services;

use App\Models\User;

/**
 * The catalog of optional feature pages a user can hide from navigation, plus
 * which page opens by default when the app launches. First step of a wider
 * onboarding/accessibility push (see CLAUDE.md) — a classmate who only cares
 * about the shared class Agenda should be able to declutter everything else
 * and land straight on it. Deliberately built as a stateless catalog (mirrors
 * HeaderBadges::CATALOG) rather than a hardcoded enum scattered across the
 * layout, so a later step (a tutorial that should only cover visible modules,
 * an admin-authored feature announcement) has one place to ask "is this
 * module visible" / "what modules exist" instead of re-deriving it.
 *
 * The Board (route 'app') and Settings are never in this catalog — they're
 * the app's core surface (Quick Capture's default target, the only place
 * Inbox/Projects/Groups live) and always the safe fallback, so hiding them
 * would strand the user with no way back in.
 *
 * A feature that ships its own dedicated on/off toggle (e.g. one gated
 * behind a default-off `*_enabled` column, explained inline in Settings)
 * belongs there, not duplicated here as a second switch. This catalog is
 * only for pages that were previously always-on and are only now becoming
 * optional.
 */
class AppModules
{
    /**
     * label = Settings row label. description = one line explaining what
     * hiding it does. route = the route name its nav entries and its
     * default-page choice point at.
     *
     * @var array<string, array{label: string, description: string, route: string}>
     */
    public const CATALOG = [
        'prepare' => [
            'label' => 'Vorbereiten',
            'description' => 'Der Abend-/Morgen-Ritual-Screen zum Planen des nächsten Tages.',
            'route' => 'prepare',
        ],
        'schedule' => [
            'label' => 'Zeitplan',
            'description' => 'Der Tages-/Wochen-Zeitplan mit Terminen, Kategorien und Pomodoro.',
            'route' => 'schedule',
        ],
        'weekplan' => [
            'label' => 'Wochenplan & Ferien',
            'description' => 'Die wiederkehrende Wochenvorlage und das Pausieren einzelner Tage.',
            'route' => 'weekplan',
        ],
        'agenda' => [
            'label' => 'Agenda',
            'description' => 'Hausaufgaben und Prüfungen, privat oder mit einer Klasse geteilt.',
            'route' => 'agenda',
        ],
        'crafts' => [
            'label' => 'Bastelideen',
            'description' => 'Die Liste für „was mache ich, wenn mir langweilig ist".',
            'route' => 'crafts',
        ],
        'emergency' => [
            'label' => 'Notfallmodus',
            'description' => 'Ein Projekt kurzfristig priorisieren, wenn es fast fällig ist.',
            'route' => 'emergency',
        ],
        'progress' => [
            'label' => 'Fortschritt',
            'description' => 'Serie, Tagesziel und die Heatmap erledigter Aufgaben.',
            'route' => 'progress',
        ],
    ];

    /**
     * Whether a catalog module is currently visible for this user. Any key
     * outside the catalog (the board, settings, an unknown/future key) is
     * always visible — this only ever hides something explicitly listed
     * above.
     */
    public static function isVisible(User $user, string $key): bool
    {
        if (! array_key_exists($key, self::CATALOG)) {
            return true;
        }

        return ! in_array($key, self::hiddenKeys($user), true);
    }

    /**
     * @return list<string>
     */
    public static function hiddenKeys(User $user): array
    {
        return is_array($user->hidden_modules) ? array_values($user->hidden_modules) : [];
    }

    /**
     * The catalog, in a fixed order, each row carrying whether the user
     * currently has it hidden — what Settings' "Module" card renders.
     *
     * @return list<array{key: string, label: string, description: string, hidden: bool}>
     */
    public static function rowsFor(User $user): array
    {
        $hidden = self::hiddenKeys($user);

        return collect(self::CATALOG)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'hidden' => in_array($key, $hidden, true),
            ])
            ->values()
            ->all();
    }

    /**
     * Every page the user could pick as their default landing page right
     * now: the board (always first, always available), then every catalog
     * module that isn't hidden.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function landingPageOptions(User $user): array
    {
        $options = [['key' => 'app', 'label' => 'Board (Startseite)']];

        foreach (self::CATALOG as $key => $meta) {
            if (self::isVisible($user, $key)) {
                $options[] = ['key' => $key, 'label' => $meta['label']];
            }
        }

        return $options;
    }

    /**
     * Whether $key is a value defaultLandingRouteName() can currently
     * resolve to something other than the board fallback — used both by
     * User::defaultLandingRouteName() and by Settings::setDefaultPage() so
     * the two never disagree about what's a valid choice.
     */
    public static function isValidLandingPage(User $user, string $key): bool
    {
        if ($key === 'app') {
            return true;
        }

        // Deliberately not just isVisible(): that treats an unknown key as
        // "always visible" (nothing to hide), which is right for isVisible's
        // own job but wrong here — a garbage/removed key must never validate
        // as a landing page choice.
        return array_key_exists($key, self::CATALOG) && self::isVisible($user, $key);
    }
}
