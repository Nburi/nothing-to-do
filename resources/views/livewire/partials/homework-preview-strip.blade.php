{{-- Dashboard card: open homework due within the next few weekdays. Zero footprint when the
     setting is off or nothing matches — TaskBoard::homeworkPreview() already returns an empty
     collection in the "off" case, so a single isNotEmpty() check covers both. Items sit in a
     horizontally scrollable row of compact cards rather than a stacked list, so 2-3 due items
     don't grow the card's height — more just scroll in sideways, no "N weitere" needed.

     $interaction ('drag'|'swipe') picks which gesture wires up a card into today's focus:
     'drag' (desktop) mounts a Sortable drag source on the row; 'swipe' (mobile) gives each
     card its own vertical swipe-up gesture instead — the strip itself scrolls horizontally,
     so a horizontal swipe-to-act here would fight that native scroll on every touch. Card
     markup is identical either way (unlike full task cards, desktop/mobile don't need
     different layouts for something this compact), only the wiring differs per include site. --}}
@php $interaction = $interaction ?? 'drag'; @endphp
@if ($this->homeworkPreview->isNotEmpty())
    <div class="{{ $spacing }} rounded-card border border-line bg-surface px-3 py-2.5 shadow-map">
        <div class="mb-2 flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2">
                <div class="grid h-6 w-6 flex-none place-items-center rounded-full bg-forest-soft text-forest">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H13l3 3v9a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 3 16V7"/>
                        <path d="M13 4v3h3"/>
                    </svg>
                </div>
                <p class="truncate text-[13px] font-medium text-ink">Bald fällige Hausaufgaben</p>
            </div>

            <a href="{{ route('agenda') }}" wire:navigate class="inline-flex flex-none items-center gap-1 text-[11.5px] text-ink-faint transition hover:text-overprint">
                Zur Agenda
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
        </div>

        <div
            class="-mx-3 flex gap-2 overflow-x-auto px-3 pb-0.5"
            @if ($interaction === 'drag') x-data x-init="window.homeworkDragSource($el, $wire)" @endif
        >
            @foreach ($this->homeworkPreview as $entry)
                @php $alreadyToday = in_array($entry->id, $this->promotedHomeworkEntryIds); @endphp
                <div
                    wire:key="homework-preview-{{ $entry->id }}"
                    @unless ($alreadyToday) data-id="{{ $entry->id }}" @endunless
                    @if ($interaction === 'swipe')
                        x-data="homeworkSwipeCard({ id: {{ $entry->id }} })"
                        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
                        :class="{ 'transition-transform duration-200 ease-tactile': !dragging }"
                        :style="'transform: translateY(' + dy + 'px)'"
                    @endif
                    @class([
                        'relative w-[9.5rem] flex-none touch-pan-x rounded-card border border-line bg-paper/60 px-2.5 py-1.5',
                        'cursor-grab active:cursor-grabbing' => $interaction === 'drag' && ! $alreadyToday,
                    ])
                >
                    @if ($interaction === 'swipe')
                        {{-- Swipe-up reveal — mirrors the horizontal reveal panels on task
                             cards, rotated to this gesture's own vertical axis. --}}
                        <div
                            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-card bg-forest text-white"
                            x-show="dy < 0" :style="{ opacity: progress }" style="display: none;"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 15V5m0 0-4 4m4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="text-[11px] font-medium">Heute</span>
                        </div>
                    @endif

                    <div class="mb-1 flex items-center justify-between gap-1.5">
                        <button
                            type="button"
                            wire:click.stop="toggleHomeworkPreviewDone({{ $entry->id }})"
                            class="grid h-[15px] w-[15px] flex-none place-items-center rounded-full border-2 border-line text-transparent transition hover:border-forest hover:text-forest focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                            aria-label="Erledigt markieren: {{ $entry->title }}"
                        >
                            <svg class="h-2 w-2" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        @if ($alreadyToday)
                            <span class="tnum flex-none inline-flex items-center gap-0.5 rounded-card bg-forest-soft px-1.5 py-0.5 text-[10.5px] font-medium text-forest" title="Schon im Tagesfokus">
                                <svg class="h-2 w-2" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Heute
                            </span>
                        @else
                            <span @class([
                                'tnum flex-none rounded-card px-1.5 py-0.5 text-[10.5px] font-medium',
                                'bg-signal-soft text-signal' => $entry->isOverdue(),
                                'bg-contour-soft text-contour' => ! $entry->isOverdue(),
                            ])>{{ $entry->dateLabel() }}</span>
                        @endif
                    </div>

                    <p class="truncate text-[10px] text-ink-faint">{{ $entry->subject }}</p>
                    <p class="truncate text-[12px] font-medium text-ink">{{ $entry->title }}</p>

                    @if ($preview = $entry->notesPreview())
                        <div x-data="{ notesOpen: false }">
                            <button
                                type="button"
                                @click.stop="notesOpen = !notesOpen"
                                class="mt-0.5 block max-w-full truncate rounded text-left text-[10.5px] text-ink-faint transition hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                :aria-expanded="notesOpen.toString()"
                                aria-label="Notiz ein-/ausklappen: {{ $entry->title }}"
                            >
                                <span x-show="!notesOpen">{{ $preview }}</span>
                                <span x-show="notesOpen" style="display: none;">Notiz ausblenden</span>
                            </button>
                            <p
                                x-show="notesOpen"
                                style="display: none;"
                                class="mt-0.5 whitespace-pre-line break-words text-[10.5px] text-ink-soft"
                            >{{ $entry->notes }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
