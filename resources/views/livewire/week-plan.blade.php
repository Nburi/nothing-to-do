@php
    use App\Services\DayWindow;

    $wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $wdFull = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    // One frame for all seven columns (one hour gutter), scale derived from the
    // span so the grid's own height stays roughly constant — see DayWindow.
    $dayStart = $frame['start'];
    $dayEnd = $frame['end'];
    $span = $dayEnd - $dayStart;
    $ppmWeek = DayWindow::ppm($span);
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

            @if ($frame['expanded'])
                <div class="mb-3 flex items-center gap-1.5 text-xs text-ink-faint">
                    <span class="tnum">Die Achse reicht bis {{ \App\Services\DayWindow::label($frame['start'], $frame['end']) }} — ein Block liegt ausserhalb deines Tagesrahmens.</span>
                </div>
            @endif

            <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
                {{-- Weekday headers — no dates, this is the template, not a calendar. --}}
                <div class="flex border-b border-line">
                    <div class="w-12 flex-none"></div>
                    @for ($day = 1; $day <= 7; $day++)
                        @php $weekdaySetting = $this->weekdaySettings[$day]; @endphp
                        {{-- A div, not a button: the Tagesrahmen chip below is its own
                             button and cannot be nested inside one. --}}
                        <div
                            @class([
                                'group flex-1 border-l border-line pb-2 text-center transition hover:bg-paper',
                                'bg-forest-soft/40' => $day === $todayIso,
                            ])
                        >
                            {{-- The button carries the cell's padding, not the cell — see the
                                 same note in schedule.blade.php. --}}
                            <button wire:click="openEventForm({{ $day }})" class="block w-full px-2 pb-1 pt-2.5" aria-label="Block am {{ $wdFull[$day - 1] }} hinzufuegen">
                                <div class="text-[11px] uppercase tracking-wide {{ $day === $todayIso ? 'text-forest' : 'text-ink-faint' }}">{{ $wd[$day - 1] }}</div>
                            </button>
                            <button
                                wire:click="openWeekdayBounds({{ $day }})"
                                @class([
                                    'tnum mt-0.5 inline-flex rounded-full border px-1.5 text-[9px] leading-[14px] transition',
                                    'border-contour/45 bg-contour-soft text-contour' => $weekdaySetting['source'] === 'weekday',
                                    'border-line bg-surface text-ink-faint opacity-0 pointer-events-none group-hover:pointer-events-auto group-hover:opacity-100 focus-visible:opacity-100' => $weekdaySetting['source'] !== 'weekday',
                                ])
                                aria-label="Tagesrahmen fuer {{ $wdFull[$day - 1] }} aendern - zurzeit {{ $this->dayBoundsLabel($weekdaySetting['start'], $weekdaySetting['end']) }}"
                            >{{ $this->dayBoundsLabel($weekdaySetting['start'], $weekdaySetting['end']) }}</button>
                        </div>
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
                        @for ($h = (int) ceil($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                            <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) * $ppmWeek }}px">{{ sprintf('%02d', $h) }}</span>
                        @endfor
                    </div>

                    @for ($day = 1; $day <= 7; $day++)
                        @php $weekdaySetting = $this->weekdaySettings[$day]; @endphp
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
                            {{-- Dimmed outside this weekday's own Tagesrahmen: a Saturday that
                                 starts later says so at a glance, without reading the chip. --}}
                            @if ($weekdaySetting['start'] > $dayStart)
                                <div class="tl-night tl-night-top" style="height: {{ ($weekdaySetting['start'] - $dayStart) * $ppmWeek }}px"></div>
                            @endif
                            @if ($weekdaySetting['end'] < $dayEnd)
                                <div class="tl-night tl-night-bottom" style="height: {{ ($dayEnd - $weekdaySetting['end']) * $ppmWeek }}px"></div>
                            @endif

                            @for ($h = (int) ceil($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
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
            <p class="mt-3 text-center text-xs text-ink-faint">Ziehen verschiebt · an den Enden ziehen ändert die Länge · kurze Blöcke richten sich beim Draufzeigen auf · Doppelklick bearbeitet</p>
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
                    <button @click="focused = focused === 1 ? 7 : focused - 1" class="grid h-10 w-10 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Vorheriger Wochentag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button @click="focused = {{ $todayIso }}" class="min-h-10 min-w-0 flex-1 self-stretch text-center leading-tight">
                        <div class="text-sm font-medium text-ink" x-text="@js($wdFull)[focused - 1]"></div>
                        <div class="tnum text-[11px] text-ink-faint" x-show="focused === {{ $todayIso }}" style="display:none">heute</div>
                    </button>
                    <button @click="focused = focused === 7 ? 1 : focused + 1" class="grid h-10 w-10 place-items-center rounded-card text-ink-soft transition hover:bg-paper active:scale-95" aria-label="Nächster Wochentag">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <button @click="$wire.openEventForm(focused)" class="grid h-[46px] w-[46px] flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Block hinzufügen">
                    <svg class="h-5 w-5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
            <p class="mb-2 flex-none text-center text-xs text-ink-faint">So sieht dein normaler <span x-text="@js($wdFull)[focused - 1]"></span> aus.</p>
            {{-- One button per weekday, only the focused one shown: the chip has to
                 name the frame of the day actually on screen, and paging here is
                 client-side only (no round trip, see the x-data above). --}}
            <div class="mb-2 flex flex-none justify-center">
                @for ($day = 1; $day <= 7; $day++)
                    @php $weekdaySetting = $this->weekdaySettings[$day]; @endphp
                    <button
                        x-show="focused === {{ $day }}"
                        style="display:none"
                        wire:click="openWeekdayBounds({{ $day }})"
                        @class([
                            'tnum rounded-full border px-2.5 py-1 text-[11px] leading-none transition active:scale-95',
                            'border-contour/45 bg-contour-soft text-contour' => $weekdaySetting['source'] === 'weekday',
                            'border-line bg-surface text-ink-faint' => $weekdaySetting['source'] !== 'weekday',
                        ])
                        aria-label="Tagesrahmen fuer {{ $wdFull[$day - 1] }} aendern"
                    >{{ $this->dayBoundsLabel($weekdaySetting['start'], $weekdaySetting['end']) }}</button>
                @endfor
            </div>

            {{-- One gutter per weekday, not one shared with all seven: only one is
                 ever on screen here, so each can — and must — carry its own
                 Tagesrahmen and therefore its own scale. The desktop grid above
                 cannot do this; seven columns share one gutter there. --}}
            <div class="min-h-0 flex-1 rounded-card border border-line bg-surface p-2">
                <div class="relative h-full">
                    @for ($day = 1; $day <= 7; $day++)
                        @php
                            $mFrame = $this->weekdayFrames[$day];
                            $mStart = $mFrame['start'];
                            $mEnd = $mFrame['end'];
                            $mSpan = $mEnd - $mStart;
                        @endphp
                        <div x-show="focused === {{ $day }}" style="display:none" class="absolute inset-0 flex">
                            <div class="relative w-8 flex-none" aria-hidden="true">
                                @for ($h = (int) ceil($mStart / 60); $h <= intval($mEnd / 60); $h++)
                                    <span class="tnum absolute right-2 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $mStart) / $mSpan * 100 }}%">{{ sprintf('%02d', $h) }}</span>
                                @endfor
                            </div>
                            <div
                                wire:key="wp-grid-m-{{ $day }}"
                                class="relative flex-1 border-l border-line/60"
                                data-grid
                                data-weekday="{{ $day }}"
                                data-span="{{ $mSpan }}"
                                data-day-start="{{ $mStart }}"
                                x-data="scheduleDraw({ date: '{{ $day }}' })"
                                @pointerdown.self="beginDraw"
                                @pointermove="moveDraw"
                                @pointerup="finishDraw"
                                :class="$store.draw.active ? 'cursor-crosshair' : ''"
                                style="touch-action: none"
                            >
                                @if ($mFrame['settingStart'] > $mStart)
                                    <div class="tl-night tl-night-top" style="height: {{ ($mFrame['settingStart'] - $mStart) / $mSpan * 100 }}%"></div>
                                @endif
                                @if ($mFrame['settingEnd'] < $mEnd)
                                    <div class="tl-night tl-night-bottom" style="height: {{ ($mEnd - $mFrame['settingEnd']) / $mSpan * 100 }}%"></div>
                                @endif

                                @for ($h = (int) ceil($mStart / 60); $h <= intval($mEnd / 60); $h++)
                                    <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $mStart) / $mSpan * 100 }}%"></div>
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

            <p class="mt-1 flex-none text-center text-[10px] leading-tight text-ink-faint">
                Kurze Blöcke antippen, um sie aufzurichten · nochmal tippen bearbeitet
            </p>

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

    {{-- ════════════════ TAGESRAHMEN ════════════════ --}}
    @include('livewire.partials.day-bounds-popover')
</div>
