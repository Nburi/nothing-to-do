@php
    $stepCount = 14;
@endphp

<div
    x-data
    x-init="$store.onboarding.init({{ $stepCount }})"
    class="mx-auto max-w-xl px-4 pb-16 pt-6 sm:px-6"
>
    <div class="mb-10 flex items-center gap-4">
        <div class="flex-1">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-line">
                <div
                    class="h-full rounded-full bg-forest transition-all duration-300 ease-out"
                    :style="`width: ${(($store.onboarding.step + 1) / $store.onboarding.total) * 100}%`"
                ></div>
            </div>
            <p class="mt-1.5 text-xs text-ink-faint">
                Schritt <span x-text="$store.onboarding.step + 1"></span> von <span x-text="$store.onboarding.total"></span>
            </p>
        </div>
        <button
            type="button"
            wire:click="skip"
            x-show="$store.onboarding.step < $store.onboarding.total - 1"
            class="flex-none text-sm text-ink-faint transition hover:text-ink-soft"
        >Überspringen</button>
    </div>

    <div class="min-h-[420px]">

        {{-- 0 — Willkommen --}}
        <div x-cloak x-show="$store.onboarding.step === 0" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <x-logo class="h-7 w-7 text-forest" />
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Willkommen bei nothing-to-do</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Ein persönliches Produktivitätssystem — gebaut, damit nichts vergessen geht, ohne dass dich
                eine riesige Liste erschlägt. Diese kurze Einführung zeigt dir in {{ $stepCount }} Schritten,
                wie die App tickt. Du kannst jederzeit überspringen und sie später in den Einstellungen
                erneut ansehen.
            </p>
        </div>

        {{-- 1 — Die 3 Dinge (signature moment: tap through sizes, the sample card grows and splits) --}}
        <div x-cloak x-show="$store.onboarding.step === 1" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="12" width="14" height="4" rx="1"/><rect x="5" y="7" width="10" height="4" rx="1"/><rect x="7" y="2" width="6" height="4" rx="1"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Die 3 Dinge</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Alles, was reinkommt, landet zuerst in der Inbox. Von dort sortierst du es in eine von drei
                Grössen — das ist das Grundprinzip der ganzen App. Tippe durch:
            </p>

            <div x-data="{ size: 'todo' }" class="space-y-5">
                <div class="mx-auto flex max-w-xs justify-center gap-1.5 rounded-[0.6rem] bg-surface p-1">
                    <button type="button" @click="size = 'todo'" class="flex-1 rounded-[0.45rem] px-2 py-1.5 text-sm transition" :class="size === 'todo' ? 'bg-forest text-white shadow-sm' : 'text-ink-soft hover:text-ink'">To-Do</button>
                    <button type="button" @click="size = 'task'" class="flex-1 rounded-[0.45rem] px-2 py-1.5 text-sm transition" :class="size === 'task' ? 'bg-forest text-white shadow-sm' : 'text-ink-soft hover:text-ink'">Task</button>
                    <button type="button" @click="size = 'project'" class="flex-1 rounded-[0.45rem] px-2 py-1.5 text-sm transition" :class="size === 'project' ? 'bg-forest text-white shadow-sm' : 'text-ink-soft hover:text-ink'">Project</button>
                </div>

                <div class="relative mx-auto flex h-28 max-w-xs items-center justify-center">
                    <template x-if="size !== 'project'">
                        <div
                            class="rounded-card border text-center transition-all duration-300 ease-out"
                            :class="size === 'todo'
                                ? 'w-40 border-line bg-paper px-3 py-2.5 text-sm text-ink-soft'
                                : 'w-56 border-forest/40 bg-forest-soft px-5 py-4 text-base font-medium text-ink shadow-map'"
                        >
                            <span x-text="size === 'todo' ? 'Wäsche aufhängen' : 'Referat vorbereiten'"></span>
                        </div>
                    </template>
                    <template x-if="size === 'project'">
                        <div class="relative h-24 w-56" x-transition.scale.origin.top.duration.300ms>
                            <div class="absolute inset-x-8 top-0 rounded-card border border-line bg-paper px-3 py-2 text-xs text-ink-faint shadow-sm" style="rotate: -3deg">Recherche</div>
                            <div class="absolute inset-x-4 top-6 rounded-card border border-line bg-paper px-3 py-2 text-xs text-ink-faint shadow-sm" style="rotate: 2deg">Folien bauen</div>
                            <div class="absolute inset-x-0 top-12 rounded-card border border-forest/40 bg-forest-soft px-3.5 py-2.5 text-sm font-medium text-ink shadow-map">Referat vorbereiten</div>
                        </div>
                    </template>
                </div>

                <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                    <template x-if="size === 'todo'"><span><span class="font-medium text-ink">To-Do</span> — klein. Mehrere schaffst du in einer Session.</span></template>
                    <template x-if="size === 'task'"><span><span class="font-medium text-ink">Task</span> — grösser, aber trotzdem ein einzelner Arbeitsschritt.</span></template>
                    <template x-if="size === 'project'"><span><span class="font-medium text-ink">Project</span> — ein Behälter für mehrteilige, nicht dringende Arbeit mit eigener Seite.</span></template>
                </p>
            </div>
        </div>

        {{-- 2 — Heute, Wichtig, Termine --}}
        <div x-cloak x-show="$store.onboarding.step === 2" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5l2.1 4.4 4.9.7-3.5 3.5.8 4.9-4.3-2.3-4.3 2.3.8-4.9-3.5-3.5 4.9-.7L10 2.5Z"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Heute, Wichtig, Termine</h1>
            <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                <p><span class="font-medium text-ink">Heute</span> markiert eine Aufgabe als Fokus für den Tag — sie taucht dann oben in deiner Heute-Ansicht auf.</p>
                <p><span class="font-medium text-ink">Wichtig</span> — ein Tipp auf den Titel markiert eine Aufgabe als wichtig; wichtige Aufgaben stehen im Board immer zuoberst.</p>
                <p><span class="font-medium text-ink">Frist</span> (hart, z. B. ein Abgabetermin) und <span class="font-medium text-ink">Wunschtermin</span> (weich, selbst gesetzt) — tippe auf das Datums-Feld einer Aufgabe für eine schnelle Auswahl.</p>
            </div>
        </div>

        {{-- 3 — Schnellerfassung --}}
        <div x-cloak x-show="$store.onboarding.step === 3" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Schnellerfassung</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Drück jederzeit die Taste
                <kbd class="rounded border border-line bg-surface px-1.5 py-0.5 font-mono text-xs text-ink">N</kbd>
                — oder tippe auf das <span class="font-medium text-ink">+</span> oben rechts — um sofort
                etwas festzuhalten, egal auf welcher Seite du gerade bist. Du wählst dabei direkt das Ziel:
                Inbox, To-Do, Task, ein Projekt, eine Gruppe, eine Bastelidee oder ein Agenda-Eintrag.
            </p>
        </div>

        {{-- 4 — Das Board --}}
        <div x-cloak x-show="$store.onboarding.step === 4" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="4" height="12" rx="1"/><rect x="8" y="4" width="4" height="7" rx="1"/><rect x="13" y="4" width="4" height="9" rx="1"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Das Board</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Inbox, To-Dos und Tasks liegen am Desktop nebeneinander, am Handy als Tabs untereinander. Zieh
                Karten zwischen den Spalten, wisch am Handy nach links, rechts oder unten, und halte eine
                Karte kurz über eine andere, um daraus eine Gruppe zu machen.
            </p>
        </div>

        {{-- 5 — Projekte & Gruppen --}}
        <div x-cloak x-show="$store.onboarding.step === 5" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M3 6a1 1 0 0 1 1-1h4l1.5 2H16a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6Z"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Projekte &amp; Gruppen</h1>
            <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                <p><span class="font-medium text-ink">Ein Projekt</span> hat eine eigene Seite mit Brainstorming-Notizen und externem Link (z. B. zu Jira oder GitHub) — für nicht dringende, mehrteilige Vorhaben.</p>
                <p><span class="font-medium text-ink">Eine Gruppe</span> ist die kleinere Variante: ein Bündel zusammengehöriger Schritte mit eigenen Notiz-Karten, direkt im Board sichtbar.</p>
            </div>
        </div>

        {{-- 6 — Vorbereitung für morgen --}}
        <div x-cloak x-show="$store.onboarding.step === 6" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 6h9m-9 4h12m-12 4h6"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Vorbereitung für morgen</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Ein geführtes Abend- oder Morgenritual (je nach deiner Einstellung): Inbox leeren, festlegen
                was als Nächstes dran ist, und den Zeitplan für den Tag legen — alles in drei kurzen,
                wischbaren Schritten.
            </p>
        </div>

        {{-- 7 — Zeitplan & Fokus --}}
        <div x-cloak x-show="$store.onboarding.step === 7" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Zeitplan &amp; Fokus</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Im Zeitplan legst du Termine und Kategorie-Blöcke (z. B. Schule, Training) auf eine
                Zeitachse. Kategorien mit aktiviertem Pomodoro zeigen einen Fokus-Timer mit Arbeits- und
                Pausenphasen — inklusive Vorschlag, welche Aufgabe als Nächstes dran ist.
            </p>
        </div>

        {{-- 8 — Wochenplan & Ferien --}}
        <div x-cloak x-show="$store.onboarding.step === 8" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Wochenplan &amp; Ferien</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Wiederkehrende Termine (z. B. der Stundenplan) verwaltest du einmal im Wochenplan als
                abstrakte Mo–So-Vorlage. Einzelne Tage — Ferien, Krankheit — lassen sich dort gezielt
                pausieren, ohne die Vorlage selbst anzutasten.
            </p>
        </div>

        {{-- 9 — Notfallmodus --}}
        <div x-cloak x-show="$store.onboarding.step === 9" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="1" fill="currentColor"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Notfallmodus</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Wenn ein Projekt plötzlich dringend wird: Der Notfallmodus reiht seine Aufgaben in der
                richtigen Reihenfolge und blendet den Rest des Boards vorübergehend aus, bis nur noch das
                Wichtigste zu sehen ist.
            </p>
        </div>

        {{-- 10 — Agenda --}}
        <div x-cloak x-show="$store.onboarding.step === 10" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M12 3v2h2"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Agenda</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Hausaufgaben und Prüfungen leben in einer eigenen Liste, getrennt vom Rest der App. Sie kann
                privat bleiben oder mit einer Schulklasse geteilt werden — dann sieht die ganze Klasse
                dieselbe Liste, während alles andere in der App weiterhin nur dir gehört.
            </p>
        </div>

        {{-- 11 — Bastelideen & Fortschritt --}}
        <div x-cloak x-show="$store.onboarding.step === 11" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5a5 5 0 0 0-3 9v1.5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V11.5a5 5 0 0 0-3-9Z"/><path d="M8 17h4"/><path d="M8.5 14.5h3"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Bastelideen &amp; Fortschritt</h1>
            <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                <p><span class="font-medium text-ink">Bastelideen</span> ist eine Liste für „was mache ich, wenn mir langweilig ist" — ganz ohne Termindruck.</p>
                <p><span class="font-medium text-ink">Fortschritt</span> zeigt, wie viele Aufgaben du erledigst, deine Serie aufeinanderfolgender „perfekter" Tage, und eine Heatmap der letzten Wochen.</p>
            </div>
        </div>

        {{-- 12 — Module & Startseite (interactive, functional — real toggles) --}}
        <div x-cloak x-show="$store.onboarding.step === 12" class="space-y-6">
            <div class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/></svg>
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">Passe die App an</h1>
                <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                    Blende Bereiche aus, die du nicht brauchst — sie verschwinden aus dem „Mehr"-Menü. Wähle
                    danach, welche Seite sich öffnen soll, wenn du die App startest. Beides lässt sich
                    jederzeit in den Einstellungen wieder ändern.
                </p>
            </div>

            <div class="space-y-2">
                @foreach ($this->moduleRows as $row)
                    <div
                        wire:key="onboarding-module-row-{{ $row['key'] }}"
                        class="flex items-center gap-3 rounded-card border border-line bg-surface p-3.5 transition-opacity duration-300 {{ $row['hidden'] ? 'opacity-45' : 'opacity-100' }}"
                    >
                        <div class="min-w-0 flex-1">
                            <p @class(['text-sm font-medium', 'text-ink' => ! $row['hidden'], 'text-ink-faint' => $row['hidden']])>{{ $row['label'] }}</p>
                            <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">{{ $row['description'] }}</p>
                        </div>
                        <button
                            type="button"
                            wire:click="toggleModule('{{ $row['key'] }}')"
                            @class([
                                'relative h-6 w-10 flex-none rounded-full transition',
                                'bg-forest' => ! $row['hidden'],
                                'bg-line' => $row['hidden'],
                            ])
                            aria-label="{{ $row['label'] }} {{ $row['hidden'] ? 'einblenden' : 'ausblenden' }}"
                        >
                            <span @class([
                                'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                                'left-[1.125rem]' => ! $row['hidden'],
                                'left-0.5' => $row['hidden'],
                            ])></span>
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="rounded-card border border-line bg-surface p-4">
                <p class="mb-3 text-sm font-medium text-ink">Startseite</p>
                <div class="flex flex-wrap gap-2 rounded-[0.6rem] bg-paper p-1">
                    @foreach ($this->landingPageOptions as $option)
                        <button
                            type="button"
                            wire:click="setDefaultPage('{{ $option['key'] }}')"
                            @class([
                                'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                'bg-forest text-white shadow-sm' => $defaultPage === $option['key'],
                                'text-ink-soft hover:text-ink' => $defaultPage !== $option['key'],
                            ])
                        >{{ $option['label'] }}</button>
                    @endforeach
                </div>
            </div>

            <p class="text-center text-xs text-ink-faint">
                Im Header lassen sich zudem einzelne Kurzinfos (z. B. deine Serie) ein-/ausblenden und
                anordnen — unter Einstellungen → Header-Badges.
            </p>
        </div>

        {{-- 13 — Fertig --}}
        <div x-cloak x-show="$store.onboarding.step === 13" class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10.5l4 4 8-9"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Bereit.</h1>
            <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                <p>Das war's — du kennst jetzt jeden Bereich der App.</p>
                <p>
                    In den Einstellungen findest du ausserdem: den Pomodoro-Rhythmus, Push-Benachrichtigungen,
                    deine Zeitzone, Zeitplan-Kategorien und — für Fortgeschrittene — eine token-basierte API
                    für Apple Shortcuts.
                </p>
                <p>Dieses Tutorial findest du jederzeit wieder unter Einstellungen → Tutorial.</p>
            </div>
        </div>

    </div>

    <div class="mt-10 flex items-center justify-between gap-3">
        <button
            type="button"
            @click="$store.onboarding.back()"
            x-show="$store.onboarding.step > 0"
            x-cloak
            class="rounded-card border border-line bg-surface px-4 py-2 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
        >Zurück</button>

        <button
            type="button"
            @click="$store.onboarding.next()"
            x-show="$store.onboarding.step < $store.onboarding.total - 1"
            x-cloak
            class="ml-auto rounded-card bg-forest px-5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
        >Weiter</button>

        <button
            type="button"
            wire:click="finish"
            x-show="$store.onboarding.step === $store.onboarding.total - 1"
            x-cloak
            class="ml-auto rounded-card bg-forest px-5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
        >Los geht's</button>
    </div>
</div>
