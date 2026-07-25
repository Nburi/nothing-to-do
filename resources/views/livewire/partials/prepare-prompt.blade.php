@if ($this->showPreparePrompt)
    <div class="{{ $spacing }} flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-map">
        <div class="flex min-w-0 items-center gap-3">
            <div class="grid h-9 w-9 flex-none place-items-center rounded-full bg-forest-soft text-forest">
                <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h9m-9 4h12m-12 4h6"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-ink">Zeit für deine Vorbereitung?</p>
                <p class="truncate text-xs text-ink-soft">
                    {{ auth()->user()->prepare_time_of_day === 'morning' ? 'Plane deinen Tag, bevor er losgeht.' : 'Bereite dich auf morgen vor.' }}
                </p>
            </div>
        </div>
        <div class="flex flex-none items-center gap-2">
            <a href="{{ route('prepare') }}" wire:navigate class="rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                Starten
            </a>
            <button
                type="button"
                wire:click="dismissPreparePrompt"
                class="grid h-9 w-9 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                aria-label="Für heute ausblenden"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
@endif
