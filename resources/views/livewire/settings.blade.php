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
        @foreach (['general' => 'Allgemein', 'schedule' => 'Zeitplan & Fokus', 'prepare' => 'Vorbereitung', 'notifications' => 'Benachrichtigungen', 'developer' => 'Entwickler'] as $id => $label)
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

        <form wire:submit="save" class="max-w-xs space-y-4">
            <div>
                <label for="resetTime" class="mb-1.5 block text-sm font-medium text-ink">Verschwinden um</label>
                <input
                    id="resetTime"
                    type="time"
                    wire:model="resetTime"
                    class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                />
                @error('resetTime')
                    <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
                @enderror
            </div>

            <div
                x-data="{ saved: false }"
                @saved.window="saved = true; setTimeout(() => saved = false, 2200)"
                class="flex items-center gap-3"
            >
                <button
                    type="submit"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >
                    Speichern
                </button>
                <span
                    x-show="saved"
                    x-transition:enter="transition duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="inline-flex items-center gap-1.5 text-sm text-ink-soft"
                    style="display: none;"
                >
                    <svg class="h-4 w-4 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Gespeichert
                </span>
            </div>
        </form>
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

        {{-- Zeitzone --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h3 class="mb-1 text-base font-medium text-ink">Zeitzone</h3>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Stunden-Versatz zu UTC — z. B. <span class="font-medium text-ink">+1</span> für die Schweizer Winterzeit
            oder <span class="font-medium text-ink">+5.5</span> für halbe/viertel Zeitzonen (z. B. Indien, Nepal).
        </p>

        <form
            wire:submit="saveTimezone"
            x-data="{ saved: false }"
            @timezone-saved.window="saved = true; setTimeout(() => saved = false, 2200)"
            class="max-w-xs space-y-4"
        >
            <div>
                <label for="timezoneOffset" class="mb-1.5 block text-sm font-medium text-ink">UTC-Versatz (Stunden)</label>
                <input
                    id="timezoneOffset"
                    type="number"
                    step="0.25"
                    min="-12"
                    max="14"
                    wire:model="timezoneOffset"
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
                    wire:click="$set('timezoneAutoDst', {{ $timezoneAutoDst ? 'false' : 'true' }})"
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

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >
                    Speichern
                </button>
                <span
                    x-show="saved"
                    x-transition:enter="transition duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="inline-flex items-center gap-1.5 text-sm text-ink-soft"
                    style="display: none;"
                >
                    <svg class="h-4 w-4 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Gespeichert
                </span>
            </div>
        </form>
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
                <div wire:key="cat-{{ $category->id }}" x-data="{ colorOpen: false }" class="flex items-center gap-3 rounded-card border border-line bg-paper/60 px-3 py-2.5">
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

        {{-- Pomodoro --}}
        <form
        wire:submit="saveSchedule"
        x-data="{ saved: false }"
        @schedule-saved.window="saved = true; setTimeout(() => saved = false, 2200)"
    >
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
                        <input id="{{ $model }}" type="number" min="1" wire:model="{{ $model }}" class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
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
                    wire:click="$set('pAutostart', {{ $pAutostart ? 'false' : 'true' }})"
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

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Speichern
                </button>
                <span
                    x-show="saved"
                    x-transition:enter="transition duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="inline-flex items-center gap-1.5 text-sm text-ink-soft"
                    style="display: none;"
                >
                    <svg class="h-4 w-4 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Gespeichert
                </span>
            </div>
        </div>
        </form>

        {{-- Vorschau auf fällige Termine --}}
        <form
            wire:submit="saveDeadlinePreview"
            x-data="{ saved: false }"
            @deadline-preview-saved.window="saved = true; setTimeout(() => saved = false, 2200)"
        >
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
                    wire:click="$set('deadlinePreviewEnabled', {{ $deadlinePreviewEnabled ? 'false' : 'true' }})"
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
                    <input id="deadlinePreviewDays" type="number" min="0" max="14" wire:model="deadlinePreviewDays" class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                    @error('deadlinePreviewDays') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Speichern
                </button>
                <span
                    x-show="saved"
                    x-transition:enter="transition duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="inline-flex items-center gap-1.5 text-sm text-ink-soft"
                    style="display: none;"
                >
                    <svg class="h-4 w-4 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Gespeichert
                </span>
            </div>
        </div>
        </form>
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

    {{-- Shortcuts & API --}}
    <section id="developer" class="scroll-mt-28 space-y-5">
    <h2 class="text-lg font-medium tracking-tight text-ink">Entwickler</h2>
    <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <div class="mb-1 flex items-center justify-between gap-3">
            <h3 class="text-base font-medium text-ink">Shortcuts & API</h3>
            <a href="{{ route('docs.api') }}" class="text-sm font-medium text-overprint hover:underline" wire:navigate>
                API-Dokumentation →
            </a>
        </div>
        <p class="mb-5 text-sm leading-relaxed text-ink-soft">
            Persönliche Zugriffstoken für Apple Shortcuts und andere Automatisierungen. Ein Token verhält sich wie
            ein Passwort für deinen Account — jedes trägt volle Rechte auf alle deine Daten.
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

        <form wire:submit="createApiToken" class="mt-4 flex items-center gap-2 border-t border-line pt-4">
            <input
                type="text"
                wire:model="newTokenName"
                placeholder="Name — z. B. iPhone Shortcuts"
                autocomplete="off"
                class="min-w-0 flex-1 rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
            />
            <button type="submit" class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                Token erstellen
            </button>
        </form>
        @error('newTokenName') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
    </div>
    </section>

    </div>
</div>
