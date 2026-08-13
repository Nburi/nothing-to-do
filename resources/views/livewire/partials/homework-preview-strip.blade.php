{{-- Dashboard card: open homework due within the next few weekdays. Zero footprint when the
     setting is off or nothing matches — TaskBoard::homeworkPreview() already returns an empty
     collection in the "off" case, so a single isNotEmpty() check covers both. --}}
@if ($this->homeworkPreview->isNotEmpty())
    @php $visibleCount = 3; @endphp
    <div x-data="{ showAll: false }" class="{{ $spacing }} rounded-card border border-line bg-surface p-4 shadow-map">
        <div class="mb-3 flex items-center gap-2.5">
            <div class="grid h-7 w-7 flex-none place-items-center rounded-full bg-forest-soft text-forest">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H13l3 3v9a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 3 16V7"/>
                    <path d="M13 4v3h3"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-ink">Bald fällige Hausaufgaben</p>
        </div>

        <div class="space-y-1.5">
            @foreach ($this->homeworkPreview as $i => $entry)
                <div
                    wire:key="homework-preview-{{ $entry->id }}"
                    @if ($i >= $visibleCount) x-show="showAll" style="display: none;" @endif
                    class="flex items-center gap-3 rounded-card bg-paper/60 px-3 py-2"
                >
                    <button
                        type="button"
                        wire:click="toggleHomeworkPreviewDone({{ $entry->id }})"
                        class="grid h-[16px] w-[16px] flex-none place-items-center rounded-full border-2 border-line text-transparent transition hover:border-forest hover:text-forest focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                        aria-label="Erledigt markieren: {{ $entry->title }}"
                    >
                        <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                            <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] text-ink-faint">{{ $entry->subject }}</p>
                        <p class="truncate text-[13.5px] font-medium text-ink">{{ $entry->title }}</p>
                    </div>

                    <span @class([
                        'tnum flex-none rounded-card px-2 py-1 text-[12px] font-medium',
                        'bg-signal-soft text-signal' => $entry->isOverdue(),
                        'bg-contour-soft text-contour' => ! $entry->isOverdue(),
                    ])>{{ $entry->dateLabel() }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-2 flex items-center justify-between gap-3">
            @if ($this->homeworkPreview->count() > $visibleCount)
                <button
                    type="button"
                    x-show="!showAll"
                    @click="showAll = true"
                    class="px-1 py-1 text-left text-xs text-ink-faint transition hover:text-ink-soft"
                >{{ $this->homeworkPreview->count() - $visibleCount }} weitere · Alle anzeigen</button>
            @else
                <span></span>
            @endif

            <a href="{{ route('agenda') }}" wire:navigate class="inline-flex flex-none items-center gap-1 px-1 py-1 text-xs text-ink-faint transition hover:text-overprint">
                Zur Agenda
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
        </div>
    </div>
@endif
