@php
    $dayStart = 6 * 60;
    $dayEnd = 23 * 60;
    $span = $dayEnd - $dayStart;
    $now = auth()->user()->localNow();
    $nowMin = $now->hour * 60 + $now->minute;
    $events = $this->scheduleToday;
    $focus = $this->focusSession;
    $phase = $this->focusPhase;
    $suggestion = $this->taskSuggestion;
    $pct = fn ($min) => max(0, min(100, ($min - $dayStart) / $span * 100));

    $stripColor = fn ($token) => match ($token) {
        'forest' => ['fill' => 'bg-forest', 'out' => 'border-forest'],
        'overprint' => ['fill' => 'bg-overprint', 'out' => 'border-overprint'],
        'signal' => ['fill' => 'bg-signal', 'out' => 'border-signal'],
        'ink-faint', 'ink' => ['fill' => 'bg-ink-faint', 'out' => 'border-ink-faint'],
        default => ['fill' => 'bg-contour', 'out' => 'border-contour'],
    };

    $isBreak = fn (?string $p) => in_array($p, ['short_break', 'long_break'], true);
@endphp

<div>
@if ($focus)
    {{-- Focus card: a plain div (not the clickable-card <a> below) since it hosts
         its own Start/Stop/Continue/Skip buttons — a button can't sit inside an <a>. --}}
    <div class="rounded-card border border-line bg-surface px-4 py-3 shadow-map">
        @if ($phase && $phase['awaiting_next'])
            @php
                $nextIsBreak = $isBreak($phase['next_phase']);
                $nextLabel = match ($phase['next_phase']) {
                    'long_break' => 'Lange Pause bereit',
                    'short_break' => 'Kurze Pause bereit',
                    default => 'Nächste Session bereit',
                };
            @endphp
            <div class="flex items-center gap-4">
                <div class="grid h-16 w-16 flex-none place-items-center rounded-full border-2 border-dashed border-forest/40 text-forest">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-0.5 flex items-center gap-2">
                        <span class="rounded bg-forest-soft px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-forest">Bereit</span>
                    </div>
                    <p class="truncate text-[15px] font-medium text-ink">{{ $nextLabel }}</p>
                    @if ($suggestion)
                        @include('livewire.partials.schedule-strip-suggestion')
                    @endif
                    <a href="{{ route('schedule') }}" wire:navigate class="truncate text-xs text-ink-soft hover:text-ink">Zeitplan öffnen</a>
                </div>
                <div class="flex flex-none flex-col items-stretch gap-1.5">
                    <button
                        type="button"
                        wire:click="continuePhase({{ $focus->id }})"
                        onclick="window.primeFocusAudio && window.primeFocusAudio()"
                        class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                    >
                        {{ $nextIsBreak ? 'Pause starten' : 'Weiter' }}
                    </button>
                    @if ($nextIsBreak)
                        <button
                            type="button"
                            wire:click="skipBreak({{ $focus->id }})"
                            class="rounded-card px-4 py-1 text-xs text-ink-faint transition hover:text-ink"
                        >
                            Überspringen
                        </button>
                    @endif
                </div>
            </div>
        @elseif ($phase)
            <div
                wire:key="focus-ring-{{ $focus->id }}-{{ $phase['phase'] }}-{{ $phase['cycle'] }}"
                wire:poll.5s.visible
                class="flex items-center gap-4"
                x-data="focusTimer({ id: {{ $focus->id }}, remaining: {{ $phase['remaining_seconds'] }}, total: {{ $phase['total_seconds'] }}, circ: 263.9 })"
            >
                <div class="relative h-16 w-16 flex-none">
                    <svg viewBox="0 0 100 100" class="h-16 w-16 -rotate-90" aria-hidden="true">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="rgb(var(--forest-soft))" stroke-width="9" />
                        <circle cx="50" cy="50" r="42" fill="none" stroke="rgb(var(--forest))" stroke-width="9" stroke-linecap="round" stroke-dasharray="263.9" :stroke-dashoffset="offset" />
                    </svg>
                    <div class="absolute inset-0 grid place-items-center">
                        <span class="tnum text-[15px] font-medium text-ink" x-text="mmss"></span>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-0.5 flex items-center gap-2">
                        <span class="rounded bg-forest-soft px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-forest">{{ $phase['phase'] === 'work' ? 'Fokus' : 'Pause' }}</span>
                        <span class="text-[11px] text-forest">läuft</span>
                    </div>
                    <p class="truncate text-[15px] font-medium text-ink">{{ match ($phase['phase']) {
                        'short_break' => 'Kurze Pause',
                        'long_break' => 'Lange Pause',
                        default => $focus->displayTitle(),
                    } }}</p>
                    @if ($suggestion)
                        @include('livewire.partials.schedule-strip-suggestion')
                    @endif
                    <a href="{{ route('schedule') }}" wire:navigate class="truncate text-xs text-ink-soft hover:text-ink">Zeitplan öffnen</a>
                </div>
                <div class="flex flex-none flex-col items-stretch gap-1.5">
                    <button
                        type="button"
                        wire:click="stopFocusTimer({{ $focus->id }})"
                        class="grid h-9 w-9 flex-none place-items-center self-center rounded-card border border-line text-ink-faint transition hover:border-ink-faint/60 hover:text-ink active:scale-95"
                        aria-label="Timer stoppen"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="1.5"/></svg>
                    </button>
                    @if ($isBreak($phase['phase']))
                        <button
                            type="button"
                            wire:click="skipBreak({{ $focus->id }})"
                            class="rounded-card px-1 text-xs text-ink-faint transition hover:text-ink"
                        >
                            Überspringen
                        </button>
                    @endif
                </div>
            </div>
        @else
            <div class="flex items-center gap-4">
                <div class="grid h-16 w-16 flex-none place-items-center rounded-full border-2 border-dashed border-forest/40 text-forest">
                    <svg class="h-5 w-5 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-0.5 flex items-center gap-2">
                        <span class="rounded bg-forest-soft px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-forest">Bereit</span>
                        <span class="tnum text-[11px] text-ink-faint">{{ $focus->start_time }}–{{ $focus->end_time }}</span>
                    </div>
                    <p class="truncate text-[15px] font-medium text-ink">{{ $focus->displayTitle() }}</p>
                    @if ($suggestion)
                        @include('livewire.partials.schedule-strip-suggestion')
                    @endif
                    <a href="{{ route('schedule') }}" wire:navigate class="truncate text-xs text-ink-soft hover:text-ink">Zeitplan öffnen</a>
                </div>
                <button
                    type="button"
                    wire:click="startFocusTimer({{ $focus->id }})"
                    onclick="window.primeFocusAudio && window.primeFocusAudio()"
                    class="flex-none rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                >
                    Start
                </button>
            </div>
        @endif
    </div>
@else
    <a href="{{ route('schedule') }}" wire:navigate class="group block">
        <div class="rounded-card border border-line bg-surface px-4 py-3 shadow-map transition hover:border-ink-faint/50">
            @if ($events->isEmpty())
                <div class="flex items-center justify-between">
                    <span class="text-sm text-ink-soft">Heute keine Termine geplant</span>
                    <span class="inline-flex items-center gap-1 text-xs text-ink-faint">Zeitplan öffnen <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></span>
                </div>
            @else
                <div class="mb-2.5 flex items-center justify-between">
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-medium text-ink">Heute</span>
                        <span class="text-xs text-ink-faint">{{ $now->isoFormat('dd · D. MMM') }}</span>
                    </div>
                    <span class="inline-flex items-center gap-1 text-xs text-ink-faint">Zeitplan öffnen <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></span>
                </div>
                <div class="relative h-7">
                    <div class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded bg-line"></div>
                    @foreach ($events as $e)
                        @php
                            $left = $pct($e->startMinutes());
                            $w = max(0.8, $e->durationMinutes() / $span * 100);
                            $past = $e->isPast($now);
                            $act = $e->isActive($now);
                            $sc = $stripColor($e->colorToken());
                        @endphp
                        <div
                            @class([
                                'absolute top-1/2 h-3 -translate-y-1/2 overflow-hidden rounded-full',
                                $sc['fill'] => $past,
                                'border-[1.5px] bg-surface '.$sc['out'] => ! $past,
                            ])
                            style="left: {{ $left }}%; width: {{ $w }}%; min-width: 7px"
                            title="{{ $e->displayTitle() }} {{ $e->start_time }}–{{ $e->end_time }}"
                        >
                            @if ($act)
                                <div class="absolute inset-y-0 left-0 {{ $sc['fill'] }}" style="width: {{ $e->progress($now) * 100 }}%"></div>
                            @endif
                        </div>
                    @endforeach
                    @if ($nowMin >= $dayStart && $nowMin <= $dayEnd)
                        <div class="absolute bottom-0 top-0 z-10 w-0.5 rounded bg-signal" style="left: {{ $pct($nowMin) }}%"></div>
                    @endif
                </div>
                <div class="relative mt-1.5 h-3">
                    @foreach ([6, 10, 14, 18, 22] as $h)
                        <span class="tnum absolute -translate-x-1/2 text-[10px] text-ink-faint" style="left: {{ $pct($h * 60) }}%">{{ sprintf('%02d', $h) }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </a>
@endif
</div>
