<div class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 pb-28 pt-5 sm:px-6 md:pb-12">
    <div class="w-full rounded-card border border-line bg-surface p-6 text-center shadow-map">
        @if ($space === null)
            <div class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-full bg-signal-soft text-signal">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/>
                </svg>
            </div>
            <h1 class="text-lg font-medium text-ink">Diese Einladung gilt nicht mehr</h1>
            <p class="mx-auto mt-2 max-w-xs text-[13.5px] leading-relaxed text-ink-faint">
                Der Code <span class="tnum font-medium text-ink-soft">{{ $code }}</span> gehört zu keiner Klasse.
                Vielleicht wurde er neu vergeben — frag jemanden aus der Klasse nach dem aktuellen Link.
            </p>
            <a href="{{ route('agenda') }}" wire:navigate class="mt-5 inline-block rounded-card border border-line px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                Zur Agenda
            </a>
        @elseif ($alreadyMember)
            <div class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-full bg-forest-soft text-forest">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 12.5 4.5 4.5L19 7.5"/>
                </svg>
            </div>
            <h1 class="text-lg font-medium text-ink">Du bist schon dabei</h1>
            <p class="mt-2 text-[13.5px] text-ink-faint">
                „{{ $space->name }}" steht bereits in deiner Agenda.
            </p>
            <a href="{{ route('agenda') }}" wire:navigate class="mt-5 inline-block rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                Zur Agenda
            </a>
        @else
            <div class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-full bg-contour-soft text-contour">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19"/><circle cx="10" cy="8" r="3.2"/><path d="M19 11h-4M17 9v4"/>
                </svg>
            </div>
            <h1 class="text-lg font-medium text-ink">{{ $space->name }}</h1>
            <p class="mt-2 text-[13.5px] text-ink-faint">
                {{ $memberCount }} {{ $memberCount === 1 ? 'Mitglied' : 'Mitglieder' }}
            </p>
            <p class="mx-auto mt-3 max-w-xs text-[13px] leading-relaxed text-ink-faint">
                Wenn du beitrittst, siehst du alle Hausaufgaben und Prüfungen dieser Klasse — und kannst selbst
                welche für alle eintragen. Abhaken tust du nur für dich.
            </p>
            <button
                type="button"
                wire:click="join"
                wire:loading.attr="disabled"
                class="mt-5 w-full rounded-card bg-forest py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="join">Beitreten</span>
                <span wire:loading wire:target="join" style="display: none;">Tritt bei…</span>
            </button>
            <a href="{{ route('agenda') }}" wire:navigate class="mt-2.5 inline-block text-[13px] text-ink-faint transition hover:text-ink-soft">
                Nein, zurück zur Agenda
            </a>
        @endif
    </div>
</div>
