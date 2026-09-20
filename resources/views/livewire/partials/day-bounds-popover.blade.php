{{-- Tagesrahmen editor, shared by the Zeitplan (one date) and the Wochenplan
     (one weekday) — see ManagesDayBounds. A bottom sheet, the same shape as
     edit-sheet / category-link-sheet, rather than an anchored popover: the
     trigger is a 9px chip inside a scrolling grid header, which is a poor
     anchor at every width and unreachable on a narrow phone. --}}
@if ($boundsScope !== null)
    <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-labelledby="day-bounds-heading" wire:key="day-bounds-sheet">
        <div class="absolute inset-0 bg-ink/40" wire:click="cancelDayBounds"></div>
        <div
            class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card"
            @keydown.escape.window="$wire.cancelDayBounds()"
        >
            <div class="mb-1 flex items-baseline justify-between gap-3">
                <h2 id="day-bounds-heading" class="text-base font-medium text-ink">{{ $this->boundsHeading() }}</h2>
                <span class="text-[11px] uppercase tracking-wide text-ink-faint">Tagesrahmen</span>
            </div>
            <p class="mb-4 text-xs text-ink-faint">
                Wann die Zeitachse an diesem {{ $boundsScope === 'weekday' ? 'Wochentag' : 'Tag' }} beginnt und endet.
                Ein Block ausserhalb bleibt sichtbar — die Achse wird dann automatisch geweitet.
            </p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="boundsStart" class="mb-1 block text-xs text-ink-faint">Ich stehe auf um</label>
                    <select
                        id="boundsStart"
                        wire:model="boundsStart"
                        class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    >
                        @foreach ($this->dayBoundOptions(true) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('boundsStart') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="boundsEnd" class="mb-1 block text-xs text-ink-faint">Ich gehe ins Bett um</label>
                    <select
                        id="boundsEnd"
                        wire:model="boundsEnd"
                        class="tnum block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    >
                        @foreach ($this->dayBoundOptions(false) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('boundsEnd') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex items-center gap-2">
                <button
                    type="button"
                    wire:click="saveDayBounds"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                >Übernehmen</button>
                <button
                    type="button"
                    wire:click="cancelDayBounds"
                    class="rounded-card border border-line bg-surface px-4 py-2 text-sm text-ink-soft transition hover:text-ink"
                >Abbrechen</button>

                {{-- Not destructive — resetting only drops this row's own
                     override so it inherits the tier above again, which is
                     exactly the frame it would have had untouched. No armed
                     double-click needed for something that loses nothing. --}}
                @if ($boundsHasOverride)
                    <button
                        type="button"
                        wire:click="resetDayBounds"
                        class="ml-auto text-xs text-ink-faint underline transition hover:text-ink"
                    >{{ $boundsScope === 'weekday' ? 'Standard verwenden' : 'Wochentag verwenden' }}</button>
                @endif
            </div>
        </div>
    </div>
@endif
