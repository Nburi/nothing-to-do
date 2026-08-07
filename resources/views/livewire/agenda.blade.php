<div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-6 md:pb-12">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Agenda</h1>
            <p class="mt-1 text-[13px] text-ink-faint">Hausaufgaben &amp; Prüfungen — unabhängig von deinen Listen</p>
        </div>
        <button
            type="button"
            wire:click="openCreateForm"
            class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
        >
            + Eintrag
        </button>
    </div>

    <div class="mt-5 inline-flex gap-1 rounded-card border border-line bg-surface p-1">
        @foreach (['all' => 'Alle', 'homework' => 'Hausaufgaben', 'exam' => 'Prüfungen'] as $val => $lbl)
            <button
                type="button"
                wire:click="setFilter('{{ $val }}')"
                @class([
                    'rounded-[0.4rem] px-3 py-1.5 text-sm transition',
                    'bg-forest text-white' => $filterType === $val,
                    'text-ink-soft hover:text-ink' => $filterType !== $val,
                ])
            >{{ $lbl }}</button>
        @endforeach
    </div>

    <div class="mt-4 space-y-2">
        @forelse ($this->openEntries as $entry)
            @include('livewire.partials.agenda-entry', ['entry' => $entry])
        @empty
            <div class="rounded-card border border-dashed border-line p-10 text-center">
                <svg class="mx-auto mb-3 h-8 w-8 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
                </svg>
                <p class="text-sm text-ink-faint">Keine Einträge — leg deine erste Hausaufgabe oder Prüfung an.</p>
            </div>
        @endforelse
    </div>

    @if ($this->doneEntries->isNotEmpty())
        <div x-data="{ show: false }" class="mt-4">
            <button type="button" @click="show = !show" class="px-0.5 text-[12.5px] text-ink-faint transition hover:text-ink-soft">
                <span x-show="!show">{{ $this->doneEntries->count() }} erledigt · anzeigen</span>
                <span x-show="show" style="display: none;">{{ $this->doneEntries->count() }} erledigt · ausblenden</span>
            </button>
            <div x-show="show" x-transition class="mt-2 space-y-2" style="display: none;">
                @foreach ($this->doneEntries as $entry)
                    @include('livewire.partials.agenda-entry', ['entry' => $entry])
                @endforeach
            </div>
        </div>
    @endif

    @include('livewire.partials.agenda-entry-form')
</div>
