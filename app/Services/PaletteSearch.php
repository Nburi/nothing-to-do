<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\HelpArticle;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;

/**
 * The read side of the command palette (Strg/⌘+K): one query string in, a few
 * small groups of jump targets out — pages, tasks, projects, groups, agenda
 * entries, Bastelideen and help articles. Stateless like
 * ProgressStats/TaskSuggestor; the Livewire component is only a thin wrapper.
 *
 * Every read goes through the same ownership/visibility scope the rest of the
 * app uses for that model (forUser / visibleTo), so the palette can never
 * surface something the user couldn't already reach by navigating. A hidden
 * module (AppModules) contributes neither its page nor its content — the
 * palette follows "zero footprint when off" like nav links and header badges.
 */
class PaletteSearch
{
    /** Per-group cap: a palette is for jumping, not for browsing a whole list. */
    public const LIMIT = 5;

    /**
     * Fixed navigation targets. `module` ties an entry to an AppModules key
     * (hidden module → hidden entry), `keywords` are extra words that should
     * find it ("streak" → Fortschritt) beyond its label.
     *
     * @var list<array{label: string, route: string, keywords: string, module?: string, admin?: bool}>
     */
    private const PAGES = [
        ['label' => 'Board', 'route' => 'app', 'keywords' => 'aufgaben tasks todos inbox start'],
        ['label' => 'Tagesüberblick', 'route' => 'today', 'keywords' => 'heute tag überblick morgen'],
        ['label' => 'Zeitplan', 'route' => 'schedule', 'keywords' => 'kalender termine pomodoro fokus', 'module' => 'schedule'],
        ['label' => 'Wochenplan & Ferien', 'route' => 'weekplan', 'keywords' => 'woche vorlage pause', 'module' => 'weekplan'],
        ['label' => 'Planer', 'route' => 'planner', 'keywords' => 'plan tage verteilen'],
        ['label' => 'Vorbereiten', 'route' => 'prepare', 'keywords' => 'morgen abend ritual', 'module' => 'prepare'],
        ['label' => 'Agenda', 'route' => 'agenda', 'keywords' => 'hausaufgaben prüfungen schule klasse', 'module' => 'agenda'],
        ['label' => 'Bastelideen', 'route' => 'crafts', 'keywords' => 'langweilig ideen', 'module' => 'crafts'],
        ['label' => 'Notfallmodus', 'route' => 'emergency', 'keywords' => 'projekt dringend', 'module' => 'emergency'],
        ['label' => 'Fortschritt', 'route' => 'progress', 'keywords' => 'serie streak heatmap tagesziel statistik', 'module' => 'progress'],
        ['label' => 'Einstellungen', 'route' => 'settings', 'keywords' => 'settings konto zeitzone benachrichtigungen kategorien pomodoro token api'],
        ['label' => 'Hilfe', 'route' => 'help', 'keywords' => 'anleitung docs faq'],
        ['label' => 'Support & Feedback', 'route' => 'support', 'keywords' => 'kontakt fehler melden'],
        ['label' => 'Tutorial', 'route' => 'onboarding', 'keywords' => 'einführung onboarding erklärung'],
        ['label' => 'Ankündigungen verwalten', 'route' => 'admin.announcements', 'keywords' => 'admin', 'admin' => true],
        ['label' => 'Hilfe-Center verwalten', 'route' => 'admin.help', 'keywords' => 'admin', 'admin' => true],
        ['label' => 'Support-Anfragen', 'route' => 'admin.support', 'keywords' => 'admin', 'admin' => true],
        ['label' => 'Fehler-Statistiken', 'route' => 'admin.errors', 'keywords' => 'admin', 'admin' => true],
    ];

    /**
     * @return list<array{key: string, label: string, items: list<array{title: string, subtitle: string, href: string, icon: string}>}>
     */
    public static function search(User $user, string $query): array
    {
        $query = trim($query);

        // An empty query shows the page list only — a palette that opens onto a
        // blank box makes the user guess what it's for.
        $groups = [
            ['key' => 'pages', 'label' => 'Seiten', 'items' => self::pages($user, $query)],
        ];

        if ($query !== '') {
            $groups[] = ['key' => 'tasks', 'label' => 'Aufgaben', 'items' => self::tasks($user, $query)];
            $groups[] = ['key' => 'projects', 'label' => 'Projekte', 'items' => self::projects($user, $query)];
            $groups[] = ['key' => 'groups', 'label' => 'Gruppen', 'items' => self::groups($user, $query)];

            if (AppModules::isVisible($user, 'agenda')) {
                $groups[] = ['key' => 'agenda', 'label' => 'Agenda', 'items' => self::agenda($user, $query)];
            }

            if (AppModules::isVisible($user, 'crafts')) {
                $groups[] = ['key' => 'crafts', 'label' => 'Bastelideen', 'items' => self::crafts($user, $query)];
            }

            $groups[] = ['key' => 'help', 'label' => 'Hilfe', 'items' => self::help($query)];
        }

        return array_values(array_filter($groups, fn (array $g) => $g['items'] !== []));
    }

