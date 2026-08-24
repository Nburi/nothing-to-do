<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;

/**
 * The header's badge row: small, ambient shortcuts (a count or a snippet plus
 * a link) that only take up space when they actually have something to show
 * — same "no sad 0/empty state" rule the streak badge already followed
 * before this existed (see layouts/app.blade.php pre-refactor). Two entry
 * points:
 *
 *  - preferenceRowsFor() — every catalog key, in the user's order, with its
 *    enabled flag: what Settings renders and lets the user drag/toggle.
 *  - visibleFor() — only the enabled rows that currently have content to
 *    show, resolved and ready for the header: what layouts/app.blade.php loops over.
 *
 * A user who has never touched Settings has header_badges === null, which
 * means "use the catalog's own DEFAULT_ENABLED selection" — not an empty
 * header. Once they save any change, their own {key, enabled} list is
 * stored and used verbatim (missing/new catalog keys are appended, disabled,
 * so a later addition to CATALOG never silently reactivates itself inside an
 * already-customised list).
 */
class HeaderBadges
{
    /** Catalog keys enabled for anyone who hasn't customised header_badges yet. */
    public const DEFAULT_ENABLED = ['streak', 'agenda'];

    /**
     * label = Settings row label. route = the Livewire route name a click
     * navigates to. tone = the Topografie colour token used for a non-streak
     * badge's border/background (streak computes its own tiered tone,
     * unchanged from before this feature).
     *
     * @var array<string, array{label: string, route: string, tone: string}>
     */
    public const CATALOG = [
        'streak' => ['label' => 'Serie', 'route' => 'progress', 'tone' => 'ink'],
        'agenda' => ['label' => 'Agenda', 'route' => 'agenda', 'tone' => 'ink'],
        'today' => ['label' => 'Heute offen', 'route' => 'app', 'tone' => 'ink'],
        'schedule' => ['label' => 'Zeitplan', 'route' => 'schedule', 'tone' => 'ink'],
        'goal' => ['label' => 'Tagesziel', 'route' => 'progress', 'tone' => 'ink'],
        'emergency' => ['label' => 'Notfall', 'route' => 'emergency', 'tone' => 'signal'],
    ];

    /**
     * Every catalog key in the user's chosen order with its enabled flag —
     * for Settings' drag/toggle list. Stored rows come first, in their
     * stored order; any catalog key missing from a stored list (either
     * because the user never customised anything, or because a key was
     * added to the catalog after they did) is appended at the end, disabled
     * — visible in Settings to opt into, never silently active in the header.
     *
     * @return list<array{key: string, enabled: bool}>
     */
    public static function preferenceRowsFor(User $user): array
    {
        $stored = is_array($user->header_badges) ? $user->header_badges : null;

        $rows = [];
        $seen = [];

        if ($stored !== null) {
            foreach ($stored as $row) {
                $key = $row['key'] ?? null;

                if (! is_string($key) || ! array_key_exists($key, self::CATALOG) || isset($seen[$key])) {
                    continue;
                }

                $rows[] = ['key' => $key, 'enabled' => (bool) ($row['enabled'] ?? false)];
                $seen[$key] = true;
            }
        }

        foreach (array_keys(self::CATALOG) as $key) {
            if (isset($seen[$key])) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'enabled' => $stored === null && in_array($key, self::DEFAULT_ENABLED, true),
            ];
        }

