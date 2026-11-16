<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">

        <title>{{ $title ?? 'nothing-to-do' }}</title>
        <meta name="description" content="{{ $description ?? '' }}">
        <link rel="canonical" href="{{ $canonical ?? url('/') }}">
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:locale" content="de_CH">
        <meta property="og:url" content="{{ $canonical ?? url('/') }}">
        <meta property="og:title" content="{{ $title ?? 'nothing-to-do' }}">
        <meta property="og:description" content="{{ $description ?? '' }}">
        <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
        <meta name="twitter:card" content="summary">

        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        {{-- Per-page structured data (e.g. an Article schema for one Hilfe-Center
             page) — the caller builds the JSON, this just prints it. --}}
        @isset($jsonLd)
            <script type="application/ld+json">{!! $jsonLd !!}</script>
        @endisset
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-card focus:bg-forest focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">Zum Inhalt springen</a>

        {{-- Same header shell as welcome.blade.php — a public reader arriving
             from a search result should be able to get straight to Anmelden/
             Loslegen without hunting for the way back to the marketing page. --}}
        <header class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5" wire:navigate>
                <x-logo class="h-6 w-6 text-forest" />
                <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
            </a>
            <nav class="flex items-center gap-1.5 sm:gap-3" aria-label="Hauptnavigation">
                <a href="{{ route('login') }}" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper" wire:navigate>Anmelden</a>
                <a href="{{ route('register') }}" class="rounded-card bg-forest px-3.5 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper" wire:navigate>Loslegen</a>
            </nav>
        </header>

        <main id="main">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
