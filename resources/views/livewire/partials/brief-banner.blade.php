@php $n = $this->briefNudge; @endphp
<a href="{{ route('brief') }}" wire:navigate class="group block">
    <div class="flex items-center gap-3 rounded-card border border-overprint/35 bg-overprint-soft px-4 py-3 transition hover:border-overprint/55">
        <div class="grid h-9 w-9 flex-none place-items-center rounded-card bg-overprint text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink">{{ $n['greeting'] }} — planst du {{ $n['dayName'] }}?</p>
            <p class="text-xs text-ink-soft">
                {{ $n['waiting'] }} {{ $n['waiting'] === 1 ? 'Aufgabe wartet' : 'Aufgaben warten' }}
                @if ($n['dueSoon'] > 0) · {{ $n['dueSoon'] }} mit Termin @endif
            </p>
        </div>
        <span class="hidden flex-none rounded-card bg-overprint px-3.5 py-2 text-sm font-medium text-white transition group-hover:brightness-110 sm:inline-block">Brief starten</span>
        <button
            type="button"
            wire:click.prevent="dismissBrief"
            @click.stop
            class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-overprint/10 hover:text-ink-soft"
            aria-label="Später"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
</a>
