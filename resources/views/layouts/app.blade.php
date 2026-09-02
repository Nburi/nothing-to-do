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
                    {{-- The wordmark hides on mobile like every other text label in this
                         header (avatar name, "Mehr" label) — the logo icon alone already
                         carries the brand, and the freed width matters more on a narrow
                         screen than the extra word does. --}}
                    <a href="{{ url('/app') }}" class="flex items-center gap-2.5" wire:navigate>
                        <x-logo class="h-6 w-6 text-forest" />
                        <span class="hidden text-[15px] font-medium tracking-tight sm:inline">nothing-to-do</span>
                    </a>

                    @auth
                        @php
                            $featuresActive = request()->routeIs(['prepare', 'schedule', 'weekplan', 'planner', 'agenda', 'emergency', 'crafts']);
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
                            $anyMehrNavVisible = $showPrepareNav || $showScheduleNav || $showWeekplanNav || $showAgendaNav || $showCraftsNav || $showEmergencyNav;
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
                            {{-- max-w-[45vw]: bumped up from the original 38vw now that the
                                 wordmark hides and "Mehr" folds into the avatar menu on
                                 mobile (see both below) — there's more real width to spare
                                 before this needs to fall back to its own horizontal scroll,
                                 without risking the header itself overflowing on a narrow
                                 (~320px) phone. Still capped, still scrollable — the safety
                                 net for a wide badge selection stays exactly as before. --}}
                            <div class="flex max-w-[45vw] items-center gap-1.5 overflow-x-auto sm:max-w-none">
                                @foreach ($headerBadges as $badge)
                                    @include('partials.header-badge', ['badge' => $badge])
                                @endforeach
                            </div>
                        @endif

                        {{-- One "Mehr" dropdown replaces the old per-feature header pills
                             (Vorbereiten/Zeitplan/Agenda/Notfall), styled like the avatar
                             menu below so it reads as native to the app rather than a
                             second, differently-built dropdown. Bastelideen is in here too:
                             it never had a header pill of its own before, but it's exactly
                             the same kind of "additional feature".

                             Desktop only (sm and up) as of the mobile header redesign — on
                             mobile this whole trigger+panel folds into the avatar menu below
                             instead (see that menu's own "Weitere Funktionen" section), since
                             two near-identical round icon buttons sitting this close together
                             on a narrow phone was the actual source of the "überladen"
                             feeling, more than the badges themselves. Content is included
                             from partials/mehr-nav-links.blade.php so the desktop panel and
                             the mobile section can never drift apart. --}}
                        @if ($anyMehrNavVisible)
                        <div x-data="{ open: false }" class="relative hidden sm:block">
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
                                <span>Mehr</span>
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
                                @include('partials.mehr-nav-links')
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
                                type="button"
                                @click="open = !open"
                                @keydown.escape.window="open = false"
                                class="flex items-center gap-2 rounded-card px-2.5 py-1.5 text-sm text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                :aria-expanded="open"
                                aria-haspopup="true"
                                aria-label="Konto{{ $anyMehrNavVisible ? ' & weitere Funktionen' : '' }}"
                            >
                                <span class="relative grid h-7 w-7 place-items-center rounded-full bg-forest-soft text-[12px] font-medium text-forest">
                                    {{ Str::of(auth()->user()->name)->trim()->substr(0, 1)->upper() }}
                                    {{-- Mobile only: the "Mehr" button's own emergency dot
                                         (desktop, sm and up) has no home once that button hides
                                         on mobile — this is a safety-relevant ambient signal
                                         (you should see you're in an emergency even with the
                                         menu closed), so it moves here instead of disappearing. --}}
                                    @if (auth()->user()->isInEmergencyMode())
                                        <span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-signal ring-2 ring-paper sm:hidden" aria-hidden="true"></span>
                                    @endif
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
                                {{-- Mobile only: the "Mehr" feature links, folded in here
                                     since the standalone desktop trigger is hidden below sm.
                                     Same partial the desktop panel uses (see above), so the
                                     two lists can never disagree — only the stagger flourish
                                     differs, see header-menu-fan-in in app.css. --}}
                                @if ($anyMehrNavVisible)
                                <div class="border-b border-line py-1 sm:hidden">
                                    <p class="px-4 pb-1 pt-1.5 text-[11px] font-medium uppercase tracking-wide text-ink-faint">Weitere Funktionen</p>
                                    @include('partials.mehr-nav-links', ['stagger' => true])
                                </div>
                                @endif
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
