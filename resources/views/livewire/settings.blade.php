@php
    $swatches = [
        'contour' => 'bg-contour',
        'overprint' => 'bg-overprint',
        'forest' => 'bg-forest',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
    ];
@endphp

<div class="mx-auto max-w-3xl space-y-5 px-5 py-10 sm:px-6">
    <div class="flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Einstellungen</h1>
    </div>

    <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h2 class="mb-1 text-base font-medium text-ink">Erledigte Aufgaben</h2>
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

    {{-- Zeitzone --}}
    <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h2 class="mb-1 text-base font-medium text-ink">Zeitzone</h2>
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

    {{-- Kategorien --}}
    <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h2 class="mb-1 text-base font-medium text-ink">Kategorien</h2>
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
        class="space-y-5"
    >
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Pomodoro</h2>
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
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-paper">
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
    </form>
</div>
