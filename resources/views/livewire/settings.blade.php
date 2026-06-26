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

    <form
        wire:submit="saveSchedule"
        x-data="{ saved: false }"
        @schedule-saved.window="saved = true; setTimeout(() => saved = false, 2200)"
        class="space-y-5"
    >
        {{-- Brief --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Brief</h2>
            <p class="mb-5 text-sm leading-relaxed text-ink-soft">
                Der Brief hilft dir, den nächsten Tag zu planen. Die App schlägt ihn zur eingestellten Zeit vor — starten tust du ihn selbst.
            </p>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Wann</label>
                    <div class="inline-flex rounded-card border border-line bg-paper p-0.5">
                        @foreach (['evening' => 'Abends (morgen planen)', 'morning' => 'Morgens (heute planen)'] as $val => $lbl)
                            <button
                                type="button"
                                wire:click="$set('briefWhen', '{{ $val }}')"
                                @class([
                                    'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                    'bg-forest text-white shadow-sm' => $briefWhen === $val,
                                    'text-ink-soft hover:text-ink' => $briefWhen !== $val,
                                ])
                            >{{ $lbl }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="max-w-xs">
                    <label for="briefTime" class="mb-1.5 block text-sm font-medium text-ink">Vorschlag um</label>
                    <input id="briefTime" type="time" wire:model="briefTime" class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                    @error('briefTime') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <button type="button" wire:click="startBrief" class="inline-flex items-center gap-2 rounded-card border border-overprint/40 bg-overprint-soft px-4 py-2 text-sm font-medium text-overprint transition hover:border-overprint/70 active:scale-[0.98]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    Brief jetzt starten
                </button>
            </div>
        </div>

        {{-- Pomodoro --}}
        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Pomodoro</h2>
            <p class="mb-5 text-sm leading-relaxed text-ink-soft">
                Der Rhythmus, mit dem der Brief deine Work-Sessions erzeugt.
            </p>

            <div class="grid max-w-md grid-cols-2 gap-4">
                @php
                    $fields = [
                        'pWork' => ['Work-Session (Min)', 5, 120],
                        'pShortBreak' => ['Kurze Pause (Min)', 1, 60],
                        'pLongBreak' => ['Lange Pause (Min)', 1, 120],
                        'pLongEvery' => ['Sessions bis lange Pause', 2, 12],
                    ];
                @endphp
                @foreach ($fields as $model => [$label, $min, $max])
                    <div>
                        <label for="{{ $model }}" class="mb-1.5 block text-xs font-medium text-ink-soft">{{ $label }}</label>
                        <input id="{{ $model }}" type="number" min="{{ $min }}" max="{{ $max }}" wire:model="{{ $model }}" class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
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
