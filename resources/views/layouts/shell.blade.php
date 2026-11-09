<!DOCTYPE html>
{{-- The new app's layout (refactor step 2.2). No old header: each page brings its own top bar
     (Werkbank) or tabs (Stapel). Theme variables come first so the first paint is right. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ \App\Support\Theme::current()['mode'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            <meta name="presence-url" content="{{ route('presence.heartbeat') }}">
        @endauth
        @include('partials.theme-style')
        @include('partials.pwa-head')

        <title>{{ $title ?? 'nothing-to-do' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-paper font-sans text-ink antialiased" data-shell>
        <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-card focus:bg-surface focus:px-4 focus:py-2 focus:shadow-card">
            {{ __('shell.skip_to_content') }}
        </a>

        {{ $slot }}

        @auth
            @include('shell.capture-sheet')
        @endauth

        @livewireScripts
    </body>
</html>
