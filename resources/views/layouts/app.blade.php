<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1f6b3b">

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
                        <a href="{{ route('schedule') }}" wire:navigate class="hidden items-center gap-1.5 rounded-card px-2.5 py-1.5 text-sm text-ink-soft transition hover:bg-surface hover:text-ink sm:inline-flex">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                            Zeitplan
                        </a>
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
                                <a href="{{ route('schedule') }}" wire:navigate class="block px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink sm:hidden">
                                    Zeitplan
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

        @livewireScripts
    </body>
</html>