        return $rows;
    }

    /**
     * The enabled rows, in order, resolved to header content — a row is
     * dropped entirely (not shown as empty/zero) when its resolver finds
     * nothing to point at right now.
     *
     * @return list<array{key: string, label: string, route: string, tone: string, icon: string, text: string, title: string, href: string}>
     */
    public static function visibleFor(User $user): array
    {
        $badges = [];

        foreach (self::preferenceRowsFor($user) as $row) {
            if (! $row['enabled']) {
                continue;
            }

            $resolved = self::resolve($row['key'], $user);

            if ($resolved !== null) {
                $badges[] = $resolved;
            }
        }

        return $badges;
    }

    /**
     * @return array{key: string, label: string, route: string, tone: string, icon: string, text: string, title: string, href: string}|null
     */
    private static function resolve(string $key, User $user): ?array
    {
        return match ($key) {
            'streak' => self::streakBadge($user),
            'agenda' => self::agendaBadge($user),
            'today' => self::todayBadge($user),
            'schedule' => self::scheduleBadge($user),
            'goal' => self::goalBadge($user),
            'emergency' => self::emergencyBadge($user),
            default => null,
        };
    }

    private static function streakBadge(User $user): ?array
    {
        $streak = ProgressStats::currentStreak($user);

        if ($streak <= 0) {
            return null;
        }

        // The streak badge keeps its own pre-existing tiered colour escalation
        // (capped at forest — signal is this app's warning colour and stays
        // reserved for that) instead of the flat 'tone' every other badge
        // uses; the partial special-cases 'streak' and reads this field.
        return self::badge('streak', (string) $streak,
            $streak === 1 ? '1 Tag Serie — Fortschritt ansehen' : $streak.' Tage Serie — Fortschritt ansehen',
            [], ['tier' => ProgressStats::streakTier($streak)]);
    }

    private static function agendaBadge(User $user): ?array
    {
        $open = AgendaEntry::visibleTo($user)->openFor($user)->count();

        if ($open <= 0) {
            return null;
        }

        return self::badge('agenda', (string) $open,
            $open === 1 ? '1 offene Hausaufgabe/Prüfung — Agenda ansehen' : $open.' offene Hausaufgaben/Prüfungen — Agenda ansehen');
    }

    private static function todayBadge(User $user): ?array
    {
        $open = Task::forUser($user)->active()->onBoard()->where('is_today', true)->count();

        if ($open <= 0) {
            return null;
        }

        return self::badge('today', (string) $open,
            $open === 1 ? '1 offene Aufgabe für heute — Board ansehen' : $open.' offene Aufgaben für heute — Board ansehen',
            ['tab' => 'today']);
    }

    /**
     * The category block that's running right now, or — failing that — the
     * next one still to come today. Deliberately scoped to today only: a
     * header badge is meant to answer "what's next", not reach into
     * tomorrow's plan (that's what the Zeitplan page itself is for).
     */
    private static function scheduleBadge(User $user): ?array
    {
        $today = $user->localToday();
        ScheduleEvent::materializeRange($user, $today, $today->copy());

        $now = $user->localNow();
        $nowMinutes = $now->hour * 60 + $now->minute;

        $events = ScheduleEvent::forUser($user)->visible()->forDay($today)->ordered()->with('category')->get();

        $current = $events->first(fn (ScheduleEvent $e) => $e->isActive($now));
        $event = $current ?? $events->first(fn (ScheduleEvent $e) => $e->startMinutes() > $nowMinutes);

        if ($event === null) {
            return null;
        }

        $label = $current !== null
            ? 'Jetzt: '.$event->displayTitle().' ('.$event->start_time.'–'.$event->end_time.')'
            : $event->displayTitle().' um '.$event->start_time;

        return self::badge('schedule', $event->start_time, $label.' — Zeitplan ansehen', ['event' => $event->id]);
    }

    private static function goalBadge(User $user): ?array
    {
        $done = ProgressStats::todayCount($user);

        if ($done <= 0) {
            return null;
        }

        $goal = $user->dailyTaskGoal();

        return self::badge('goal', $done.'/'.$goal,
            "$done von $goal Aufgaben heute erledigt — Fortschritt ansehen");
    }

    private static function emergencyBadge(User $user): ?array
    {
        $project = $user->emergencyProject;

        if ($project === null) {
            return null;
        }

        $open = $project->activeTasks()->count();
        $done = $project->tasks()->where('is_completed', true)->count();
        $total = $open + $done;

        return self::badge('emergency', "$done/$total",
            "Notfallmodus „{$project->name}“ — $done von $total Aufgaben erledigt");
    }

    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $extra
     * @return array{key: string, label: string, route: string, tone: string, icon: string, text: string, title: string, href: string}
     */
    private static function badge(string $key, string $text, string $title, array $routeParams = [], array $extra = []): array
    {
        $meta = self::CATALOG[$key];

        return [
            'key' => $key,
            'label' => $meta['label'],
            'route' => $meta['route'],
            'tone' => $meta['tone'],
            'icon' => $key,
            'text' => $text,
            'title' => $title,
            'href' => route($meta['route'], $routeParams),
            ...$extra,
        ];
    }
}
