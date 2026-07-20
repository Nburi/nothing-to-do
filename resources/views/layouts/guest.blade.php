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
        <div class="flex min-h-[100dvh] flex-col items-center justify-center px-5 py-12">
            <a href="{{ url('/') }}" class="mb-8 flex items-center gap-2.5" wire:navigate>
                <x-logo class="h-7 w-7 text-forest" />
                <span class="text-lg font-medium tracking-tight">nothing-to-do</span>
            </a>

            <div class="w-full max-w-sm rounded-card border border-line bg-surface p-7 shadow-map">
                {{ $slot }}
            </div>

            <p class="mt-8 text-xs text-ink-faint">Drei Listen. Ein Tag. Nichts zu tun.</p>
        </div>

        @livewireScripts
    </body>
</html>
