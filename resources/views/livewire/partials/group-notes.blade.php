{{-- The group's Notizen column: a stack of separate note cards (not one
     document) — a group can hold a few unrelated things worth jotting down,
     and one blob made finding any single one of them slower as it grew. --}}
<div class="flex flex-col gap-2.5">
    @forelse ($this->notes as $note)
        @include('livewire.partials.group-note-card', ['note' => $note])
    @empty
        <button type="button" wire:click="addNote" class="group/empty flex w-full flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line bg-paper/40 px-4 py-10 text-center transition hover:border-ink-faint/60 hover:bg-paper/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
            <svg class="h-8 w-8 text-line transition group-hover/empty:text-ink-faint" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M30 9.5 38.5 18 18 38.5l-9 1.5 1.5-9L30 9.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="m26.5 13 8.5 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span class="text-sm font-medium text-ink">Notiz beginnen</span>
            <span class="max-w-[30ch] text-xs leading-relaxed text-ink-faint">Was zu dieser Gruppe gehört, aber keine Aufgabe ist. Markdown wird unterstützt.</span>
        </button>
    @endforelse

    @if ($this->notes->isNotEmpty())
        <button
            type="button"
            wire:click="addNote"
            class="flex items-center justify-center gap-1.5 rounded-card border border-dashed border-line px-3 py-2.5 text-xs font-medium text-ink-faint transition hover:border-ink-faint/60 hover:bg-paper/60 hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
            Notiz
        </button>
    @endif
</div>
