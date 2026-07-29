@if ($this->emergencyProject)
    @php
        $project = $this->emergencyProject;
        $progress = $this->emergencyProgress;
        $allDone = $progress['next'] === null && $progress['total'] > 0;
    @endphp
    <div class="{{ $spacing }} flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-map {{ $allDone ? 'border-t-[3px] border-t-forest' : 'border-t-[3px] border-t-signal' }}">
        <div class="flex min-w-0 items-center gap-3">
            <div @class([
                'grid h-9 w-9 flex-none place-items-center rounded-full',
                'bg-forest-soft text-forest' => $allDone,
                'bg-signal-soft text-signal' => !$allDone,
            ])>
                @if ($allDone)
                    <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.5 3.5 7-8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @else
                    <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="10" cy="14" r="1" fill="currentColor"/>
                    </svg>
                @endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-ink">{{ $allDone ? 'Geschafft: ' : 'Notfallmodus: ' }}{{ $project->name }}</p>
                <p class="truncate text-xs text-ink-soft">
                    @if ($allDone)
                        Alle Aufgaben erledigt. Notfallmodus beenden?
                    @else
                        @if ($project->deadline)<span class="tnum">{{ $project->deadlineLabel() }}</span> · @endif
                        <span class="tnum">{{ $progress['done'] }} von {{ $progress['total'] }}</span> erledigt
                        @if ($progress['next']) · Weiter: {{ $progress['next']->title }} @endif
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-none items-center gap-2">
            @unless ($allDone)
                <a href="{{ route('emergency') }}" wire:navigate class="rounded-card border border-line bg-surface px-3 py-2 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">
                    Verwalten
                </a>
            @endunless
            <button
                type="button"
                wire:click="endEmergencyMode"
                @class([
                    'rounded-card px-3 py-2 text-sm transition',
                    'bg-forest font-medium text-white hover:brightness-110 active:scale-[0.98]' => $allDone,
                    'text-ink-faint hover:bg-paper hover:text-ink' => !$allDone,
                ])
            >
                Beenden
            </button>
        </div>
    </div>
@endif
