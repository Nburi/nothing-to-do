@php
    $swatches = [
        'contour' => 'bg-contour',
        'overprint' => 'bg-overprint',
        'forest' => 'bg-forest',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
    ];
@endphp

<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-5 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Einstellungen</h1>
    </div>

    <nav
        aria-label="Einstellungsbereiche"
        wire:ignore
        x-data="{
            active: 'general',
            init() {
                const sections = [...document.querySelectorAll('#content section[id]')];
                const io = new IntersectionObserver(
                    (entries) => entries.forEach((entry) => {
                        if (entry.isIntersecting) this.active = entry.target.id;
                    }),
                    { rootMargin: '-120px 0px -70% 0px', threshold: 0 }
                );
                sections.forEach((section) => io.observe(section));

                // The last section rarely has enough room below it to ever cross the
                // IntersectionObserver's trigger band — once the page can't scroll any
                // further, just treat it as active directly.
                const last = sections[sections.length - 1];
                window.addEventListener('scroll', () => {
                    if (last && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                        this.active = last.id;
                    }
                }, { passive: true });
            },
        }"
        class="sticky top-16 z-20 -mt-1 mb-2 flex items-center gap-1.5 overflow-x-auto border-b border-line bg-paper/95 py-3 backdrop-blur-sm"
    >
        @foreach (['general' => 'Allgemein', 'schedule' => 'Zeitplan & Fokus', 'prepare' => 'Vorbereitung', 'progress' => 'Fortschritt', 'notifications' => 'Benachrichtigungen', 'developer' => 'Entwickler'] as $id => $label)
            <a
                href="#{{ $id }}"
                :aria-current="active === '{{ $id }}' ? 'true' : null"
                :class="active === '{{ $id }}'
                    ? 'bg-surface text-ink underline decoration-2 underline-offset-4 dark:text-white'
                    : 'text-ink-soft hover:bg-surface hover:text-ink'"
                class="flex-none rounded-card px-3 py-1.5 text-sm transition"
            >{{ $label }}</a>
        @endforeach
    </nav>

    <div class="space-y-10 pt-4 sm:space-y-12">

    {{-- Allgemein --}}
    <section id="general" class="scroll-mt-28 space-y-5">
        <h2 class="text-lg font-medium tracking-tight text-ink">Allgemein</h2>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Erledigte Aufgaben</h3>
        <p class="mb-5 text-sm text-ink-soft leading-relaxed">
            Erledigte Aufgaben bleiben bis zu dieser Uhrzeit sichtbar — danach verschwinden sie automatisch.
            Standard: <span class="font-medium text-ink">01:00</span>
        </p>

        <div class="max-w-xs">
            <label for="resetTime" class="mb-1.5 block text-sm font-medium text-ink">Verschwinden um</label>
            <input
                id="resetTime"
                type="time"
                wire:model="resetTime"
                wire:change="save"
                class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
            />
            @error('resetTime')
                <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
            @enderror
        </div>
        </div>

        {{-- Module — hide the pages you don't use. Each row visually mirrors
             its "Mehr"-menu counterpart; toggling it off fades it right here
             (not just in the header) so the effect is confirmed without ever
             leaving Settings, and the counter above the list updates with
             it. See App\Services\AppModules. --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="mb-1 flex items-baseline justify-between gap-3">
                <h3 class="text-base font-medium text-ink">Module</h3>
                <p
                    wire:key="module-count"
                    class="text-xs text-ink-faint"
                >Menü zeigt noch {{ collect($this->moduleRows)->where('hidden', false)->count() }} von {{ count($this->moduleRows) }} Bereichen</p>
            </div>
            <p class="mb-5 text-sm text-ink-soft leading-relaxed">
                Blende Bereiche aus, die du nicht brauchst — sie verschwinden aus dem „Mehr"-Menü und dem
                Profilmenü. Das Board und die Einstellungen bleiben immer erreichbar.
            </p>

            <div class="space-y-2">
                @foreach ($this->moduleRows as $row)
                    <div
                        wire:key="module-row-{{ $row['key'] }}"
                        class="flex items-center gap-3 rounded-card border border-line bg-paper p-3.5 transition-opacity duration-300 {{ $row['hidden'] ? 'opacity-45' : 'opacity-100' }}"
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
        </div>

        {{-- Startseite --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h3 class="mb-1 text-base font-medium text-ink">Startseite</h3>
            <p class="mb-5 text-sm text-ink-soft leading-relaxed">
                Diese Seite öffnet sich, wenn du die App startest oder dich anmeldest — z. B. direkt die
                Agenda statt des Boards. Nur ausgeblendete Bereiche stehen hier nicht zur Wahl.
            </p>

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
            <p class="mt-3 text-xs text-ink-soft">
                Aktuell: <span class="font-medium text-ink">{{ collect($this->landingPageOptions)->firstWhere('key', $defaultPage)['label'] ?? 'Board (Startseite)' }}</span>
            </p>
        </div>

        {{-- Listen-Konzept — which mental model the board renders through (see
             App\Services\ListConcepts). Signature moment: each available pill
             previews the user's own real, currently active tasks — not mock
             data — so switching is "try it on", not a blind label pick.
             Concepts not built yet show "Bald verfügbar" and can't be
             selected (their session flips `available` when it lands). --}}
        <div id="list-concept" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h3 class="mb-1 text-base font-medium text-ink">Listen-Konzept</h3>
            <p class="mb-5 text-sm text-ink-soft leading-relaxed">
                Wie deine Liste organisiert ist. Deine Aufgaben bleiben dabei immer erhalten — nur die
                Ansicht wechselt.
            </p>

            <div class="space-y-3">
                @foreach ($this->listConceptRows as $row)
                    <button
                        type="button"
                        wire:key="list-concept-{{ $row['key'] }}"
                        @if ($row['available']) wire:click="setListConcept('{{ $row['key'] }}')" @endif
                        @disabled(! $row['available'])
                        @class([
                            'flex w-full items-start gap-4 rounded-card border p-4 text-left transition',
                            'border-forest bg-forest-soft/40' => $row['current'],
                            'border-line bg-paper hover:border-ink-soft' => ! $row['current'] && $row['available'],
                            'cursor-not-allowed border-line/60 bg-paper/50 opacity-60' => ! $row['available'],
                        ])
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p @class(['text-sm font-medium', 'text-ink' => $row['available'], 'text-ink-faint' => ! $row['available']])>{{ $row['label'] }}</p>
                                @if ($row['current'])
                                    <span class="rounded-full bg-forest px-1.5 py-0.5 text-[10px] font-medium text-white">Aktiv</span>
                                @elseif (! $row['available'])
                                    <span class="rounded-full bg-line px-1.5 py-0.5 text-[10px] font-medium text-ink-faint">Bald verfügbar</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">{{ $row['description'] }}</p>

                            {{-- Real-data thumbnail — only for a concept that's actually built. --}}
                            @if ($row['available'])
                                <div class="mt-3">
                                    @include('livewire.partials.list-concept-preview', ['conceptKey' => $row['key'], 'previewTasks' => $this->listConceptPreviewTasks])
                                </div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tutorial — always offered, whether this account finished it, skipped
             it, or never even had it (see App\Livewire\Onboarding). Re-running it
             never resets anything here; it just re-stamps "last viewed on". --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">Tutorial</p>
                    <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">
                        @if (auth()->user()->onboarding_completed_at)
                            Zuletzt angesehen am {{ auth()->user()->onboarding_completed_at->isoFormat('D.M.YYYY') }}.
                        @else
                            Du hast die Einführung noch nicht angesehen.
                        @endif
                    </p>
                </div>
                <a
                    href="{{ route('onboarding') }}"
                    wire:navigate
                    class="flex-none rounded-card border border-line bg-paper px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                >{{ auth()->user()->onboarding_completed_at ? 'Nochmal ansehen' : 'Tutorial starten' }}</a>
            </div>
        </div>

        {{-- Hausaufgaben-Vorschau --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">Hausaufgaben-Vorschau</p>
                    <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">
                        Zeigt offene Hausaufgaben, die innerhalb der nächsten 3 Wochentage fällig sind, oben im
                        Dashboard — Wochenenden zählen dabei nicht mit.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="toggleHomeworkPreviewEnabled"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $homeworkPreviewEnabled,
                        'bg-line' => ! $homeworkPreviewEnabled,
                    ])
                    aria-label="Hausaufgaben-Vorschau {{ $homeworkPreviewEnabled ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $homeworkPreviewEnabled,
                        'left-0.5' => ! $homeworkPreviewEnabled,
                    ])></span>
                </button>
            </div>
        </div>

        {{-- Planer --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">Planer</p>
                    <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">
                        Verteilt offene Aufgaben, To-Dos und Hausaufgaben automatisch auf deine nächsten
                        Pomodoro-Arbeitsblöcke, damit du früh siehst, ob alles rechtzeitig fertig wird — statt es
                        erst am Tag der Deadline zu merken. Standardmässig aus.
                    </p>
                    @if ($plannerEnabled)
                        <a href="{{ route('planner') }}" wire:navigate class="mt-2 inline-block text-xs font-medium text-overprint hover:underline">Zum Planer →</a>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="togglePlannerEnabled"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $plannerEnabled,
                        'bg-line' => ! $plannerEnabled,
                    ])
                    aria-label="Planer {{ $plannerEnabled ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $plannerEnabled,
                        'left-0.5' => ! $plannerEnabled,
                    ])></span>
                </button>
            </div>
        </div>

        {{-- Zeitzone --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Zeitzone</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Stunden-Versatz zu UTC — z. B. <span class="font-medium text-ink">+1</span> für die Schweizer Winterzeit
            oder <span class="font-medium text-ink">+5.5</span> für halbe/viertel Zeitzonen (z. B. Indien, Nepal).
        </p>

        <div class="max-w-xs space-y-4">
            <div>
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <label for="timezoneOffset" class="block text-sm font-medium text-ink">UTC-Versatz (Stunden)</label>
                    <button
                        type="button"
                        x-data
                        @click="const d = window.detectTimezoneDefaults(); $wire.applyDetectedTimezone(d.offset, d.autoDst)"
                        class="flex-none text-xs font-medium text-overprint hover:underline"
                    >Automatisch erkennen</button>
                </div>
                <input
                    id="timezoneOffset"
                    type="number"
                    step="0.25"
                    min="-12"
                    max="14"
                    wire:model="timezoneOffset"
                    wire:change="saveTimezone"
                    class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                />
                @error('timezoneOffset')
                    <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-ink">Sommer-/Winterzeit automatisch korrigieren</p>
                    <p class="text-xs text-ink-soft">Zählt in der europäischen Sommerzeit automatisch eine Stunde dazu.</p>
                </div>
                <button
                    type="button"
                    wire:click="toggleTimezoneAutoDst"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $timezoneAutoDst,
                        'bg-line' => ! $timezoneAutoDst,
                    ])
                    aria-label="Sommer-/Winterzeit automatisch korrigieren {{ $timezoneAutoDst ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $timezoneAutoDst,
                        'left-0.5' => ! $timezoneAutoDst,
                    ])></span>
                </button>
            </div>
        </div>
        </div>

        {{-- Header-Badges — which ambient shortcuts show in the header, and in
             what order. Drag reorders the whole list (enabled and disabled
             rows alike, same as Kategorien below); the switch toggles one row
             without disturbing its position. See App\Services\HeaderBadges. --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h3 class="mb-1 text-base font-medium text-ink">Header-Badges</h3>
            <p class="mb-5 text-sm text-ink-soft leading-relaxed">
                Kleine Kurzwahl-Symbole oben im Header — ein Klick springt direkt zur passenden Seite.
                Ein Badge erscheint nur, wenn es gerade etwas zu zeigen gibt (z. B. eine offene Aufgabe
                oder einen laufenden Termin).
            </p>
            <div
                x-data
                x-init="window.headerBadgesSortable($el, $wire)"
                class="space-y-2"
            >
                @foreach ($this->headerBadgeRows as $row)
                    <div
                        wire:key="badge-row-{{ $row['key'] }}"
                        data-key="{{ $row['key'] }}"
                        class="flex items-center gap-2 rounded-card border border-line bg-paper py-2 pl-2 pr-3"
                    >
                        <span class="grid h-7 w-7 flex-none cursor-grab place-items-center rounded-card text-ink-faint active:cursor-grabbing" aria-hidden="true">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="4" r="1.1"/><circle cx="5" cy="8" r="1.1"/><circle cx="5" cy="12" r="1.1"/><circle cx="11" cy="4" r="1.1"/><circle cx="11" cy="8" r="1.1"/><circle cx="11" cy="12" r="1.1"/></svg>
                        </span>
                        <span @class(['flex-1 text-sm', 'text-ink' => $row['enabled'], 'text-ink-faint' => ! $row['enabled']])>{{ $row['label'] }}</span>
                        <button
                            type="button"
                            wire:click="toggleHeaderBadge('{{ $row['key'] }}')"
                            @class([
                                'relative h-6 w-10 flex-none rounded-full transition',
                                'bg-forest' => $row['enabled'],
                                'bg-line' => ! $row['enabled'],
                            ])
                            aria-label="{{ $row['label'] }} {{ $row['enabled'] ? 'ausblenden' : 'anzeigen' }}"
                        >
                            <span @class([
                                'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                                'left-[1.125rem]' => $row['enabled'],
                                'left-0.5' => ! $row['enabled'],
                            ])></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Presence. Lives under "Allgemein" rather than "Benachrichtigungen":
             it governs what other people see about you, not what the app sends
             you. Only rendered for someone actually in a class — for everyone
             else it would be a switch that controls nothing. --}}
        @if ($this->inAnyAgendaSpace)
            <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">Online-Status für die Klasse</p>
                        <p class="mt-0.5 text-xs text-ink-soft">
                            Mitglieder deiner Klassen sehen, ob du die App gerade offen hast. Aus heisst
                            aus: dann wird gar nicht erst aufgezeichnet, wann du zuletzt da warst.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="toggleShowPresence"
                        @class([
                            'relative h-6 w-10 flex-none rounded-full transition',
                            'bg-forest' => auth()->user()->show_presence,
                            'bg-line' => ! auth()->user()->show_presence,
                        ])
                        aria-label="Online-Status {{ auth()->user()->show_presence ? 'verbergen' : 'zeigen' }}"
                    >
                        <span @class([
                            'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                            'left-[1.125rem]' => auth()->user()->show_presence,
                            'left-0.5' => ! auth()->user()->show_presence,
                        ])></span>
                    </button>
                </div>
            </div>
        @endif
    </section>

    {{-- Zeitplan & Fokus --}}
    <section id="schedule" class="scroll-mt-28 space-y-5">
        <h2 class="text-lg font-medium tracking-tight text-ink">Zeitplan &amp; Fokus</h2>

        {{-- Kategorien --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Kategorien</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Wiederverwendbare Kategorien für den Zeitplan — z. B. Schule oder Training. Umbenennen oder Umfärben
            wirkt sich sofort auf alle ihre Termine aus. Kategorien mit aktivierter Funktion zeigen im Dashboard
            einen Pomodoro-Fokus-Timer.
        </p>

        <div class="space-y-2">
            @forelse ($this->categories as $category)
                <div wire:key="cat-{{ $category->id }}" x-data="{ colorOpen: false }" class="rounded-card border border-line bg-paper/60 px-3 py-2.5">
                <div class="flex items-center gap-3">
                    <div class="relative flex-none">
                        <button type="button" @click="colorOpen = !colorOpen" class="h-4 w-4 rounded-full transition hover:scale-110 {{ $swatches[$category->color] ?? 'bg-contour' }}" aria-label="Farbe ändern"></button>
                        <div
                            x-show="colorOpen"
                            @click.outside="colorOpen = false"
                            x-transition.opacity.duration.100ms
                            class="absolute left-0 top-full z-10 mt-1.5 flex gap-1.5 rounded-card border border-line bg-surface p-1.5 shadow-map"
                            style="display: none;"
                        >
                            @foreach ($swatches as $token => $bg)
                                <button type="button" wire:click="setCategoryColor({{ $category->id }}, '{{ $token }}')" @click="colorOpen = false" class="h-5 w-5 rounded-full {{ $bg }} transition hover:scale-110" aria-label="Farbe {{ $token }}"></button>
                            @endforeach
                        </div>
                    </div>

                    <input
                        type="text"
                        value="{{ $category->name }}"
                        wire:change="renameCategory({{ $category->id }}, $event.target.value)"
                        class="min-w-0 flex-1 rounded-card border-transparent bg-transparent px-1 text-sm text-ink focus:border-overprint focus:bg-paper focus:ring-0"
                    />

                    <button
                        type="button"
                        wire:click="toggleCategoryPomodoro({{ $category->id }})"
                        @class([
                            'relative h-6 w-10 flex-none rounded-full transition',
                            'bg-forest' => $category->pomodoro_enabled,
                            'bg-line' => ! $category->pomodoro_enabled,
                        ])
                        aria-label="Pomodoro-Fokus-Timer {{ $category->pomodoro_enabled ? 'deaktivieren' : 'aktivieren' }}"
                        title="Pomodoro-Fokus-Timer"
                    >
                        <span @class([
                            'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                            'left-[1.125rem]' => $category->pomodoro_enabled,
                            'left-0.5' => ! $category->pomodoro_enabled,
                        ])></span>
                    </button>

                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.deleteCategory({{ $category->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                        class="grid h-8 w-8 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                        aria-label="Kategorie löschen"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                    </button>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    @if ($category->pomodoro_enabled)
                        <button
                            type="button"
                            wire:click="manageCategoryLink({{ $category->id }})"
                            aria-label="Aufgaben-Verknüpfung für {{ $category->name }} verwalten — aktuell: {{ $category->taskSourceLabel() ?? 'keine' }}"
                            class="inline-flex w-fit items-center gap-1.5 rounded-full border border-line bg-paper/70 px-2.5 py-1 text-xs text-ink-soft transition hover:border-overprint/50 hover:bg-paper hover:text-ink"
                        >
                            <svg class="h-3 w-3 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 6.5l4 4L7 21H3v-4L13.5 6.5z"/><path d="M12 8l4 4"/></svg>
                            <span class="truncate">{{ $category->taskSourceLabel() ?? '+ Aufgaben verknüpfen' }}</span>
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="manageAttributes({{ $category->id }})"
                        aria-label="Eigene Attribute für {{ $category->name }} verwalten — aktuell: {{ $category->custom_attributes_count }}"
                        class="inline-flex w-fit items-center gap-1.5 rounded-full border border-line bg-paper/70 px-2.5 py-1 text-xs text-ink-soft transition hover:border-overprint/50 hover:bg-paper hover:text-ink"
                    >
                        <svg class="h-3 w-3 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3v18M15 3v18M3 9h18M3 15h18"/></svg>
                        <span class="truncate">
                            @if ($category->custom_attributes_count === 0)
                                + Attribute
                            @elseif ($category->custom_attributes_count === 1)
                                1 Attribut
                            @else
                                {{ $category->custom_attributes_count }} Attribute
                            @endif
                        </span>
                    </button>
                </div>
                </div>
            @empty
                <p class="text-sm text-ink-faint">Noch keine Kategorien.</p>
            @endforelse
        </div>

        <form wire:submit="addCategory" class="mt-4 flex items-center gap-2 border-t border-line pt-4">
            <input
                type="text"
                wire:model="newCategoryName"
                placeholder="Neue Kategorie — z. B. Lesen"
                autocomplete="off"
                class="min-w-0 flex-1 rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
            />
            <div class="flex flex-none gap-1.5">
                @foreach ($swatches as $token => $bg)
                    <button
                        type="button"
                        wire:click="$set('newCategoryColor', '{{ $token }}')"
                        @class([
                            'h-6 w-6 rounded-full transition', $bg,
                            'ring-2 ring-offset-2 ring-offset-surface ring-ink/60' => $newCategoryColor === $token,
                            'hover:scale-110' => $newCategoryColor !== $token,
                        ])
                        aria-label="Farbe {{ $token }}"
                    ></button>
                @endforeach
            </div>
            <button type="submit" class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                Hinzufügen
            </button>
        </form>
        @error('newCategoryName') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
        </div>

        @include('livewire.partials.category-link-sheet')
        @include('livewire.partials.category-attributes-sheet')

        {{-- Pomodoro --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h3 class="mb-1 text-base font-medium text-ink">Pomodoro</h3>
            <p class="mb-5 text-sm leading-relaxed text-ink-soft">
                Der Rhythmus, mit dem der Fokus-Timer einer Kategorie Arbeits- und Pausenphasen abwechselt.
            </p>

            <div class="grid max-w-md grid-cols-2 gap-4">
                @php
                    $fields = [
                        'pWork' => 'Work-Session (Min)',
                        'pShortBreak' => 'Kurze Pause (Min)',
                        'pLongBreak' => 'Lange Pause (Min)',
                        'pLongEvery' => 'Sessions bis lange Pause',
                    ];
                @endphp
                @foreach ($fields as $model => $label)
                    <div>
                        <label for="{{ $model }}" class="mb-1.5 block text-xs font-medium text-ink-soft">{{ $label }}</label>
                        <input id="{{ $model }}" type="number" min="1" wire:model="{{ $model }}" wire:change="saveSchedule" class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                        @error($model) <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4">
                <div>
                    <p class="text-sm font-medium text-ink">Automatisch weiterlaufen</p>
                    <p class="text-xs text-ink-soft">
                        Die erste Session startest du immer selbst. Danach: Sessions und Pausen automatisch
                        weiterlaufen lassen, statt jedes Mal manuell zu bestätigen.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="togglePomodoroAutostart"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $pAutostart,
                        'bg-line' => ! $pAutostart,
                    ])
                    aria-label="Automatisch weiterlaufen {{ $pAutostart ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $pAutostart,
                        'left-0.5' => ! $pAutostart,
                    ])></span>
                </button>
            </div>
        </div>

        {{-- Vorschau auf fällige Termine --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h3 class="mb-1 text-base font-medium text-ink">Vorschau auf Termine</h3>
            <p class="mb-5 text-sm leading-relaxed text-ink-soft">
                Deadlines, Hausaufgaben und Prüfungen erscheinen im Zeitplan an ihrem eigenen Tag — zusätzlich
                schon einige Tage vorher als Vorschau, damit nichts überraschend kommt. Ein Wunschtermin (weich)
                erscheint nur an seinem eigenen Tag.
            </p>

            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-ink">Vorschau aktivieren</p>
                <button
                    type="button"
                    wire:click="toggleDeadlinePreviewEnabled"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $deadlinePreviewEnabled,
                        'bg-line' => ! $deadlinePreviewEnabled,
                    ])
                    aria-label="Vorschau auf Termine {{ $deadlinePreviewEnabled ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $deadlinePreviewEnabled,
                        'left-0.5' => ! $deadlinePreviewEnabled,
                    ])></span>
                </button>
            </div>

            @if ($deadlinePreviewEnabled)
                <div class="mt-4 max-w-[10rem]">
                    <label for="deadlinePreviewDays" class="mb-1.5 block text-xs font-medium text-ink-soft">Tage vorher</label>
                    <input id="deadlinePreviewDays" type="number" min="0" max="14" wire:model="deadlinePreviewDays" wire:change="saveDeadlinePreviewDays" class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                    @error('deadlinePreviewDays') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    </section>

    {{-- Vorbereitung --}}
    <section id="prepare" class="scroll-mt-28 space-y-5">
        <h2 class="text-lg font-medium tracking-tight text-ink">Vorbereitung</h2>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Wann bereitest du dich vor?</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Morgens planst du den bereits laufenden Tag, abends den nächsten — bestimmt, worauf
            „Vorbereiten" im Header zielt.
        </p>

        <div class="inline-flex rounded-card border border-line bg-paper p-0.5">
            @foreach (['morning' => 'Morgens · für heute', 'evening' => 'Abends · für morgen'] as $value => $label)
                <button
                    type="button"
                    wire:click="setPrepareTimeOfDay('{{ $value }}')"
                    @class([
                        'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                        'bg-forest text-white shadow-sm' => $prepareTimeOfDay === $value,
                        'text-ink-soft hover:text-ink' => $prepareTimeOfDay !== $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Erinnerung</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Ein Stups, falls du deine Vorbereitung noch nicht gemacht hast.
        </p>

        <div class="inline-flex rounded-card border border-line bg-paper p-0.5">
            @foreach (['off' => 'Aus', 'automatic' => 'Automatisch', 'fixed' => 'Feste Zeit'] as $value => $label)
                <button
                    type="button"
                    wire:click="setPrepareReminderMode('{{ $value }}')"
                    @class([
                        'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                        'bg-forest text-white shadow-sm' => $prepareReminderMode === $value,
                        'text-ink-soft hover:text-ink' => $prepareReminderMode !== $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>

        @if ($prepareReminderMode === 'automatic')
            <p class="mt-4 text-sm leading-relaxed text-ink-soft">
                Ein Banner im Board fragt nach, sobald du die App
                {{ $prepareTimeOfDay === 'morning' ? 'morgens' : 'abends' }} öffnest und noch nicht
                vorbereitet hast. Hast du die App nicht geöffnet, kommt um
                <span class="font-medium text-ink">{{ $prepareTimeOfDay === 'morning' ? '10:00' : '21:00' }}</span>
                eine Push-Erinnerung.
            </p>
        @elseif ($prepareReminderMode === 'fixed')
            <div class="mt-4 max-w-[10rem]">
                <label for="prepareReminderTime" class="mb-1.5 block text-sm font-medium text-ink">Uhrzeit</label>
                <input
                    id="prepareReminderTime"
                    type="time"
                    wire:model="prepareReminderTime"
                    wire:change="savePrepareReminderTime"
                    class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                />
                @error('prepareReminderTime') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
            </div>
        @endif
        </div>
    </section>

    {{-- Fortschritt --}}
    <section id="progress" class="scroll-mt-28 space-y-5">
        <h2 class="text-lg font-medium tracking-tight text-ink">Fortschritt</h2>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Tagesziel</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Wie viele Aufgaben an einem Tag als "Ziel erreicht" zählen — treibt den Ring auf der
            <a href="{{ route('progress') }}" class="text-overprint hover:underline" wire:navigate>Fortschritt</a>-Seite
            und eine der beiden Feier-Animationen.
        </p>

        <div class="max-w-[8rem]">
            <label for="dailyTaskGoal" class="mb-1.5 block text-sm font-medium text-ink">Aufgaben pro Tag</label>
            <input
                id="dailyTaskGoal"
                type="number"
                min="1"
                max="30"
                wire:model="dailyTaskGoal"
                wire:change="saveDailyGoal"
                class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
            />
            @error('dailyTaskGoal') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
        </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Erinnerungen</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Zwei unabhängige Stupser, falls am Tag noch nichts (genug) passiert ist.
        </p>

        <div class="space-y-1">
            <div class="flex items-center justify-between gap-3 py-2">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">Offene Aufgaben am Abend</p>
                    <p class="text-xs text-ink-soft">Falls dann noch "Heute"-Aufgaben offen sind.</p>
                </div>
                <button
                    type="button"
                    wire:click="toggleNotifyDailyReminder"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $notifyDailyReminder,
                        'bg-line' => ! $notifyDailyReminder,
                    ])
                    aria-label="Erinnerung an offene Aufgaben {{ $notifyDailyReminder ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $notifyDailyReminder,
                        'left-0.5' => ! $notifyDailyReminder,
                    ])></span>
                </button>
            </div>

            @if ($notifyDailyReminder)
                <div class="max-w-[10rem] pb-2">
                    <label for="dailyReminderTime" class="mb-1.5 block text-sm font-medium text-ink">Uhrzeit</label>
                    <input
                        id="dailyReminderTime"
                        type="time"
                        wire:model="dailyReminderTime"
                        wire:change="saveDailyReminderTime"
                        class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    />
                    @error('dailyReminderTime') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center justify-between gap-3 py-2">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">Serie in Gefahr</p>
                    <p class="text-xs text-ink-soft">
                        Um {{ \App\Models\User::STREAK_RISK_DUE_TIME }}, falls heute noch nichts erledigt wurde und
                        die Serie sonst reissen würde.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="toggleNotifyStreakRisk"
                    @class([
                        'relative h-6 w-10 flex-none rounded-full transition',
                        'bg-forest' => $notifyStreakRisk,
                        'bg-line' => ! $notifyStreakRisk,
                    ])
                    aria-label="Serie-in-Gefahr-Erinnerung {{ $notifyStreakRisk ? 'deaktivieren' : 'aktivieren' }}"
                >
                    <span @class([
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                        'left-[1.125rem]' => $notifyStreakRisk,
                        'left-0.5' => ! $notifyStreakRisk,
                    ])></span>
                </button>
            </div>
        </div>
        </div>
    </section>

    {{-- Benachrichtigungen --}}
    <section id="notifications" class="scroll-mt-28 space-y-5">
    <h2 class="text-lg font-medium tracking-tight text-ink">Benachrichtigungen</h2>
    <div
        class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8"
        x-data="{
            vapidPublicKey: @js(config('webpush.vapid.public_key')),
            permission: (typeof Notification !== 'undefined' ? Notification.permission : 'unsupported'),
            subscribed: false,
            busy: false,
            async check() {
                this.subscribed = !!(await window.currentPushSubscription());
            },
            async subscribe() {
                this.busy = true;
                try {
                    const sub = await window.subscribeToPush(this.vapidPublicKey);
                    if (typeof Notification !== 'undefined') this.permission = Notification.permission;
                    if (!sub) return;
                    await $wire.subscribeToPush(sub.endpoint, sub.p256dh, sub.auth);
                    this.subscribed = true;
                } finally {
                    this.busy = false;
                }
            },
            async unsubscribe() {
                this.busy = true;
                try {
                    const endpoint = await window.unsubscribeFromPush();
                    if (endpoint) await $wire.unsubscribeFromPush(endpoint);
                    this.subscribed = false;
                } finally {
                    this.busy = false;
                }
            },
        }"
        x-init="check()"
    >
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Push-Benachrichtigungen für ausgewählte Momente — funktionieren auch, wenn dieser Browser
            komplett geschlossen ist.
        </p>

        <div x-show="permission === 'denied'" class="mb-5 rounded-card border border-line bg-paper/60 px-3 py-2.5" style="display: none;">
            <p class="text-sm text-ink">Benachrichtigungen sind im Browser blockiert — in den Browser-Einstellungen erlauben.</p>
        </div>

        <div x-show="permission !== 'denied'" class="mb-5 flex items-center justify-between gap-3 rounded-card border border-line bg-paper/60 px-3 py-2.5">
            <div class="min-w-0">
                <p class="text-sm text-ink" x-show="!subscribed">Auf diesem Gerät noch nicht aktiviert.</p>
                <p class="text-sm text-ink" x-show="subscribed" style="display: none;">Auf diesem Gerät aktiv.</p>
            </div>
            <button
                type="button"
                x-show="!subscribed"
                @click="subscribe()"
                :disabled="busy"
                class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] disabled:opacity-60"
            >
                Aktivieren
            </button>
            <button
                type="button"
                x-show="subscribed"
                style="display: none;"
                @click="unsubscribe()"
                :disabled="busy"
                class="flex-none rounded-card border border-line px-3.5 py-2 text-sm font-medium text-ink-soft transition hover:bg-signal-soft hover:text-signal disabled:opacity-60"
            >
                Deaktivieren
            </button>
        </div>

        {{-- Debug: sends a real push to every device on this account right now, independent of
             the notify_* toggles below, to isolate delivery problems (VAPID config, network,
             a push service rejecting the request) from "which moments should notify". --}}
        <div class="mb-5 rounded-card border border-line bg-paper/60 px-3 py-2.5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-ink-soft">Testet alle Geräte auf diesem Account, unabhängig von den Reglern unten.</p>
                <button
                    type="button"
                    wire:click="sendTestPush"
                    wire:loading.attr="disabled"
                    wire:target="sendTestPush"
                    class="flex-none rounded-card border border-line px-3.5 py-2 text-sm font-medium text-ink-soft transition hover:bg-signal-soft hover:text-signal disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="sendTestPush">Test-Benachrichtigung senden</span>
                    <span wire:loading wire:target="sendTestPush">Sende …</span>
                </button>
            </div>

            @if ($testPushSent)
                <div class="mt-3 space-y-1.5">
                    @forelse ($testPushResults as $result)
                        <div class="flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-3 py-2 text-xs">
                            <span class="min-w-0 truncate text-ink-soft">{{ $result['user_agent'] ?? 'Unbekanntes Gerät' }}</span>
                            @if ($result['success'])
                                <span class="flex-none font-medium text-forest">Zugestellt</span>
                            @else
                                <span class="flex-none font-medium text-signal" title="{{ $result['reason'] }}">
                                    Fehlgeschlagen{{ $result['status'] ? " ({$result['status']})" : '' }}
                                </span>
                            @endif
                        </div>
                        @if (! $result['success'])
                            <p class="px-1 text-[11px] text-ink-faint">{{ $result['reason'] }}</p>
                        @endif
                    @empty
                        <p class="text-xs text-ink-soft">Keine Geräte abonniert — zuerst oben aktivieren.</p>
                    @endforelse
                </div>
            @endif
        </div>

        <div class="space-y-1">
            @php
                $notifyRows = [
                    ['key' => 'notify_event_start', 'action' => 'toggleNotifyEventStart', 'label' => 'Beginn von Terminen & Kategorien', 'hint' => 'Jeder Zeitplan-Block, sobald seine Startzeit erreicht ist.'],
                    ['key' => 'notify_pomo_start', 'action' => 'toggleNotifyPomoStart', 'label' => 'Start einer Pomodoro-Session', 'hint' => 'Die erste Session und jede automatisch/manuell folgende.'],
                    ['key' => 'notify_break_start', 'action' => 'toggleNotifyBreakStart', 'label' => 'Start einer Pause', 'hint' => 'Kurze und lange Pausen.'],
                    ['key' => 'notify_event_upcoming', 'action' => 'toggleNotifyEventUpcoming', 'label' => '5 Minuten vor Terminen & Kategorien', 'hint' => 'Ein zusätzlicher Hinweis, kurz bevor ein Zeitplan-Block beginnt.'],
                ];
            @endphp
            @foreach ($notifyRows as $row)
                <div class="flex items-center justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">{{ $row['label'] }}</p>
                        <p class="text-xs text-ink-soft">{{ $row['hint'] }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="{{ $row['action'] }}"
                        @class([
                            'relative h-6 w-10 flex-none rounded-full transition',
                            'bg-forest' => auth()->user()->{$row['key']},
                            'bg-line' => ! auth()->user()->{$row['key']},
                        ])
                        aria-label="{{ $row['label'] }} {{ auth()->user()->{$row['key']} ? 'deaktivieren' : 'aktivieren' }}"
                    >
                        <span @class([
                            'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                            'left-[1.125rem]' => auth()->user()->{$row['key']},
                            'left-0.5' => ! auth()->user()->{$row['key']},
                        ])></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
    </section>

    {{-- Shortcuts, API & MCP --}}
    <section id="developer" class="scroll-mt-28 space-y-5">
    <h2 class="text-lg font-medium tracking-tight text-ink">Entwickler</h2>
    <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
            <h3 class="text-base font-medium text-ink">Shortcuts, API & MCP</h3>
            <span class="flex gap-3 text-sm font-medium">
                <a href="{{ route('docs.api') }}" class="text-overprint hover:underline" wire:navigate>
                    API-Doku →
                </a>
                <a href="{{ route('docs.mcp') }}" class="text-overprint hover:underline" wire:navigate>
                    MCP-Doku →
                </a>
            </span>
        </div>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Persönliche Zugriffstoken für Apple Shortcuts, andere Automatisierungen, und für einen
            MCP-Client wie Claude (siehe MCP-Doku). Jedes Token trägt die Rechte, die du unten wählst —
            Lesen ist bei jedem Token dabei, Schreiben und Löschen sind einzeln zuschaltbar.
        </p>

        @if ($createdToken)
            <div x-data="{ copied: false }" class="mb-5 rounded-card border border-forest/40 bg-forest/10 p-4">
                <p class="mb-2 text-sm font-medium text-ink">Token erstellt — jetzt kopieren, es wird nicht wieder angezeigt.</p>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $createdToken }}"
                        onclick="this.select()"
                        class="min-w-0 flex-1 rounded-card border border-line bg-paper px-3 py-2 font-mono text-xs text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    />
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText('{{ $createdToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="flex-none rounded-card bg-forest px-3 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                    >
                        <span x-show="!copied">Kopieren</span>
                        <span x-show="copied" style="display: none;">Kopiert ✓</span>
                    </button>
                </div>
                <button type="button" wire:click="dismissCreatedToken" class="mt-3 text-xs text-ink-soft hover:text-ink">
                    Fertig
                </button>
            </div>
        @endif

        <div class="space-y-2">
            @forelse ($this->apiTokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-center gap-3 rounded-card border border-line bg-paper/60 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $token->name }}</p>
                        <p class="text-xs text-ink-faint">
                            Erstellt {{ $token->created_at->locale('de_CH')->diffForHumans() }}
                            @if ($token->last_used_at)
                                · zuletzt verwendet {{ $token->last_used_at->locale('de_CH')->diffForHumans() }}
                            @else
                                · noch nie verwendet
                            @endif
                        </p>
                        <p class="mt-1 flex flex-wrap gap-1">
                            @php $abilities = $token->abilities ?? []; @endphp
                            @if (in_array('*', $abilities, true))
                                <span class="rounded-full bg-signal-soft px-2 py-0.5 text-[11px] font-medium text-signal">Alle Rechte (alt)</span>
                            @else
                                <span class="rounded-full bg-line px-2 py-0.5 text-[11px] font-medium text-ink-soft">Lesen</span>
                                @if (in_array('mcp:write', $abilities, true))
                                    <span class="rounded-full bg-line px-2 py-0.5 text-[11px] font-medium text-ink-soft">Schreiben</span>
                                @endif
                                @if (in_array('mcp:delete', $abilities, true))
                                    <span class="rounded-full bg-signal-soft px-2 py-0.5 text-[11px] font-medium text-signal">Löschen</span>
                                @endif
                            @endif
                        </p>
                    </div>
                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.revokeApiToken({{ $token->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                        class="grid h-8 w-8 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                        aria-label="Token widerrufen"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                    </button>
                </div>
            @empty
                <p class="text-sm text-ink-faint">Noch keine Token.</p>
            @endforelse
        </div>

        <form wire:submit="createApiToken" class="mt-4 space-y-3 border-t border-line pt-4">
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    wire:model="newTokenName"
                    placeholder="Name — z. B. iPhone Shortcuts / Claude"
                    autocomplete="off"
                    class="min-w-0 flex-1 rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                />
                <button type="submit" class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    Token erstellen
                </button>
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink-soft">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="newTokenCanWrite" class="rounded border-line text-forest focus:ring-forest" />
                    Schreiben erlauben (Aufgaben anlegen/ändern, Einstellungen setzen)
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="newTokenCanDelete" class="rounded border-line text-signal focus:ring-signal" />
                    Löschen erlauben
                </label>
            </div>
        </form>
        @error('newTokenName') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
    </div>
    </section>

    </div>
</div>
