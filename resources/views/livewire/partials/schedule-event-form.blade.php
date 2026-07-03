@php
    $swatches = [
        'contour' => 'bg-contour',
        'overprint' => 'bg-overprint',
        'forest' => 'bg-forest',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
    ];
    $wdLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
@endphp

<div x-data="{ open: $wire.entangle('showEventForm') }" x-cloak>
    <div
        x-show="open"
        x-transition.opacity.duration.150ms
        @click="$wire.cancelEventForm()"
        class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"
        style="display: none;"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="opacity-0 translate-y-6 md:translate-y-2 md:scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed inset-x-0 bottom-0 z-50 md:inset-0 md:m-auto md:h-fit md:max-w-md"
        style="display: none;"
    >
        <div class="mx-auto max-h-[88dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map md:rounded-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">{{ $editingEventId ? 'Eintrag bearbeiten' : 'Neuer Eintrag' }}</h2>
                <button wire:click="cancelEventForm" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="saveEventForm" class="space-y-4">
                <div class="inline-flex rounded-card border border-line bg-paper p-0.5">
                    @foreach (['appointment' => 'Termin', 'category' => 'Kategorie'] as $val => $lbl)
                        <button
                            type="button"
                            wire:click="$set('eventKind', '{{ $val }}')"
                            @class([
                                'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                'bg-forest text-white shadow-sm' => $eventKind === $val,
                                'text-ink-soft hover:text-ink' => $eventKind !== $val,
                            ])
                        >{{ $lbl }}</button>
                    @endforeach
                </div>

                @if ($eventKind === 'category')
                    <div>
                        @if ($this->categories->isEmpty())
                            <p class="rounded-card border border-line bg-paper/60 p-3 text-sm leading-relaxed text-ink-soft">
                                Noch keine Kategorien angelegt.
                                <a href="{{ route('settings') }}" wire:navigate class="font-medium text-overprint hover:underline">In den Einstellungen anlegen →</a>
                            </p>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->categories as $cat)
                                    <button
                                        type="button"
                                        wire:click="$set('eventCategoryId', {{ $cat->id }})"
                                        @class([
                                            'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                                            'border-ink/60 bg-paper text-ink' => $eventCategoryId === $cat->id,
                                            'border-line text-ink-soft hover:border-ink-faint/60' => $eventCategoryId !== $cat->id,
                                        ])
                                    >
                                        <span class="h-2.5 w-2.5 rounded-full {{ $swatches[$cat->color] ?? 'bg-contour' }}"></span>
                                        {{ $cat->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('eventCategoryId') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <input
                            type="text"
                            wire:model="eventTitle"
                            placeholder="Titel — z. B. Zahnarzt"
                            autocomplete="off"
                            class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                        />
                        @error('eventTitle') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-ink-faint">Datum</label>
                    <input type="date" wire:model="eventDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    @error('eventDate') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-ink-faint">Start</label>
                        <input type="time" wire:model="eventStart" step="300" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-ink-faint">Ende</label>
                        <input type="time" wire:model="eventEnd" step="300" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    </div>
                </div>
                @error('eventEnd') <p class="-mt-2 text-xs text-signal">{{ $message }}</p> @enderror

                @if ($eventKind === 'appointment')
                    <div>
                        <label class="mb-1.5 block text-[11px] font-medium text-ink-faint">Farbe</label>
                        <div class="flex gap-2.5">
                            @foreach ($swatches as $token => $bg)
                                <button
                                    type="button"
                                    wire:click="$set('eventColor', '{{ $token }}')"
                                    @class([
                                        'h-7 w-7 rounded-full transition', $bg,
                                        'ring-2 ring-offset-2 ring-offset-surface ring-ink/60' => $eventColor === $token,
                                        'hover:scale-110' => $eventColor !== $token,
                                    ])
                                    aria-label="Farbe {{ $token }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($editingEventId === null)
                    <div x-data="{ rec: $wire.entangle('eventRecurring'), days: $wire.entangle('eventDays') }" class="rounded-card border border-line bg-paper/60 p-3">
                        <label class="flex cursor-pointer items-center justify-between">
                            <span class="text-sm text-ink">Wiederholen</span>
                            <input type="checkbox" x-model="rec" class="rounded border-line text-forest focus:ring-forest" />
                        </label>
                        <div x-show="rec" x-transition.opacity.duration.150ms class="mt-3 flex flex-wrap gap-1.5" style="display:none">
                            @foreach ($wdLabels as $i => $lbl)
                                @php $iso = $i + 1; @endphp
                                <button
                                    type="button"
                                    @click="days.includes({{ $iso }}) ? days = days.filter(d => d !== {{ $iso }}) : days.push({{ $iso }})"
                                    class="h-8 w-8 rounded-full border text-xs font-medium transition"
                                    :class="days.includes({{ $iso }}) ? 'border-forest bg-forest text-white' : 'border-line bg-surface text-ink-soft hover:border-ink-faint/60'"
                                >{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if ($eventKind === 'appointment')
                        <label class="flex cursor-pointer items-center gap-2.5">
                            <input type="checkbox" wire:model="eventSaveAsTemplate" class="rounded border-line text-forest focus:ring-forest" />
                            <span class="text-sm text-ink-soft">Als Vorlage speichern</span>
                        </label>
                    @endif
                @endif

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-card bg-forest px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                        {{ $editingEventId ? 'Speichern' : 'Hinzufügen' }}
                    </button>
                    @if ($editingEventId)
                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click="if (armed) { $wire.deleteEvent({{ $editingEventId }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'bg-signal border-signal text-white' : 'border-line text-signal hover:bg-signal-soft'"
                            class="grid h-11 w-11 flex-none place-items-center rounded-card border transition active:scale-95"
                            aria-label="Eintrag löschen"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
