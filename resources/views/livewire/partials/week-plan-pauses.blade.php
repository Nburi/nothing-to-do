@php
    $wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
@endphp

<div class="rounded-card border border-line bg-surface p-4 shadow-map">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-medium text-ink">Pausen &amp; Ferien</h2>
            <p class="mt-0.5 text-xs text-ink-faint">An diesen Tagen füllt der Wochenplan den Zeitplan nicht.</p>
        </div>
        <button wire:click="openPauseForm" class="inline-flex flex-none items-center gap-1.5 rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:text-ink active:scale-95">
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
            Ferien eintragen
        </button>
    </div>

    @if (empty($this->pausedRanges))
        <p class="rounded-card border border-dashed border-line bg-paper/60 p-3 text-center text-xs text-ink-faint">Keine Pausen geplant.</p>
    @else
        <ul class="space-y-1.5">
            @foreach ($this->pausedRanges as $range)
                @php
                    $isSingle = $range['start']->isSameDay($range['end']);
                    $label = $isSingle
                        ? $range['start']->isoFormat('D. MMM')
                        : $range['start']->isoFormat('D.').'–'.$range['end']->isoFormat('D. MMM');
                    $dayCount = (int) $range['start']->diffInDays($range['end']) + 1;
                @endphp
                <li wire:key="pause-range-{{ $range['start']->toDateString() }}" x-data="{ expanded: false }" class="rounded-card border border-line bg-paper/60 px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" @click="expanded = !expanded" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                            <svg class="h-3.5 w-3.5 flex-none text-ink-faint transition" :class="expanded ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 5l6 5-6 5"/></svg>
                            <span class="tnum truncate text-sm text-ink">{{ $label }}</span>
                            @if (! $isSingle)
                                <span class="flex-none text-xs text-ink-faint">({{ $dayCount }} Tage)</span>
                            @endif
                            @if ($range['note'])
                                <span class="truncate text-xs text-ink-faint">· {{ $range['note'] }}</span>
                            @endif
                        </button>
                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click="if (armed) { $wire.unpauseRange('{{ $range['start']->toDateString() }}', '{{ $range['end']->toDateString() }}'); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                            class="grid h-7 w-7 flex-none place-items-center rounded-card transition active:scale-95"
                            aria-label="Pause für diesen Zeitraum aufheben"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        </button>
                    </div>

                    {{-- One click per day, no arming — switching a single day back on
                         inside a longer pause is low-stakes and meant to be quick
                         (the makeup-exam case), unlike dropping the whole range. --}}
                    <div x-show="expanded" x-transition.opacity.duration.150ms class="mt-2 flex flex-wrap gap-1.5 border-t border-line/60 pt-2" style="display:none">
                        @php $d = $range['start']->copy(); @endphp
                        @while ($d->lessThanOrEqualTo($range['end']))
                            <button
                                type="button"
                                wire:click="unpauseDate('{{ $d->toDateString() }}')"
                                class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs text-ink-soft transition hover:border-forest/50 hover:text-forest active:scale-95"
                                title="{{ $wd[$d->dayOfWeekIso - 1] }} {{ $d->isoFormat('D.M.') }} wieder einschalten"
                            >{{ $wd[$d->dayOfWeekIso - 1] }} {{ $d->day }}.</button>
                            @php $d->addDay(); @endphp
                        @endwhile
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ════════════════ Pause form ════════════════ --}}
    <div x-data="{ open: $wire.entangle('showPauseForm') }" x-cloak>
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            @click="$wire.cancelPauseForm()"
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
            class="fixed inset-x-0 bottom-0 z-50 md:inset-0 md:m-auto md:h-fit md:max-w-sm"
            style="display: none;"
        >
            <div class="mx-auto max-h-[88dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map md:rounded-card">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-medium text-ink">Ferien eintragen</h2>
                    <button wire:click="cancelPauseForm" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="savePauseRange" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Von</label>
                            <input type="date" wire:model="pauseFrom" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                            @error('pauseFrom') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Bis</label>
                            <input type="date" wire:model="pauseTo" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                            @error('pauseTo') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-ink-faint">Grund (optional)</label>
                        <input type="text" wire:model="pauseNote" placeholder="z. B. Sportferien" maxlength="255" autocomplete="off" class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0" />
                    </div>
                    <button type="submit" class="w-full rounded-card bg-forest px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                        Pausieren
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
