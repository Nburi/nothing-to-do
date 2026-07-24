@php
    $wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $span = $dayEnd - $dayStart;
@endphp

<div
    x-data
    x-init="$store.prepare.init({
        inbox: @js($this->inboxQueue->pluck('id')),
        review: @js($this->reviewQueue->pluck('id')),
    })"
    :class="$store.prepare.phase === 'schedule' ? 'mx-auto max-w-2xl px-4 pb-10 pt-5' : 'mx-auto max-w-md px-4 pb-10 pt-5'"
>
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft transition hover:text-ink">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3 2 8l4 5M2 8h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Schliessen
        </a>
        <div class="flex items-center gap-1.5" x-show="$store.prepare.phase !== 'done'" style="display: none">
            <span class="h-1.5 w-1.5 rounded-full transition" :class="$store.prepare.stepIndex === 0 ? 'scale-125 bg-ink' : ($store.prepare.stepIndex > 0 ? 'bg-forest' : 'bg-line')"></span>
            <span class="h-1.5 w-1.5 rounded-full transition" :class="$store.prepare.stepIndex === 1 ? 'scale-125 bg-ink' : ($store.prepare.stepIndex > 1 ? 'bg-forest' : 'bg-line')"></span>
            <span class="h-1.5 w-1.5 rounded-full transition" :class="$store.prepare.stepIndex === 2 ? 'scale-125 bg-ink' : ($store.prepare.stepIndex > 2 ? 'bg-forest' : 'bg-line')"></span>
        </div>
        <p class="tnum text-xs text-ink-faint" x-text="$store.prepare.remainingLabel" style="display: block"></p>
    </div>

    {{-- Phase 1: Inbox leeren --}}
    <div x-cloak x-show="$store.prepare.phase === 'inbox'">
        <h1 class="mb-5 text-center text-xs font-medium uppercase tracking-wide text-ink-faint">Inbox leeren</h1>
        <div class="relative grid" style="min-height: 260px">
            @forelse ($this->inboxQueue as $task)
                @include('livewire.partials.prepare-card-inbox', ['task' => $task])
            @empty
            @endforelse
        </div>
    </div>

    {{-- Phase 2: Für morgen planen --}}
    <div x-cloak x-show="$store.prepare.phase === 'review'">
        <h1 class="mb-5 text-center text-xs font-medium uppercase tracking-wide text-ink-faint">Für morgen planen</h1>
        <div class="relative grid" style="min-height: 260px">
            @forelse ($this->reviewQueue as $task)
                @include('livewire.partials.prepare-card-review', ['task' => $task])
            @empty
            @endforelse
        </div>
    </div>

    {{-- Phase 3: Zeitplan für morgen — the existing Zeitplan timeline, pointed at tomorrow. --}}
    <div x-cloak x-show="$store.prepare.phase === 'schedule'">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xs font-medium uppercase tracking-wide text-ink-faint">Zeitplan für morgen</h1>
                <p class="text-sm text-ink-soft">Morgen · {{ $wd[$this->tomorrow->dayOfWeekIso - 1] }}, {{ $this->tomorrow->isoFormat('D.M.') }}</p>
            </div>
            <button
                type="button"
                wire:click="openEventForm('{{ $this->tomorrow->toDateString() }}')"
                class="grid h-10 w-10 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95"
                aria-label="Termin hinzufügen"
            >
                <svg class="h-4.5 w-4.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>

        @if ($this->tomorrowFlagged->isNotEmpty())
            <div class="mb-3 flex items-center gap-2 overflow-x-auto">
                <span class="flex-none text-[11px] text-ink-faint">Für morgen:</span>
                @foreach ($this->tomorrowFlagged as $t)
                    <span class="flex-none whitespace-nowrap rounded-full bg-forest-soft px-2.5 py-1 text-xs text-ink">{{ $t->title }}</span>
                @endforeach
            </div>
        @endif

        @if ($this->templates->isNotEmpty())
            <div class="mb-3 flex items-center gap-2 overflow-x-auto">
                <span class="flex-none text-[11px] text-ink-faint">Vorlagen:</span>
                @foreach ($this->templates as $t)
                    <button wire:click="applyTemplate({{ $t->id }}, '{{ $this->tomorrow->toDateString() }}')" class="flex-none whitespace-nowrap rounded-card border border-line bg-surface px-2.5 py-1 text-xs text-ink-soft transition active:scale-95 hover:text-ink">
                        + {{ $t->displayName() }}
                    </button>
                @endforeach
            </div>
        @endif

        <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
            <div class="flex" style="height: 480px">
                <div class="relative w-10 flex-none" aria-hidden="true">
                    @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                        <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%">{{ sprintf('%02d', $h) }}</span>
                    @endfor
                </div>
                <div
                    wire:key="prep-draw-grid-{{ $this->tomorrow->toDateString() }}"
                    class="relative flex-1 border-l border-line/60"
                    data-grid
                    data-span="{{ $span }}"
                    data-day-start="{{ $dayStart }}"
                    x-data="scheduleDraw({ date: '{{ $this->tomorrow->toDateString() }}' })"
                    @pointerdown.self="beginDraw"
                    @pointermove="moveDraw"
                    @pointerup="finishDraw"
                    :class="$store.draw.active ? 'cursor-crosshair' : ''"
                    style="touch-action: none"
                >
                    @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                        <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%"></div>
                    @endfor

                    @forelse ($this->tomorrowEvents as $event)
                        @include('livewire.partials.schedule-event', ['event' => $event, 'compact' => false])
                    @empty
                        <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 text-center">
                            <p class="text-sm text-ink-faint">Noch kein Termin für morgen.</p>
                            <p class="mt-1 text-xs text-ink-faint">Kategorie unten wählen und auf dem Zeitstrahl ziehen.</p>
                        </div>
                    @endforelse

                    {{-- Draw preview block --}}
                    <div
                        x-show="drawing"
                        :style="`top:${previewTop}%; height:${Math.max(previewHeight, 0.5)}%; ${previewColorStyle}`"
                        class="pointer-events-none absolute inset-x-0.5 z-30 rounded-[7px]"
                        style="display: none"
                    ></div>
                </div>
            </div>

            @if ($this->categories->isNotEmpty())
                @include('livewire.partials.schedule-category-footer')
            @endif
        </div>

        <div class="mt-5 flex items-center gap-3">
            <button type="button" @click="$store.prepare.goDone()" class="flex-1 rounded-card border border-line bg-surface py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink">
                Später planen
            </button>
            <button type="button" @click="$store.prepare.goDone()" class="flex-1 rounded-card bg-forest py-2.5 text-sm font-medium text-white transition hover:brightness-95">
                Fertig
            </button>
        </div>
    </div>

    {{-- Done --}}
    <div x-cloak x-show="$store.prepare.phase === 'done'" class="animate-punch-in flex flex-col items-center gap-5 py-20 text-center">
        <div class="grid h-14 w-14 place-items-center rounded-full bg-forest-soft text-forest">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5 8 14.5 16 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1 class="text-lg font-medium text-ink">Bereit für morgen!</h1>

        <div class="flex items-center gap-7">
            <div class="text-center">
                <p class="tnum text-xl font-medium text-ink" x-text="$store.prepare.inboxTotal"></p>
                <p class="text-[11px] text-ink-faint">sortiert</p>
            </div>
            <div class="text-center">
                <p class="tnum text-xl font-medium text-ink" x-text="$store.prepare.flaggedCount"></p>
                <p class="text-[11px] text-ink-faint">für morgen</p>
            </div>
            <div class="text-center">
                <p class="tnum text-xl font-medium text-ink">{{ $this->tomorrowEvents->count() }}</p>
                <p class="text-[11px] text-ink-faint">geplant</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('schedule') }}" wire:navigate class="rounded-card border border-line bg-surface px-4 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink">
                Zum Zeitplan
            </a>
            <a href="{{ route('app') }}" wire:navigate class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-95">
                Zum Board
            </a>
        </div>
    </div>

    @include('livewire.partials.schedule-event-form')
</div>
