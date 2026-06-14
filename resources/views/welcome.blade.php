<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1f6b3b">
        <title>nothing-to-do — drei Listen, ein ruhiger Kopf</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
        {{-- Header --}}
        <header class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <x-logo class="h-6 w-6 text-forest" />
                <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
            </div>
            <nav class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('login') }}" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:text-ink">Anmelden</a>
                <a href="{{ route('register') }}" class="rounded-card bg-forest px-3.5 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Loslegen</a>
            </nav>
        </header>

        {{-- Hero --}}
        <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-10 sm:px-8 md:grid-cols-2 md:gap-8 md:pb-24 md:pt-20">
            <div class="animate-rise">
                <h1 class="text-4xl font-medium leading-[1.05] tracking-tight sm:text-5xl">
                    Drei Listen,<br>ein ruhiger Kopf.
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-ink-soft">
                    Erfasse alles in der Inbox, sortiere in To-Dos und Tasks, und sieh nur, was heute wirklich zählt.
                </p>
                <div class="mt-8 flex items-center gap-3">
                    <a href="{{ route('register') }}" class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Loslegen</a>
                    <a href="{{ route('login') }}" class="rounded-card border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink">Anmelden</a>
                </div>
                <p class="mt-5 text-xs text-ink-faint">Für einen Kopf. Keine Teams, kein Ballast.</p>
            </div>

            {{-- Real mini-board preview --}}
            <div class="animate-rise rounded-card border border-line bg-surface/60 p-3 shadow-map" style="animation-delay: 90ms;">
                <div class="grid grid-cols-3 gap-2.5">
                    @php
                        $cols = [
                            ['Inbox', [['Wettkampf anmelden', false, null], ['Buch zurückgeben', false, null]]],
                            ['To-Dos', [['Matheaufgaben', true, 'heute'], ['Vokabeln L. 8', false, null]]],
                            ['Tasks', [['Aufsatz entwerfen', false, 'Fr'], ['Praktikum', false, null]]],
                        ];
                    @endphp
                    @foreach ($cols as [$name, $items])
                        <div>
                            <p class="mb-2 px-0.5 text-[10px] font-medium text-ink-faint">{{ $name }}</p>
                            <div class="flex flex-col gap-2">
                                @foreach ($items as [$t, $imp, $tag])
                                    <div class="relative rounded-[7px] border border-line bg-surface px-2 py-2">
                                        @if ($imp)<span class="absolute inset-y-1.5 left-0 w-[2px] rounded-full bg-overprint"></span>@endif
                                        <div class="flex items-start gap-1.5">
                                            <span class="mt-px h-2.5 w-2.5 flex-none rounded-full border-2 border-line"></span>
                                            <span class="text-[11px] leading-tight text-ink">{{ $t }}</span>
                                        </div>
                                        @if ($tag)
                                            <span class="mt-1 ml-4 inline-block rounded px-1 py-px text-[9px] font-medium {{ $tag === 'heute' ? 'bg-forest-soft text-forest' : 'bg-contour-soft text-contour' }}">{{ $tag }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- The 3 Things --}}
        <section class="border-t border-line bg-surface/40">
            <div class="mx-auto max-w-3xl px-5 py-16 sm:px-8 md:py-24">
                <h2 class="text-2xl font-medium tracking-tight sm:text-3xl">Sortiert nach Form, nicht nach Projekt.</h2>
                <p class="mt-3 max-w-xl text-ink-soft">Jede Aufgabe ist eine von drei Sorten. Das hält die Übersicht klein und die Entscheidung leicht.</p>

                <div class="mt-10 divide-y divide-line border-y border-line">
                    <div class="flex items-baseline gap-4 py-5">
                        <span class="w-24 flex-none text-sm font-medium text-overprint">Inbox</span>
                        <p class="text-ink-soft">Alles Neue landet hier. Erst erfassen, später einsortieren.</p>
                    </div>
                    <div class="flex items-baseline gap-4 py-5">
                        <span class="w-24 flex-none text-sm font-medium text-forest">To-Do</span>
                        <p class="text-ink-soft">Klein. Mehrere davon passen in eine einzige Arbeitssession.</p>
                    </div>
                    <div class="flex items-baseline gap-4 py-5">
                        <span class="w-24 flex-none text-sm font-medium text-contour">Task</span>
                        <p class="text-ink-soft">Grösser, aber genau ein Arbeitsschritt. Ein Brocken nach dem anderen.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Heute focus --}}
        <section class="mx-auto max-w-3xl px-5 py-16 text-center sm:px-8 md:py-24">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-forest-soft px-3 py-1 text-xs font-medium text-forest">
                <x-logo class="h-3 w-3" /> Heute
            </span>
            <h2 class="mx-auto mt-5 max-w-2xl text-2xl font-medium leading-snug tracking-tight sm:text-3xl">
                Markiere, was heute dran ist. Der Rest wartet geduldig.
            </h2>
            <p class="mx-auto mt-4 max-w-md text-ink-soft">
                Wisch eine Aufgabe nach rechts oder zieh sie in den Heute-Bereich. Was wichtig ist und bald fällig, steht von selbst oben.
            </p>
        </section>

        {{-- CTA --}}
        <section class="border-t border-line">
            <div class="mx-auto max-w-3xl px-5 py-16 text-center sm:px-8 md:py-20">
                <h2 class="text-2xl font-medium tracking-tight sm:text-3xl">Bereit, den Kopf frei zu räumen?</h2>
                <div class="mt-7 flex items-center justify-center gap-3">
                    <a href="{{ route('register') }}" class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Loslegen</a>
                    <a href="{{ route('login') }}" class="rounded-card border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink">Anmelden</a>
                </div>
            </div>
        </section>

        <footer class="border-t border-line">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-5 py-8 text-sm text-ink-faint sm:flex-row sm:px-8">
                <div class="flex items-center gap-2">
                    <x-logo class="h-4 w-4 text-forest" />
                    <span>nothing-to-do</span>
                </div>
                <p>Drei Listen. Ein Tag. Nichts zu tun.</p>
            </div>
        </footer>
    </body>
</html>
