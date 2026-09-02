<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Only rendered for a signed-in user; its absence is how the heartbeat
             in app.js knows to stay quiet for guests. --}}
        @auth
            <meta name="presence-url" content="{{ route('presence.heartbeat') }}">
        @endauth
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">
        @include('partials.pwa-head')

        <title>{{ config('app.name', 'nothing-to-do') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    @php
        // A floating "+" belongs where capture is the page's own main action and the
        // bottom-right corner is nothing but scrollable content: the board (it floats
        // above that page's bottom nav) and Bastelideen. Everywhere else the corner is
        // spoken for — the Zeitplan pins its "Zeichnen:" category row to the bottom of
        // a viewport-height grid, which no amount of page padding can scroll clear —
        // and those pages have their own prominent add buttons anyway, so the global
        // capture is a utility action there and belongs in the header.
        $showCaptureFab = request()->routeIs('app')
            || request()->routeIs('crafts')
            || request()->routeIs('agenda');

        // The panel opens on Inbox by default. On a page that is about one specific
        // kind of thing, opening it there should mean that thing — otherwise the
        // button sitting on the Bastelideen page quietly files an Inbox task, and
        // the one on Agenda promises an entry it wouldn't create.
        $captureTarget = match (true) {
            request()->routeIs('crafts') => 'craft',
            request()->routeIs('agenda') => 'agenda',
            request()->routeIs('group.show') => 'group',
            default => null,
        };

        // On a group's own dashboard, "that thing" is not just "a group" but this
        // one — otherwise the panel would open asking for a name for a new group
        // while standing inside an existing one.
        $captureGroup = request()->routeIs('group.show') ? request()->route('group') : null;
        $captureGroupId = $captureGroup instanceof \App\Models\TaskGroup ? $captureGroup->id : null;
    @endphp
    {{-- The capture defaults live on <body> so the keyboard shortcut (N, handled
         globally in app.js) opens the same chip the page's own "+" would. --}}
    <body
        class="min-h-[100dvh] bg-paper font-sans text-ink antialiased"
        @if ($captureTarget) data-capture-target="{{ $captureTarget }}" @endif
        @if ($captureGroupId) data-capture-group="{{ $captureGroupId }}" @endif
    >
        <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-card focus:bg-surface focus:px-4 focus:py-2 focus:shadow-map">
            Zum Inhalt springen
        </a>

        <div class="min-h-[100dvh]">
            <header class="sticky top-0 z-30 border-b border-line bg-paper/85 backdrop-blur-sm">
                <div class="mx-auto flex h-16 max-w-[1400px] items-center justify-between gap-4 px-4 sm:px-6">
                    <a href="{{ url('/app') }}" class="flex items-center gap-2.5" wire:navigate>
                        <x-logo class="h-6 w-6 text-forest" />
                        <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
                    </a>

                    @auth
                        @php
                            $featuresActive = request()->routeIs(['prepare', 'schedule', 'weekplan', 'planner', 'agenda', 'emergency', 'crafts', 'family']);
                            $currentStreak = \App\Services\ProgressStats::currentStreak(auth()->user());
                            $streakTier = \App\Services\ProgressStats::streakTier($currentStreak);

                            // Which "Mehr" entries this user actually gets — Settings' "Module"
                            // card (App\Services\AppModules). Notfall stays reachable while an
                            // emergency is actually running even if the user hid it earlier —
                            // hiding a module must never strand them mid-emergency with no way
                            // to see or end it. When every entry ends up hidden, the whole
                            // "Mehr" button disappears rather than opening onto an empty panel.
                            $showPrepareNav = \App\Services\AppModules::isVisible(auth()->user(), 'prepare');
                            $showScheduleNav = \App\Services\AppModules::isVisible(auth()->user(), 'schedule');
                            $showWeekplanNav = \App\Services\AppModules::isVisible(auth()->user(), 'weekplan');
                            $showAgendaNav = \App\Services\AppModules::isVisible(auth()->user(), 'agenda');
                            $showCraftsNav = \App\Services\AppModules::isVisible(auth()->user(), 'crafts');
                            $showEmergencyNav = \App\Services\AppModules::isVisible(auth()->user(), 'emergency') || auth()->user()->isInEmergencyMode();
                            $showProgressNav = \App\Services\AppModules::isVisible(auth()->user(), 'progress');
                            $showFamilyNav = \App\Services\AppModules::isVisible(auth()->user(), 'family');
                            $anyMehrNavVisible = $showPrepareNav || $showScheduleNav || $showWeekplanNav || $showAgendaNav || $showCraftsNav || $showEmergencyNav || $showFamilyNav;
                        @endphp
                        <div class="flex items-center gap-1.5">
                        {{-- The header badge row — a user-configured, ordered set of ambient
                             shortcuts (Settings' "Header-Badges" card). Each one only takes up
                             space once it actually has something to show — no sad 0/empty state
                             — and disappears entirely otherwise. overflow-x-auto is the safety
                             net for a wide selection on a narrow phone (same pattern as the
                             homework preview strip). See App\Services\HeaderBadges. --}}
                        @php $headerBadges = \App\Services\HeaderBadges::visibleFor(auth()->user()); @endphp
                        @if (count($headerBadges) > 0)
                            <div class="flex max-w-[38vw] items-center gap-1.5 overflow-x-auto sm:max-w-none">
                                @foreach ($headerBadges as $badge)
                                    @include('partials.header-badge', ['badge' => $badge])
                                @endforeach
                            </div>
                        @endif

                        {{-- One "Mehr" dropdown replaces the old per-feature header pills
                             (Vorbereiten/Zeitplan/Agenda/Notfall) and the mobile-only
                             duplicate list that used to live inside the avatar menu — a
                             single control that behaves identically at every breakpoint,
                             styled like the avatar menu below so it reads as native to the
                             app rather than a second, differently-built dropdown. Bastelideen
                             moves in too: it never had a header pill of its own before, but
                             it's exactly the same kind of "additional feature". --}}
                        @if ($anyMehrNavVisible)
                        <div x-data="{ open: false }" class="relative">
                            <button
                                type="button"
                                @click="open = !open"
                                @keydown.escape.window="open = false"
                                @class([
                                    'flex items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint',
                                    'bg-surface text-ink' => $featuresActive,
                                    'text-ink-soft hover:bg-surface hover:text-ink' => !$featuresActive,
                                ])
                                :aria-expanded="open"
                                aria-haspopup="true"
                                aria-label="Weitere Funktionen"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <rect x="3" y="3" width="6" height="6" rx="1.5"/>
                                    <rect x="11" y="3" width="6" height="6" rx="1.5"/>
                                    <rect x="3" y="11" width="6" height="6" rx="1.5"/>
                                    <rect x="11" y="11" width="6" height="6" rx="1.5"/>
                                </svg>
                                <span class="hidden sm:inline">Mehr</span>
                                @if (auth()->user()->isInEmergencyMode())
                                    <span class="h-1.5 w-1.5 rounded-full bg-signal" aria-hidden="true"></span>
                                @endif
                            </button>

                            <div
                                x-show="open"
                                x-transition.opacity.duration.150ms
                                @click.outside="open = false"
                                class="absolute right-0 mt-2 w-52 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
                                style="display: none;"
                            >
                                @if ($showPrepareNav)
                                <a href="{{ route('prepare') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('prepare'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('prepare'),
                                ]) @if(request()->routeIs('prepare')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h9m-9 4h12m-12 4h6"/></svg>
                                    Vorbereiten
                                </a>
                                @endif
                                @if ($showScheduleNav)
                                <a href="{{ route('schedule') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('schedule'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('schedule'),
                                ]) @if(request()->routeIs('schedule')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                                    Zeitplan
                                </a>
                                @endif
                                @if ($showWeekplanNav)
                                <a href="{{ route('weekplan') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('weekplan'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('weekplan'),
                                ]) @if(request()->routeIs('weekplan')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                    Wochenplan &amp; Ferien
                                </a>
                                @endif
                                {{-- Off by default (users.planner_enabled) — the pill itself only
                                     ever renders once someone has opted in via Settings, so a
                                     visit to /app/planner while it's off (Planner::mount()) and the
                                     total absence of this entry from a fresh account's "Mehr" menu
                                     are the same zero-footprint story. Deliberately not part of
                                     AppModules::CATALOG — the Planner toggle predates and is
                                     unrelated to the module-visibility system. --}}
                                @if (auth()->user()->planner_enabled)
                                    <a href="{{ route('planner') }}" wire:navigate @class([
                                        'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                        'bg-paper font-medium text-ink' => request()->routeIs('planner'),
                                        'text-ink-soft hover:text-ink' => !request()->routeIs('planner'),
                                    ]) @if(request()->routeIs('planner')) aria-current="page" @endif>
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l2.5 1.5"/></svg>
                                        Planer
                                    </a>
                                @endif
                                @if ($showAgendaNav)
                                <a href="{{ route('agenda') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('agenda'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('agenda'),
                                ]) @if(request()->routeIs('agenda')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M12 3v2h2"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
                                    Agenda
                                </a>
                                @endif
                                @if ($showCraftsNav)
                                <a href="{{ route('crafts') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('crafts'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('crafts'),
                                ]) @if(request()->routeIs('crafts')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5a5 5 0 0 0-3 9v1.5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V11.5a5 5 0 0 0-3-9Z"/><path d="M8 17h4"/><path d="M8.5 14.5h3"/></svg>
                                    Bastelideen
                                </a>
                                @endif
                                @if ($showFamilyNav)
                                <a href="{{ route('family') }}" wire:navigate @class([
                                    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-paper font-medium text-ink' => request()->routeIs('family'),
                                    'text-ink-soft hover:text-ink' => !request()->routeIs('family'),
                                ]) @if(request()->routeIs('family')) aria-current="page" @endif>
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="6.5" r="2.5"/><circle cx="14" cy="6.5" r="2" /><path d="M2.5 17v-1.5A3.5 3.5 0 0 1 6 12h2a3.5 3.5 0 0 1 3.5 3.5V17"/><path d="M12.5 12.3A3 3 0 0 1 15 15v2"/></svg>
                                    Familie
                                </a>
                                @endif
                                {{-- Notfall is always listed here now (no longer conditionally
                                     hidden from the header) — an "Aktiv" badge communicates the
                                     running state instead of the item's presence/absence. Still
                                     wrapped in $showEmergencyNav, which itself stays true whenever
                                     an emergency is actually running, regardless of the module
                                     toggle — see the $showEmergencyNav definition above. --}}
                                @if ($showEmergencyNav)
                                <a href="{{ route('emergency') }}" wire:navigate @class([
                                    'flex items-center justify-between gap-2 px-4 py-2 text-sm transition hover:bg-paper',
                                    'bg-signal-soft font-medium text-signal hover:brightness-95' => auth()->user()->isInEmergencyMode(),
                                    'bg-paper font-medium text-ink' => !auth()->user()->isInEmergencyMode() && request()->routeIs('emergency'),
                                    'text-ink-soft hover:text-ink' => !auth()->user()->isInEmergencyMode() && !request()->routeIs('emergency'),
                                ]) @if(request()->routeIs('emergency')) aria-current="page" @endif>
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                            <path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <circle cx="10" cy="14" r="1" fill="currentColor"/>
                                        </svg>
                                        Notfall
                                    </span>
                                    @if (auth()->user()->isInEmergencyMode())
                                        <span class="rounded-full bg-signal px-1.5 py-0.5 text-[10px] font-medium leading-none text-white">Aktiv</span>
                                    @endif
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Quick capture: the visible counterpart to the "N" shortcut, so
                             the panel is never a hidden-only feature. On touch it hides
                             wherever the floating button below takes over, so only one "+"
                             is ever on screen at a time.
                             x-data is required, not decorative — Alpine only processes
                             @click inside an Alpine component, and this button sits outside
                             every other x-data scope on the page. --}}
                        <button
                            type="button"
                            x-data
                            @click="$store.quickCapture.show($event.currentTarget)"
                            @class([
                                'h-8 w-8 place-items-center rounded-card border border-line bg-surface text-ink-soft transition hover:border-ink-faint/60 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest',
                                'hidden sm:grid' => $showCaptureFab,
                                'grid' => !$showCaptureFab,
                            ])
                            aria-label="Schnellerfassung öffnen (Taste N)"
                            title="Schnellerfassung — Taste N"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                        <div x-data="{ open: false }" class="relative">
                            <button
                                @click="open = !open"
                                @keydown.escape.window="open = false"
                                class="flex items-center gap-2 rounded-card px-2.5 py-1.5 text-sm text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                :aria-expanded="open"
                                aria-haspopup="true"
                            >
                                <span class="grid h-7 w-7 place-items-center rounded-full bg-forest-soft text-[12px] font-medium text-forest">
                                    {{ Str::of(auth()->user()->name)->trim()->substr(0, 1)->upper() }}
                                </span>
                                <span class="hidden sm:inline">{{ Str::of(auth()->user()->name)->before(' ') }}</span>
                            </button>

                            <div
                                x-show="open"
                                x-transition.opacity.duration.150ms
                                @click.outside="open = false"
                                class="absolute right-0 mt-2 w-52 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
                                style="display: none;"
                            >
                                <div class="border-b border-line px-4 py-2.5">
                                    <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-ink-faint">{{ auth()->user()->email }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Profil
                                </a>
                                <a href="{{ route('settings') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Einstellungen
                                </a>
                                @if ($showProgressNav)
                                <a href="{{ route('progress') }}" wire:navigate class="flex items-center justify-between gap-2 px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Fortschritt
                                    @if ($currentStreak > 0)
                                        <span @class([
                                            'flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                            'bg-paper text-ink-faint' => $streakTier <= 1,
                                            'bg-contour-soft text-contour' => $streakTier === 2,
                                            'bg-forest-soft text-forest' => $streakTier === 3,
                                            'bg-forest text-white' => $streakTier === 4,
                                        ])>{{ $currentStreak }}</span>
                                    @endif
                                </a>
                                @endif
                                <a href="{{ route('help') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Hilfe
                                </a>
                                @if (auth()->user()->is_admin)
                                <a href="{{ route('admin.announcements') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Ankündigungen verwalten
                                </a>
                                <a href="{{ route('admin.help') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Hilfe-Center verwalten
                                </a>
                                <a href="{{ route('admin.support') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Support-Anfragen
                                </a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                        Abmelden
                                    </button>
                                </form>
                            </div>
                        </div>
                        </div>
                    @endauth
                </div>
            </header>

            <main id="content">
                {{ $slot }}
            </main>
        </div>

        @auth
            @if ($showCaptureFab)
                {{-- Touch-only: bottom-right is where a "+" is expected on a phone, and
                     the header button is genuinely hard to find there. On the board this
                     clears the fixed bottom nav; on Bastelideen nothing is pinned, so it
                     sits at the normal inset. Both pages reserve matching bottom padding
                     so the last card can always be scrolled out from under it.
                     x-data as above: without it Alpine never binds the @click. --}}
                <button
                    type="button"
                    x-data
                    @click="$store.quickCapture.show($event.currentTarget)"
                    @class([
                        'fixed right-4 z-40 grid h-14 w-14 place-items-center rounded-full bg-forest text-white shadow-map transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper sm:hidden',
                        'bottom-[84px]' => request()->routeIs('app'),
                        'bottom-5' => !request()->routeIs('app'),
                    ])
                    aria-label="Schnellerfassung öffnen"
                >
                    <svg class="h-6 w-6" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            @endif

            <livewire:quick-capture />

            {{-- "Here's what's new" toast — see App\Livewire\FeatureAnnouncementToast.
                 Mounted once here, same reasoning as the celebration overlay below:
                 an unseen announcement has to appear no matter which page is the
                 first one loaded. Renders nothing when there's nothing unseen. --}}
            <livewire:feature-announcement-toast />

            {{-- Milestone celebration overlay — mounted once here (not inside any one
                 Livewire component) so it fires no matter which page a task gets
                 completed from (board, project page, Zeitplan strip). See the
                 'celebration' Alpine store in app.js and ProgressStats::celebrationFor(). --}}
            <div
                x-data
                x-on:celebrate.window="$store.celebration.fire($event.detail.kind, $event.detail.label)"
                class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center"
                aria-hidden="true"
            >
                <template x-if="$store.celebration.visible">
                    <div class="relative" x-transition.opacity.duration.200ms>
                        <div class="relative h-24 w-24">
                            <template x-for="n in 3" :key="n">
                                <div
                                    class="celebrate-ring"
                                    :class="{
                                        'celebrate-ring--record': $store.celebration.kind === 'record',
                                        'celebrate-ring--perfect-day': $store.celebration.kind === 'perfect-day',
                                        'celebrate-ring--streak-record': $store.celebration.kind === 'streak-record',
                                    }"
                                    :style="`animation-delay: ${(n - 1) * 140}ms`"
                                ></div>
                            </template>
                            <template x-for="p in $store.celebration.particles" :key="p.id">
                                <div
                                    class="celebrate-particle"
                                    :class="{
                                        'celebrate-particle--record': $store.celebration.kind === 'record',
                                        'celebrate-particle--perfect-day': $store.celebration.kind === 'perfect-day',
                                        'celebrate-particle--streak-record': $store.celebration.kind === 'streak-record',
                                    }"
                                    :style="`--dx: ${p.dx}px; --dy: ${p.dy}px; --rotate: ${p.rotate}deg; animation-delay: ${p.delay}ms`"
                                ></div>
                            </template>
                        </div>
                        <p
                            class="absolute left-1/2 top-full mt-2 -translate-x-1/2 whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium shadow-map"
                            :class="{
                                'bg-overprint text-white': $store.celebration.kind === 'record',
                                'bg-contour text-white': $store.celebration.kind === 'perfect-day' || $store.celebration.kind === 'streak-record',
                                'bg-forest text-white': $store.celebration.kind === 'goal',
                            }"
                            x-text="$store.celebration.label"
                        ></p>
                    </div>
                </template>
            </div>
        @endauth

        @livewireScripts
    </body>
</html>
