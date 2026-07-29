<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>if ('IntersectionObserver' in window) document.documentElement.classList.add('js');</script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#1F6B3B">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#57A972">

        <title>nothing-to-do - Drei Listen, ein ruhiger Kopf</title>
        <meta name="description" content="Erfasse alles in der Inbox, sortiere in To-Dos, Tasks und Projekte, und sieh nur, was heute zählt. Mit Zeitplan, Fokus-Timer und Erinnerungen für den ganzen Tag.">
        <link rel="canonical" href="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="de_CH">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:title" content="nothing-to-do - Drei Listen, ein ruhiger Kopf">
        <meta property="og:description" content="Erfasse alles in der Inbox, sortiere in To-Dos, Tasks und Projekte, und sieh nur, was heute zählt. Mit Zeitplan, Fokus-Timer und Erinnerungen für den ganzen Tag.">
        <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
        <meta name="twitter:card" content="summary">

        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Scroll-reveal: gated by .js (added above, only when IntersectionObserver
               exists), so a no-JS visitor always sees full content, never stuck hidden. */
            .js .reveal {
                opacity: 0;
                transform: translateY(16px);
                transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .js .reveal.is-visible {
                opacity: 1;
                transform: none;
            }
        </style>
    </head>
    <body class="min-h-[100dvh] bg-paper font-sans text-ink antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-card focus:bg-forest focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">Zum Inhalt springen</a>

        {{-- Header --}}
        <header class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <x-logo class="h-6 w-6 text-forest" />
                <span class="text-[15px] font-medium tracking-tight">nothing-to-do</span>
            </div>
            <nav class="flex items-center gap-1.5 sm:gap-3" aria-label="Hauptnavigation">
                <a href="{{ route('login') }}" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Anmelden</a>
                <a href="{{ route('register') }}" class="rounded-card bg-forest px-3.5 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Loslegen</a>
            </nav>
        </header>

        <main id="main">
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
                        <a href="{{ route('register') }}" class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Loslegen</a>
                        <a href="{{ route('login') }}" class="rounded-card border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Anmelden</a>
                    </div>
                    <p class="mt-5 text-xs text-ink-faint">Kein Team, keine Einladung, kein Setup. Nur du und deine Listen.</p>
                </div>

                {{-- Real mini-board preview --}}
                <div class="animate-rise rounded-card border border-line bg-surface/60 p-3 shadow-map" style="animation-delay: 90ms;" aria-hidden="true">
                    <div class="grid grid-cols-3 gap-2.5">
                        @php
                            $cols = [
                                ['Inbox', [['Geschenk für Sarah', false, null], ['Auto zum Service bringen', false, null]]],
                                ['To-Dos', [['Präsentation vorbereiten', true, 'heute'], ['Einkaufsliste schreiben', false, null]]],
                                ['Tasks', [['Projektplan entwerfen', false, 'Fr'], ['Ferienwohnung buchen', false, null]]],
                            ];
                        @endphp
                        @foreach ($cols as [$name, $items])
                            <div>
                                <p class="mb-2 px-0.5 text-[10px] font-medium text-ink-faint">{{ $name }}</p>
                                <div class="flex flex-col gap-2">
                                    @foreach ($items as [$t, $imp, $tag])
                                        <div class="relative rounded-[7px] border px-2 py-2 {{ $imp ? 'border-line border-t-2 border-t-overprint bg-overprint-soft' : 'border-line bg-surface' }}">
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
            <section class="reveal border-t border-line bg-surface/40">
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

            {{-- Beyond the 3 lists: Projects, Schedule/Pomodoro, evening prep, installable+push --}}
            <section class="mx-auto max-w-6xl px-5 py-16 sm:px-8 md:py-24">
                <h2 class="reveal text-2xl font-medium tracking-tight sm:text-3xl">Nicht nur eine Liste für heute.</h2>
                <p class="reveal mt-3 max-w-xl text-ink-soft">Grössere Vorhaben, dein Tagesplan und Erinnerungen gehören genauso dazu wie die drei Listen.</p>

                <div class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {{-- Zeitplan & Fokus-Timer (wide) --}}
                    <div class="reveal rounded-card border border-line bg-surface p-6 md:col-span-2">
                        <h3 class="text-base font-medium tracking-tight">Dein Tag als Zeitplan, nicht nur als Liste</h3>
                        <p class="mt-2 max-w-sm text-sm text-ink-soft">Ordne Termine und Aufgaben auf einer Zeitachse an, mit eingebautem Fokus-Timer für konzentrierte Arbeitsblöcke.</p>
                        <div class="mt-5 flex items-center gap-3" aria-hidden="true">
                            <div class="flex h-16 flex-1 items-end gap-1.5 rounded-[7px] border border-line bg-paper/60 p-2.5">
                                <div class="h-[40%] w-full rounded-sm bg-contour-soft"></div>
                                <div class="h-[75%] w-full rounded-sm bg-forest-soft"></div>
                                <div class="h-full w-full rounded-sm bg-forest"></div>
                                <div class="h-[55%] w-full rounded-sm bg-overprint-soft"></div>
                                <div class="h-[30%] w-full rounded-sm bg-line"></div>
                            </div>
                            <div class="h-16 w-16 flex-none rounded-full" style="background: conic-gradient(rgb(var(--forest)) 0deg 260deg, rgb(var(--line)) 260deg 360deg)">
                                <div class="m-[3px] flex h-[calc(100%-6px)] w-[calc(100%-6px)] items-center justify-center rounded-full bg-surface text-[11px] font-medium tnum text-ink">18:24</div>
                            </div>
                        </div>
                    </div>

                    {{-- Installable + push notifications (tall) --}}
                    <div class="reveal flex flex-col rounded-card border border-line bg-surface p-6 md:row-span-2" style="transition-delay: 80ms;">
                        <h3 class="text-base font-medium tracking-tight">Installierbar. Erinnert dich, auch wenn nichts offen ist</h3>
                        <p class="mt-2 text-sm text-ink-soft">Als App auf dem Homescreen, mit Benachrichtigungen, die ankommen, selbst wenn der Tab längst zu ist.</p>
                        <div class="mt-5 flex flex-1 items-center justify-center" aria-hidden="true">
                            <div class="w-full max-w-[168px] rounded-[20px] border border-line bg-paper/60 p-2.5 shadow-map">
                                <div class="mx-auto mb-2 h-1 w-8 rounded-full bg-line"></div>
                                <div class="rounded-[10px] border border-line bg-surface p-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <x-logo class="h-3 w-3 text-forest" />
                                        <span class="text-[10px] font-medium text-ink">nothing-to-do</span>
                                    </div>
                                    <p class="mt-1 text-[10px] leading-snug text-ink-soft">Zeit für „Vorbereitung für morgen“.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Projects --}}
                    <div class="reveal rounded-card border border-line bg-surface p-6" style="transition-delay: 160ms;">
                        <h3 class="text-base font-medium tracking-tight">Grössere Vorhaben bleiben nicht liegen</h3>
                        <p class="mt-2 text-sm text-ink-soft">Ein Projekt bündelt alle Aufgaben dazu, mit Fortschrittsanzeige und einem Notfallmodus fürs Aufholen.</p>
                        <div class="mt-5 rounded-[7px] border border-line bg-paper/60 p-3" aria-hidden="true">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-medium text-ink">Vortrag Physik</span>
                                <span class="flex-none rounded bg-signal-soft px-1.5 py-px text-[9px] font-medium text-signal">Notfall</span>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-line">
                                <div class="h-full w-2/3 rounded-full bg-forest"></div>
                            </div>
                            <p class="mt-1.5 text-[10px] text-ink-faint">4 von 6 erledigt</p>
                        </div>
                    </div>

                    {{-- Evening prep ritual --}}
                    <div class="reveal rounded-card border border-line bg-surface p-6" style="transition-delay: 240ms;">
                        <h3 class="text-base font-medium tracking-tight">In drei Schritten bereit für morgen</h3>
                        <p class="mt-2 text-sm text-ink-soft">Inbox leeren, morgen planen, Zeitplan setzen: ein kurzes Abendritual, dann ist der Kopf wirklich frei.</p>
                        <div class="relative mt-5 h-14" aria-hidden="true">
                            <div class="absolute inset-x-4 top-3 h-10 rotate-2 rounded-[8px] border border-line bg-paper/70"></div>
                            <div class="absolute inset-x-2 top-1.5 h-10 -rotate-1 rounded-[8px] border border-line bg-paper/90"></div>
                            <div class="absolute inset-x-0 top-0 flex h-10 items-center justify-between rounded-[8px] border border-line bg-surface px-3 shadow-map">
                                <span class="text-[11px] text-ink">Rechnung bezahlen</span>
                                <span class="text-[10px] text-forest">→ morgen</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Heute focus --}}
            <section class="reveal mx-auto max-w-3xl px-5 py-16 text-center sm:px-8 md:py-24">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-forest-soft px-3 py-1 text-xs font-medium text-forest">
                    <x-logo class="h-3 w-3" aria-hidden="true" /> Heute
                </span>
                <h2 class="mx-auto mt-5 max-w-2xl text-2xl font-medium leading-snug tracking-tight sm:text-3xl">
                    Markiere, was heute dran ist. Der Rest wartet geduldig.
                </h2>
                <p class="mx-auto mt-4 max-w-md text-ink-soft">
                    Wisch eine Aufgabe nach rechts oder zieh sie in den Heute-Bereich. Was wichtig ist und bald fällig, steht von selbst oben.
                </p>
            </section>

            {{-- CTA --}}
            <section class="reveal border-t border-line">
                <div class="mx-auto max-w-3xl px-5 py-16 text-center sm:px-8 md:py-20">
                    <h2 class="text-2xl font-medium tracking-tight sm:text-3xl">Bereit, den Kopf frei zu räumen?</h2>
                    <div class="mt-7 flex items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">Loslegen</a>
                        <a href="{{ route('login') }}" class="rounded-card border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Anmelden</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-line">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-5 py-8 text-sm text-ink-faint sm:flex-row sm:px-8">
                <div class="flex items-center gap-2">
                    <x-logo class="h-4 w-4 text-forest" />
                    <span>nothing-to-do</span>
                </div>
                <p>Drei Listen. Ein Tag. Nichts zu tun.</p>
            </div>
        </footer>

        <script>
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
                document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
            }
        </script>
    </body>
</html>
