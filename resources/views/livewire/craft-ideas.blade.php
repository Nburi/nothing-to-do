<div class="mx-auto max-w-2xl px-4 pb-16 pt-5 sm:px-6">
    <h1 class="text-xl font-medium tracking-tight text-ink">Bastelideen</h1>
    <p class="mt-1 text-[13px] text-ink-faint">Für wenn dir langweilig ist — geschnappt, nicht sortiert.</p>

    @if ($this->hero)
        {{-- ════════════════ Direktvorschlag ════════════════ --}}
        <div wire:key="hero-{{ $this->heroId }}" class="animate-rise mt-5 rounded-card border border-line bg-surface p-5 shadow-map sm:p-6">
            <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.09em] text-overprint">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 3v3m0 8v3M3 10h3m8 0h3M5.5 5.5l2 2m5 5 2 2m0-9-2 2m-5 5-2 2"/>
                </svg>
                Mach doch das
            </div>
            <h2 class="mt-2 text-[1.35rem] font-bold leading-snug tracking-tight text-ink">{{ $this->hero->title }}</h2>
            @if ($this->hero->where_to_begin)
                <div class="mt-2.5 flex items-start gap-2 text-[13.5px] leading-relaxed text-ink-soft">
                    <svg class="mt-0.5 h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 10h12M10 4l6 6-6 6"/>
                    </svg>
                    <span><span class="font-medium text-ink">Wo anfangen:</span> {{ $this->hero->where_to_begin }}</span>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="markDone({{ $this->hero->id }})"
                    class="inline-flex items-center gap-1.5 rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5 6.5 12 13 4.5"/></svg>
                    Erledigt
                </button>
                @if ($this->otherIdeas->isNotEmpty())
                    <button
                        type="button"
                        wire:click="shuffle"
                        class="inline-flex items-center gap-1.5 rounded-card border border-line bg-paper px-4 py-2 text-sm font-medium text-ink-soft transition hover:border-ink-faint/60 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h3.5L14 15h3M3 15h3.5L14 6h3M14 6l3-2.2M14 6l3 2.2M14 15l3 2.2M14 15l3-2.2"/>
                        </svg>
                        Andere Idee
                    </button>
                @endif
                <button
                    type="button"
                    x-data="{ armed: false, _t: null }"
                    @click="if (armed) { $wire.deleteIdea({{ $this->hero->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                    @click.outside="armed = false; clearTimeout(_t)"
                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="ml-auto grid h-9 w-9 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                    aria-label="Löschen: {{ $this->hero->title }}"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        @if ($this->otherIdeas->isNotEmpty())
            {{-- ════════════════ Pinnwand: die übrigen Ideen ════════════════ --}}
            <p class="mb-3 mt-7 text-[11px] font-medium uppercase tracking-[0.09em] text-ink-faint">Weitere Ideen</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                @php
                    $rotations = [-1.6, 1.1, -0.8, 1.7, -1.2, 0.9, -1.9, 1.3];
                    $spans = ['sm:col-span-3', 'sm:col-span-3', 'sm:col-span-2', 'sm:col-span-4', 'sm:col-span-3', 'sm:col-span-3'];
                    $pins = ['bg-overprint', 'bg-contour'];
                @endphp
                @foreach ($this->otherIdeas as $idea)
                    @php
                        $rot = $rotations[$loop->index % count($rotations)];
                        $span = $spans[$loop->index % count($spans)];
                        $pin = $pins[$loop->index % count($pins)];
                    @endphp
                    <div
                        wire:key="craft-idea-{{ $idea->id }}"
                        class="pin-card group/idea relative {{ $span }} rounded-card border border-line bg-surface shadow-map"
                        style="transform: rotate({{ $rot }}deg)"
                    >
                        <span class="absolute -top-[5px] left-1/2 h-[9px] w-[9px] -translate-x-1/2 rounded-full {{ $pin }} shadow-[0_1px_3px_rgb(35_41_31/0.35)]" aria-hidden="true"></span>

                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click.stop="if (armed) { $wire.deleteIdea({{ $idea->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'opacity-100 bg-signal text-white' : 'opacity-0 text-ink-faint hover:bg-signal-soft hover:text-signal group-hover/idea:opacity-100 group-focus-within/idea:opacity-100'"
                            class="absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-card transition focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-signal"
                            aria-label="Löschen: {{ $idea->title }}"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <button
                            type="button"
                            wire:click="promote({{ $idea->id }})"
                            class="block w-full px-3.5 pb-3.5 pt-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint focus-visible:ring-inset"
                        >
                            <h3 class="pr-5 text-[13.5px] font-medium leading-snug text-ink">{{ $idea->title }}</h3>
                            @if ($idea->where_to_begin)
                                <p class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-ink-faint">{{ $idea->where_to_begin }}</p>
                            @endif
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- ════════════════ Leer: noch keine Ideen ════════════════ --}}
        <div class="mt-8 flex flex-col items-center gap-3 rounded-card border border-dashed border-line px-6 py-14 text-center">
            <svg class="h-9 w-9 text-line" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 3a7 7 0 1 0 0 14h1a1.5 1.5 0 0 0 1.06-2.56 1.5 1.5 0 0 1 1.06-2.56H14a3 3 0 0 0 3-3 7 7 0 0 0-7-6Z"/>
                <circle cx="7" cy="9" r=".8" fill="currentColor" stroke="none"/>
                <circle cx="10" cy="7" r=".8" fill="currentColor" stroke="none"/>
                <circle cx="13" cy="9" r=".8" fill="currentColor" stroke="none"/>
            </svg>
            <p class="max-w-[30ch] text-sm leading-relaxed text-ink-faint">
                Noch keine Bastelideen. Wirf eine rein, sobald dir eine einfällt — die Schnellerfassung findest du auf dem Dashboard.
            </p>
        </div>
    @endif

    @if ($this->doneIdeas->isNotEmpty())
        <div x-data="{ show: false }" class="mt-8 border-t border-line pt-4">
            <button type="button" @click="show = !show" class="px-0.5 text-[12.5px] text-ink-faint transition hover:text-ink-soft">
                <span x-show="!show">{{ $this->doneIdeas->count() }} erledigt · anzeigen</span>
                <span x-show="show" style="display: none;">{{ $this->doneIdeas->count() }} erledigt · ausblenden</span>
            </button>
            <div x-show="show" x-transition class="mt-3 flex flex-wrap gap-2" style="display: none;">
                @foreach ($this->doneIdeas as $idea)
                    <div wire:key="craft-idea-done-{{ $idea->id }}" class="inline-flex items-center gap-2 rounded-full border border-line bg-surface py-1 pl-1 pr-3">
                        <button
                            type="button"
                            wire:click="restoreIdea({{ $idea->id }})"
                            class="grid h-[22px] w-[22px] flex-none place-items-center rounded-full border-2 border-forest bg-forest text-white transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest"
                            aria-label="Wiederherstellen: {{ $idea->title }}"
                        >
                            <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <span class="text-[13px] text-ink-faint line-through decoration-ink-faint/50">{{ $idea->title }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
