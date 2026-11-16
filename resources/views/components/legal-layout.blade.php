{{-- Shared shell for standalone, guest-readable legal pages (Impressum, Datenschutz).
     Mirrors layouts/public.blade.php's header (logo + Anmelden/Loslegen) and
     welcome.blade.php's footer shape, as its own real Blade component (anonymous
     components are only auto-discovered from resources/views/components/, which is
     why this isn't just layouts/public.blade.php itself — that one is only ever
     rendered through Livewire's ->layout() fluent call, never as a <x-...> tag).
     Deliberately its own small component rather than reworking the existing public
     layout to serve both call styles — these two pages are the only consumers so far
     and a plain static Blade page needs nothing else from it.

     Props: $title (page title, prefixed to the app name), $description (meta
     description / og:description). --}}
@props(['title', 'description'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">

        <title>{{ $title }} · nothing-to-do</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="de_CH">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $title }} · nothing-to-do">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
        <meta name="twitter:card" content="summary">

        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-card focus:bg-forest focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">Zum Inhalt springen</a>

        <header class="mx-auto flex h-16 max-w-3xl items-center justify-between px-5 sm:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <x-logo class="h-6 w-6 text-forest" />
                <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
            </a>
            <nav class="flex items-center gap-1.5 sm:gap-3" aria-label="Hauptnavigation">
                <a href="{{ route('login') }}" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Anmelden</a>
                <a href="{{ route('register') }}" class="rounded-card bg-forest px-3.5 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Loslegen</a>
            </nav>
        </header>

        <main id="main" class="mx-auto max-w-3xl px-5 py-10 sm:px-8">
            <article class="prose-topo max-w-none">
                {{ $slot }}
            </article>
        </main>

        <footer class="mt-12 border-t border-line">
            <div class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 px-5 py-8 text-sm text-ink-faint sm:flex-row sm:px-8">
                <div class="flex items-center gap-2">
                    <x-logo class="h-4 w-4 text-forest" />
                    <span>nothing-to-do</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('impressum') }}" class="transition hover:text-ink">Impressum</a>
                    <a href="{{ route('datenschutz') }}" class="transition hover:text-ink">Datenschutz</a>
                    <a href="{{ url('/hilfe') }}" class="transition hover:text-ink">Hilfe</a>
                </div>
            </div>
        </footer>
    </body>
</html>
