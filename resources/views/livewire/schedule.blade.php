@php
    use Illuminate\Support\Carbon;

    $span = $dayEnd - $dayStart;          // total visible minutes
    $ppmWeek = 0.6;                       // px per minute, desktop week (mobile day flexes to the viewport)
    $wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $now = auth()->user()->localNow();
    $today = $now->copy()->startOfDay();
    $nowMin = $now->hour * 60 + $now->minute;
    $weekStartC = Carbon::parse($weekStart);
    $focused = Carbon::parse($focusedDate);

    $relDay = match (true) {
        $focused->isSameDay($today) => 'Heute',
        $focused->isSameDay($today->copy()->addDay()) => 'Morgen',
        $focused->isSameDay($today->copy()->subDay()) => 'Gestern',
        default => $wd[$focused->dayOfWeekIso - 1],
    };
@endphp

<div>
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            <div class="mb-5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <a href="{{ url('/app') }}" wire:navigate class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <h1 class="text-xl font-medium text-ink">Zeitplan</h1>
                    <span class="text-sm text-ink-faint">{{ $weekStartC->isoFormat('D.') }}–{{ $weekStartC->copy()->endOfWeek()->isoFormat('D. MMM') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="prevWeek" class="grid h-9 w-9 place-items-center rounded-card border border-line bg-surface text-ink-soft transition hover:text-ink active:scale-95" aria-label="Vorige Woche">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button wire:click="goToday" class="rounded-card border border-line bg-surface px-3 py-2 text-sm text-ink transition hover:border-ink-faint/60 active:scale-95">Heute</button>
                    <button wire:click="nextWeek" class="grid h-9 w-9 place-items-center rounded-card border border-line bg-surface text-ink-soft transition hover:text-ink active:scale-95" aria-label="Nächste Woche">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <button wire:click="openEventForm('{{ $today->toDateString() }}')" class="inline-flex items-center gap-1.5 rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Termin
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
                {{-- Day headers --}}
                <div class="flex border-b border-line">
                    <div class="w-12 flex-none"></div>
                    @foreach ($this->weekDays as $day)
                        <button
                            wire:click="openEventForm('{{ $day->toDateString() }}')"
                            @class([
                                'group flex-1 border-l border-line px-2 py-2.5 text-center transition hover:bg-paper',
                                'bg-forest-soft/40' => $day->isSameDay($today),
                            ])
                        >
                            <div class="text-[11px] uppercase tracking-wide text-ink-faint">{{ $wd[$day->dayOfWeekIso - 1] }}</div>
                            <div class="tnum text-sm font-medium {{ $day->isSameDay($today) ? 'text-forest' : 'text-ink' }}">{{ $day->day }}</div>
                        </button>
                    @endforeach
                </div>

                {{-- Time gutter + 7 day columns --}}
                <div class="flex" style="height: {{ $span * $ppmWeek }}px">
                    <div class="relative w-12 flex-none">
                        @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                            <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) * $ppmWeek }}px">{{ sprintf('%02d', $h) }}</span>
                        @endfor
                    </div>

                    @foreach ($this->weekDays as $day)
                        @php $dayEvents = $this->events->get($day->toDateString(), collect()); @endphp
                        <div
                            class="relative flex-1 border-l border-line"
                            data-grid
                            data-span="{{ $span }}"
                            data-day-start="{{ $dayStart }}"
                            x-data="scheduleDraw({ date: '{{ $day->toDateString() }}' })"
                            @pointerdown.self="beginDraw"
                            @pointermove="moveDraw"
                            @pointerup="finishDraw"
                            :class="$store.draw.cat ? 'cursor-crosshair' : ''"
                            style="touch-action: none"
                        >
                            @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                                <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) * $ppmWeek }}px"></div>
                            @endfor

                            @if ($day->isSameDay($today) && $nowMin >= $dayStart && $nowMin <= $dayEnd)
                                <div class="pointer-events-none absolute inset-x-0 z-[15] border-t-2 border-signal" style="top: {{ ($nowMin - $dayStart) * $ppmWeek }}px">
                                    <span class="absolute -left-1 -top-1 h-2 w-2 rounded-full bg-signal"></span>
                                </div>
                            @endif

                            @foreach ($dayEvents as $event)
                                @include('livewire.partials.schedule-event', ['event' => $event, 'compact' => true])
                            @endforeach

                            {{-- Draw preview block --}}
                            <div
                                x-show="drawing"
                                :style="`top:${previewTop}%; height:${Math.max(previewHeight, 0.5)}%; ${previewColorStyle}`"
                                class="pointer-events-none absolute inset-x-0.5 z-30 rounded-[7px]"
                                style="display:none"
                            ></div>
                        </div>
                    @endforeach
                </div>
                @if ($this->categories->isNotEmpty())
                    @include('livewire.partials.schedule-category-footer')
                @endif
            </div>
            <p class="mt-3 text-center text-xs text-ink-faint">Ziehen verschiebt · an den Enden ziehen ändert die Länge · Stift bearbeitet</p>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    {{-- The whole day fits the viewport: the timeline flexes to the remaining
         height and everything inside is positioned in % of the day's span. --}}
    <div class="md:hidden">
        <div class="flex h-[calc(100dvh-4rem)] flex-col px-4 pb-4 pt-3">
            <div class="mb-3 flex flex-none items-center gap-2">
                <a href="{{ url('/app') }}" wire:navigate class="grid h-9 w-9 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="flex min-w-0 flex-1 items-center justify-between rounded-card border border-line bg-surface px-1.5 py-1.5">
                    <button wire:click="prevDay" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Vorheriger Tag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button wire:click="goToday" class="text-center leading-tight">
                        <div class="text-sm font-medium {{ $focused->isSameDay($today) ? 'text-forest' : 'text-ink' }}">{{ $relDay }}</div>
                        <div class="tnum text-[11px] text-ink-faint">{{ $wd[$focused->dayOfWeekIso - 1] }} · {{ $focused->isoFormat('D.M.') }}</div>
                    </button>
                    <button wire:click="nextDay" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Nächster Tag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <button wire:click="openEventForm('{{ $focusedDate }}')" class="grid h-[46px] w-[46px] flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Termin hinzufügen">
                    <svg class="h-5 w-5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>

            @if ($this->templates->isNotEmpty())
                <div class="mb-3 flex flex-none gap-2 overflow-x-auto">
                    <span class="flex-none self-center text-[11px] text-ink-faint">Vorlagen:</span>
                    @foreach ($this->templates as $t)
                        <button wire:click="applyTemplate({{ $t->id }}, '{{ $focusedDate }}')" class="flex-none whitespace-nowrap rounded-card border border-line bg-surface px-2.5 py-1 text-xs text-ink-soft transition active:scale-95 hover:text-ink">
                            + {{ $t->displayName() }}
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="min-h-0 flex-1 rounded-card border border-line bg-surface p-2">
                <div class="flex h-full">
                {{-- Time gutter — same hour marks as the desktop week view. --}}
                <div class="relative w-8 flex-none" aria-hidden="true">
                    @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                        <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%">{{ sprintf('%02d', $h) }}</span>
                    @endfor
                </div>
                <div
                    class="relative flex-1 border-l border-line/60"
                    data-grid
                    data-span="{{ $span }}"
                    data-day-start="{{ $dayStart }}"
                    x-data="scheduleDraw({ date: '{{ $focusedDate }}' })"
                    @pointerdown.self="beginDraw"
                    @pointermove="moveDraw"
                    @pointerup="finishDraw"
                    :class="$store.draw.cat ? 'cursor-crosshair' : ''"
                    style="touch-action: none"
                >
                    @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                        <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%"></div>
                    @endfor

                    @if ($focused->isSameDay($today) && $nowMin >= $dayStart && $nowMin <= $dayEnd)
                        <div class="pointer-events-none absolute inset-x-0 z-[15] border-t-2 border-signal" style="top: {{ ($nowMin - $dayStart) / $span * 100 }}%">
                            <span class="absolute -left-1 -top-1 h-2 w-2 rounded-full bg-signal"></span>
                        </div>
                    @endif

                    @forelse ($this->focusedEvents as $event)
                        @include('livewire.partials.schedule-event', ['event' => $event, 'compact' => false])
                    @empty
                        <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 text-center">
                            <p class="text-sm text-ink-faint">Kein Termin an diesem Tag.</p>
                            <p class="mt-1 text-xs text-ink-faint">Tippe oben auf + oder eine Vorlage.</p>
                        </div>
                    @endforelse

                    {{-- Draw preview block --}}
                    <div
                        x-show="drawing"
                        :style="`top:${previewTop}%; height:${Math.max(previewHeight, 0.5)}%; ${previewColorStyle}`"
                        class="pointer-events-none absolute inset-x-0.5 z-30 rounded-[7px]"
                        style="display:none"
                    ></div>
                </div>
                </div>
            </div>

            @if ($this->categories->isNotEmpty())
                <div class="mt-2 flex-none rounded-card border border-line bg-surface">
                    @include('livewire.partials.schedule-category-footer')
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════ EVENT FORM (create / edit) ════════════════ --}}
    @include('livewire.partials.schedule-event-form')
</div>
