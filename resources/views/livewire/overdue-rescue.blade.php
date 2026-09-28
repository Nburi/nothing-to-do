{{-- Overdue rescue banner — see App\Livewire\OverdueRescue. The root <div> is
     permanent (Livewire rejects a root that can compile to nothing); only its
     content is conditional. `wire:poll.30s.visible` keeps the count honest
     while the board is open: completing a task doesn't re-render this
     component, and a banner insisting on "3 überfällig" after the user just
     did one of them reads as broken. Actions re-query anyway. --}}
<div wire:poll.30s.visible>
    @if ($appliedLabel !== null)
        {{-- The confirmation + undo. The move already happened; this is the
             way back, not a question. --}}
        <div class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6" x-data x-init="setTimeout(() => $wire.acknowledge(), 9000)">
            <div class="flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-map" role="status">
                <p class="min-w-0 text-sm text-ink-soft">{{ $appliedLabel }}</p>
                <div class="flex flex-none items-center gap-1">
                    <button type="button" wire:click="undo" class="rounded-full px-3 py-1.5 text-sm font-medium text-forest transition hover:bg-forest-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-forest sm:py-1">
                        Rückgängig
                    </button>
                    <button type="button" wire:click="acknowledge" class="grid h-8 w-8 place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest" aria-label="Schliessen">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 4 8 8m0-8-8 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @elseif ($this->softOverdue->isNotEmpty() && ! $this->dismissedToday)
        @php
            $overdue = $this->softOverdue;
            $count = $overdue->count();
        @endphp
        <div class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6">
            <section
                x-data="{ open: false }"
                class="rounded-card border border-line bg-contour-soft px-4 py-3.5"
                aria-label="Überfällige Aufgaben"
            >
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">
                            {{ $count === 1 ? '1 Aufgabe wartet' : $count.' Aufgaben warten' }} noch auf ein neues Datum.
                        </p>
                        <p class="mt-0.5 text-xs text-ink-soft">
                            Ihr Wunschtermin ist vorbei — das ist kein Drama. Neu legen?
                            @if ($this->hardOverdueCount > 0)
                                <span class="text-ink-faint">
                                    ({{ $this->hardOverdueCount === 1 ? 'Eine weitere hat' : $this->hardOverdueCount.' weitere haben' }} eine verpasste Deadline — die fasse ich nicht an.)
                                </span>
                            @endif
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="dismiss"
                        class="grid h-8 w-8 flex-none place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest"
                        aria-label="Heute nicht mehr anzeigen"
                        title="Heute nicht mehr anzeigen"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 4 8 8m0-8-8 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    @foreach (['today' => 'Auf heute', 'tomorrow' => 'Auf morgen', 'monday' => 'Auf Montag', 'clear' => 'Datum entfernen'] as $when => $label)
                        <button
                            type="button"
                            wire:click="reschedule('{{ $when }}')"
                            wire:loading.attr="disabled"
                            wire:target="reschedule"
                            class="rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink transition hover:border-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-forest disabled:opacity-60 sm:py-1"
                        >{{ $label }}</button>
                    @endforeach
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open"
                        class="rounded-full px-2.5 py-1.5 text-xs text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest sm:py-1"
                    >
                        <span x-text="open ? 'Liste ausblenden' : 'Welche?'"></span>
                    </button>
                </div>

                <ul x-show="open" x-cloak style="display: none;" class="mt-3 space-y-1 border-t border-line pt-3">
                    @foreach ($overdue->take(8) as $task)
                        <li class="flex items-baseline justify-between gap-3 text-sm" wire:key="overdue-{{ $task->id }}">
                            <span class="min-w-0 truncate text-ink">{{ $task->title }}</span>
                            <span class="tnum flex-none text-xs text-ink-faint">{{ $task->due_date->format('d.m.') }}</span>
                        </li>
                    @endforeach
                    @if ($count > 8)
                        <li class="text-xs text-ink-faint">… und {{ $count - 8 }} weitere</li>
                    @endif
                </ul>
            </section>
        </div>
    @endif
</div>
