@php
    $dayStart = 6 * 60;
    $dayEnd = 23 * 60;
    $span = $dayEnd - $dayStart;
    $now = auth()->user()->localNow();
    $nowMin = $now->hour * 60 + $now->minute;
    $events = $this->scheduleToday;
    $pct = fn ($min) => max(0, min(100, ($min - $dayStart) / $span * 100));

    $stripColor = fn ($token) => match ($token) {
        'forest' => ['fill' => 'bg-forest', 'out' => 'border-forest'],
        'overprint' => ['fill' => 'bg-overprint', 'out' => 'border-overprint'],
        'signal' => ['fill' => 'bg-signal', 'out' => 'border-signal'],
        'ink-faint', 'ink' => ['fill' => 'bg-ink-faint', 'out' => 'border-ink-faint'],
        default => ['fill' => 'bg-contour', 'out' => 'border-contour'],
    };
@endphp

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
