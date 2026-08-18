@php
    $wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $wdFull = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $span = $dayEnd - $dayStart;          // total visible minutes
    $ppmWeek = 0.6;                       // px per minute, desktop week (mobile day flexes to the viewport)
    $todayIso = auth()->user()->localToday()->dayOfWeekIso;
    $isEmpty = collect($this->templatesByWeekday)->every(fn ($day) => $day->isEmpty());
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
                    <div>
                        <h1 class="text-xl font-medium text-ink">Wochenplan</h1>
                        <p class="text-sm text-ink-faint">So sieht dein normaler {{ $wdFull[$todayIso - 1] }} aus.</p>
                    </div>
                </div>
                <button wire:click="openEventForm" class="inline-flex items-center gap-1.5 rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Block
                </button>
            </div>

            <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
                {{-- Weekday headers — no dates, this is the template, not a calendar. --}}
                <div class="flex border-b border-line">
                    <div class="w-12 flex-none"></div>
                    @for ($day = 1; $day <= 7; $day++)
                        <button
                            wire:click="openEventForm({{ $day }})"
                            @class([
                                'group flex-1 border-l border-line px-2 py-2.5 text-center transition hover:bg-paper',
                                'bg-forest-soft/40' => $day === $todayIso,
                            ])
                        >
                            <div class="text-[11px] uppercase tracking-wide {{ $day === $todayIso ? 'text-forest' : 'text-ink-faint' }}">{{ $wd[$day - 1] }}</div>
                        </button>
                    @endfor
                </div>

                {{-- Time gutter + 7 weekday columns --}}
                <div class="relative flex" style="height: {{ $span * $ppmWeek }}px">
                    @if ($isEmpty)
                        <div class="pointer-events-none absolute inset-x-0 top-1/3 z-20 px-6 text-center">
                            <p class="text-sm text-ink-faint">Noch kein Wochenplan.</p>
                            <p class="mt-1 text-xs text-ink-faint">Zeichne einen Block auf einen Tag, oder tippe oben auf „+ Block".</p>
                        </div>
                    @endif
                    <div class="relative w-12 flex-none">
                        @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                            <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) * $ppmWeek }}px">{{ sprintf('%02d', $h) }}</span>
                        @endfor
                    </div>

                    @for ($day = 1; $day <= 7; $day++)
                        <div
                            wire:key="wp-grid-{{ $day }}"
                            class="relative flex-1 border-l border-line"
                            data-grid
                            data-weekday="{{ $day }}"
                            data-span="{{ $span }}"
                            data-day-start="{{ $dayStart }}"
                            x-data="scheduleDraw({ date: '{{ $day }}' })"
                            @pointerdown.self="beginDraw"
                            @pointermove="moveDraw"
                            @pointerup="finishDraw"
                            :class="$store.draw.active ? 'cursor-crosshair' : ''"
                            style="touch-action: none"
                        >
                            @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                                <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) * $ppmWeek }}px"></div>
                            @endfor

                            @foreach ($this->templatesByWeekday[$day] as $template)
                                @include('livewire.partials.week-plan-event', ['template' => $template, 'weekday' => $day])
                            @endforeach

                            {{-- Draw preview block --}}
                            <div
                                x-show="drawing"
                                :style="`top:${previewTop}%; height:${Math.max(previewHeight, 0.5)}%; ${previewColorStyle}`"
                                class="pointer-events-none absolute inset-x-0.5 z-30 rounded-[7px]"
                                style="display:none"
                            ></div>
                        </div>
                    @endfor
                </div>
                @if ($this->categories->isNotEmpty())
                    @include('livewire.partials.schedule-category-footer')
                @endif
            </div>
            <p class="mt-3 text-center text-xs text-ink-faint">Ziehen verschiebt · an den Enden ziehen ändert die Länge · Stift bearbeitet</p>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    {{-- All seven days are already loaded (this page isn't paged by date), so
         switching weekdays here is purely client-side — no round trip. --}}
    <div class="md:hidden" x-data="{ focused: {{ $todayIso }} }">
        {{-- A fixed (not min-) height, exactly like Schedule's own mobile day view —
             percentage-based block positioning inside needs a definite height to
             resolve against at every level, which min-height's auto-sizing breaks. --}}
        <div class="flex h-[calc(100dvh-4rem)] flex-col px-4 pb-4 pt-3">
            <div class="mb-1 flex flex-none items-center gap-2">
                <a href="{{ url('/app') }}" wire:navigate class="grid h-9 w-9 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="flex min-w-0 flex-1 items-center justify-between rounded-card border border-line bg-surface px-1.5 py-1.5">
                    <button @click="focused = focused === 1 ? 7 : focused - 1" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Vorheriger Wochentag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button @click="focused = {{ $todayIso }}" class="text-center leading-tight">
                        <div class="text-sm font-medium text-ink" x-text="@js($wdFull)[focused - 1]"></div>
                        <div class="tnum text-[11px] text-ink-faint" x-show="focused === {{ $todayIso }}" style="display:none">heute</div>
                    </button>
                    <button @click="focused = focused === 7 ? 1 : focused + 1" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Nächster Wochentag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <button @click="$wire.openEventForm(focused)" class="grid h-[46px] w-[46px] flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Block hinzufügen">
                    <svg class="h-5 w-5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
            <p class="mb-3 flex-none text-center text-xs text-ink-faint">So sieht dein normaler <span x-text="@js($wdFull)[focused - 1]"></span> aus.</p>

            <div class="min-h-0 flex-1 rounded-card border border-line bg-surface p-2">
                <div class="flex h-full">
                {{-- Time gutter — same hour marks as the desktop week view. --}}
                <div class="relative w-8 flex-none" aria-hidden="true">
                    @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                        <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%">{{ sprintf('%02d', $h) }}</span>
                    @endfor
                </div>

                <div class="relative flex-1">
                    @for ($day = 1; $day <= 7; $day++)
                        <div x-show="focused === {{ $day }}" style="display:none" class="absolute inset-0">
                            <div
                                wire:key="wp-grid-m-{{ $day }}"
                                class="relative h-full border-l border-line/60"
                                data-grid
                                data-weekday="{{ $day }}"
                                data-span="{{ $span }}"
                                data-day-start="{{ $dayStart }}"
                                x-data="scheduleDraw({ date: '{{ $day }}' })"
                                @pointerdown.self="beginDraw"
                                @pointermove="moveDraw"
                                @pointerup="finishDraw"
                                :class="$store.draw.active ? 'cursor-crosshair' : ''"
                                style="touch-action: none"
                            >
                                @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                                    <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) / $span * 100 }}%"></div>
                                @endfor

                                @forelse ($this->templatesByWeekday[$day] as $template)
                                    @include('livewire.partials.week-plan-event', ['template' => $template, 'weekday' => $day])
                                @empty
                                    <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 text-center">
                                        <p class="text-sm text-ink-faint">Noch nichts für diesen Tag.</p>
                                        <p class="mt-1 text-xs text-ink-faint">Tippe oben auf + oder zeichne einen Block.</p>
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
                    @endfor
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

    {{-- ════════════════ PAUSES — rendered once, same content at both
         breakpoints, unlike the grid above which genuinely differs. ════════════════ --}}
    <div class="mx-auto max-w-[1400px] px-4 pb-6 pt-3 md:px-6 md:pb-6 md:pt-0">
        @include('livewire.partials.week-plan-pauses')
    </div>

    {{-- ════════════════ BLOCK FORM (create / edit) ════════════════ --}}
    @include('livewire.partials.week-plan-event-form')
</div>
