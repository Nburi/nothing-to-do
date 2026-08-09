<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">
        @include('partials.pwa-head')

        <title>{{ config('app.name', 'nothing-to-do') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
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
                        <div class="flex items-center gap-1.5">
                        <a href="{{ route('prepare') }}" wire:navigate @class([
                            'hidden items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm transition sm:inline-flex',
                            'bg-surface text-ink' => request()->routeIs('prepare'),
                            'text-ink-soft hover:bg-surface hover:text-ink' => !request()->routeIs('prepare'),
                        ]) @if(request()->routeIs('prepare')) aria-current="page" @endif>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h9m-9 4h12m-12 4h6"/></svg>
                            Vorbereiten
                        </a>
                        <a href="{{ route('schedule') }}" wire:navigate @class([
                            'hidden items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm transition sm:inline-flex',
                            'bg-surface text-ink' => request()->routeIs('schedule'),
                            'text-ink-soft hover:bg-surface hover:text-ink' => !request()->routeIs('schedule'),
                        ]) @if(request()->routeIs('schedule')) aria-current="page" @endif>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                            Zeitplan
                        </a>
                        <a href="{{ route('agenda') }}" wire:navigate @class([
                            'hidden items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm transition sm:inline-flex',
                            'bg-surface text-ink' => request()->routeIs('agenda'),
                            'text-ink-soft hover:bg-surface hover:text-ink' => !request()->routeIs('agenda'),
                        ]) @if(request()->routeIs('agenda')) aria-current="page" @endif>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M12 3v2h2"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
                            Agenda
                        </a>
                        {{-- Notfall is a mode, not a place: the pill only exists while the
                             mode is actually running (or while its own screen is open).
                             Otherwise it lives in the avatar menu, next to Bastelideen. --}}
                        @if (auth()->user()->isInEmergencyMode() || request()->routeIs('emergency'))
                            <a href="{{ route('emergency') }}" wire:navigate @class([
                                'hidden items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm transition sm:inline-flex',
                                'bg-signal-soft text-signal hover:brightness-95' => auth()->user()->isInEmergencyMode(),
                                'bg-surface text-ink' => !auth()->user()->isInEmergencyMode(),
                            ]) @if(request()->routeIs('emergency')) aria-current="page" @endif>
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="10" cy="14" r="1" fill="currentColor"/>
                                </svg>
                                Notfall
                            </a>
                        @endif

                        {{-- Quick capture: the visible counterpart to the "N" shortcut, so
                             the panel is never a hidden-only feature. --}}
                        <button
                            type="button"
                            @click="$store.quickCapture.show($event.currentTarget)"
                            class="hidden h-8 w-8 place-items-center rounded-card border border-line bg-surface text-ink-soft transition hover:border-ink-faint/60 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest sm:grid"
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
                                <a href="{{ route('prepare') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink sm:hidden">
                                    Vorbereiten
                                </a>
                                <a href="{{ route('schedule') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink sm:hidden">
                                    Zeitplan
                                </a>
                                <a href="{{ route('agenda') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink sm:hidden">
                                    Agenda
                                </a>
                                {{-- Bastelideen and Notfall have no permanent desktop pill
                                     any more, so they stay in the menu at every width. --}}
                                <a href="{{ route('crafts') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Bastelideen
                                </a>
                                <a href="{{ route('emergency') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Notfall
                                </a>
                                <a href="{{ route('profile.edit') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Profil
                                </a>
                                <a href="{{ route('settings') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                                    Einstellungen
                                </a>
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
            {{-- Mobile counterpart to the header's "+" — a hardware keyboard isn't
                 a given on touch, so capture always has a visible entry point.
                 Sits clear of the board's fixed bottom nav, which only that page has. --}}
            <button
                type="button"
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

            <livewire:quick-capture />
        @endauth

        @livewireScripts
    </body>
</html>