    /**
     * Escape LIKE wildcards in what the user typed: searching for "50%" or
     * "a_b" must match those literal characters, not "everything". "!" is the
     * escape character rather than the usual backslash because SQLite has no
     * default escape character at all and MySQL treats backslash specially
     * inside string literals — "!" behaves the same on both once it is named
     * explicitly (see the ESCAPE clause in matches()).
     */
    public static function like(string $query): string
    {
        return '%'.preg_replace('/[!%_]/', '!$0', $query).'%';
    }

    /** A `column LIKE ? ESCAPE '!'` constraint — pair with like(). */
    private static function matches($builder, string $column, string $query, string $boolean = 'and')
    {
        return $builder->whereRaw("{$column} LIKE ? ESCAPE '!'", [self::like($query)], $boolean);
    }

    private static function pages(User $user, string $query): array
    {
        $needle = mb_strtolower($query);
        $items = [];

        foreach (self::PAGES as $page) {
            if (($page['admin'] ?? false) && ! $user->is_admin) {
                continue;
            }

            $module = $page['module'] ?? null;

            // Notfallmodus stays reachable while an emergency is actually
            // running, even if the module was hidden earlier — same exception
            // the nav makes, so hiding it can never strand the user mid-emergency.
            $visible = $module === null
                || AppModules::isVisible($user, $module)
                || ($module === 'emergency' && $user->isInEmergencyMode());

            if (! $visible) {
                continue;
            }

            // The Planer has its own dedicated toggle instead of a module key.
            if ($page['route'] === 'planner' && ! $user->planner_enabled) {
                continue;
            }

            if ($needle !== '' && ! str_contains(mb_strtolower($page['label'].' '.$page['keywords']), $needle)) {
                continue;
            }

            $items[] = [
                'title' => $page['label'],
                'subtitle' => '',
                'href' => route($page['route']),
                'icon' => 'page',
            ];
        }

        return $items;
    }

    private static function tasks(User $user, string $query): array
    {
        return Task::query()
            ->forUser($user)
            ->active()
            ->tap(fn ($q) => self::matches($q, 'title', $query))
            ->orderByDesc('is_today')
            ->orderByDesc('is_important')
            ->limit(self::LIMIT)
            ->get(['id', 'title', 'list', 'project_id', 'group_id', 'is_today'])
            ->map(function (Task $task) {
                // A task that lives inside a project or a group has no card on
                // the board — send the user to the page that actually shows it.
                $href = match (true) {
                    $task->project_id !== null => route('project.show', $task->project_id),
                    $task->group_id !== null => route('group.show', $task->group_id),
                    default => route('app', ['task' => $task->id]),
                };

                $subtitle = match (true) {
                    $task->project_id !== null => 'Projekt',
                    $task->group_id !== null => 'Gruppe',
                    default => ['inbox' => 'Inbox', 'todos' => 'To-Do', 'tasks' => 'Task'][$task->list] ?? '',
                };

                if ($task->is_today) {
                    $subtitle = trim($subtitle.' · Heute', ' ·');
                }

                return ['title' => $task->title, 'subtitle' => $subtitle, 'href' => $href, 'icon' => 'task'];
            })
            ->all();
    }

    private static function projects(User $user, string $query): array
    {
        return Project::query()
            ->forUser($user)
            ->tap(fn ($q) => self::matches($q, 'name', $query))
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Project $p) => [
                'title' => $p->name,
                'subtitle' => '',
                'href' => route('project.show', $p->id),
                'icon' => 'project',
            ])
            ->all();
    }

    private static function groups(User $user, string $query): array
    {
        return TaskGroup::query()
            ->forUser($user)
            ->tap(fn ($q) => self::matches($q, 'name', $query))
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (TaskGroup $g) => [
                'title' => $g->name,
                'subtitle' => '',
                'href' => route('group.show', $g->id),
                'icon' => 'group',
            ])
            ->all();
    }

    private static function agenda(User $user, string $query): array
    {
        return AgendaEntry::query()
            ->visibleTo($user)
            ->openFor($user)
            ->where(function ($q) use ($query) {
                self::matches($q, 'title', $query);
                self::matches($q, 'subject', $query, 'or');
            })
            ->orderBy('date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AgendaEntry $e) => [
                'title' => $e->title,
                'subtitle' => trim($e->subject.' · '.$e->dateLabel(), ' ·'),
                'href' => route('agenda'),
                'icon' => 'agenda',
            ])
            ->all();
    }

    private static function crafts(User $user, string $query): array
    {
        return CraftIdea::query()
            ->forUser($user)
            ->open()
            ->tap(fn ($q) => self::matches($q, 'title', $query))
            ->limit(self::LIMIT)
            ->get(['id', 'title'])
            ->map(fn (CraftIdea $c) => [
                'title' => $c->title,
                'subtitle' => '',
                'href' => route('crafts'),
                'icon' => 'craft',
            ])
            ->all();
    }

    private static function help(string $query): array
    {
        return HelpArticle::query()
            ->published()
            ->tap(fn ($q) => self::matches($q, 'title', $query))
            ->limit(self::LIMIT)
            ->get(['id', 'title'])
            ->map(fn (HelpArticle $a) => [
                'title' => $a->title,
                'subtitle' => '',
                'href' => route('help', $a->id),
                'icon' => 'help',
            ])
            ->all();
    }
}
