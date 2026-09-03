{{--
    Shared shell for every rendered error page (404/4xx/5xx) — a standalone
    HTML document, not layouts.app: an error can hit a guest (a dead link
    shared from outside) just as easily as a logged-in user, and the whole
    point is that it must never itself depend on anything that could be part
    of what's broken. Mirrors welcome.blade.php's <head> (same meta/PWA/Vite
    setup) so a guest never sees two different visual identities in one
    session.

    Params: $title (browser tab), $heading, $message, $icon (optional Blade
    component name, defaults to 'error-icon'), $iconClass (extra classes,
    e.g. the 404 page's error-icon-settle pulse).
--}}
@php
    $icon ??= 'error-icon';
    $iconClass ??= '';
    $backRoute = auth()->check() ? auth()->user()->defaultLandingRouteName() : 'home';
    $backLabel = auth()->check() ? 'Zurück zum Board' : 'Zurück zur Startseite';
@endphp
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">

        <title>{{ $title }} · nothing-to-do</title>

        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
        <div class="flex min-h-[100dvh] flex-col">
            <header class="mx-auto flex h-16 w-full max-w-6xl items-center px-5 sm:px-8">
                <div class="flex items-center gap-2.5">
                    <x-logo class="h-6 w-6 text-forest" />
                    <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
                </div>
            </header>

            <main class="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-5 pb-20 text-center">
                <div class="animate-rise flex flex-col items-center">
                    <span class="mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-line bg-surface text-contour">
                        <x-dynamic-component :component="$icon" :class="'h-8 w-8 '.$iconClass" />
                    </span>
                    <h1 class="text-2xl font-medium tracking-tight">{{ $heading }}</h1>
                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-ink-soft">{{ $message }}</p>
                    <a
                        href="{{ route($backRoute) }}"
                        class="mt-8 rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper"
                    >{{ $backLabel }}</a>
                </div>
            </main>
        </div>
    </body>
</html>
