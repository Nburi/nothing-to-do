{{-- Today's time line around now: pomodoro blocks as work, other blocks as fixed, a now marker
     and "free until". @param $timeline (Board::timeline()), $compact (no axis) --}}
@php
    $span = max(1, $timeline['to'] - $timeline['from']);
    $pos = fn (float $h) => round(max(0, min(100, ($h - $timeline['from']) / $span * 100)), 2);
@endphp
<figure class="flex min-w-0 flex-col gap-1.5" aria-label="{{ __('shell.timeline') }}">
    <div class="relative h-7 overflow-hidden rounded-card" style="background: color-mix(in srgb, var(--t-ink) 9%, var(--t-surface))">
        @foreach ($timeline['segments'] as $seg)
            @php($w = $pos($seg['end']) - $pos($seg['start']))
            @if ($w > 0)
                <span
                    @class([
                        'absolute inset-y-0 flex items-center justify-center overflow-hidden px-1 font-mono text-[11px] whitespace-nowrap',
                        'bg-accent text-accent-fg' => $seg['kind'] === 'work',
                        'bg-deep text-on-deep' => $seg['kind'] === 'fixed',
                    ])
                    style="left: {{ $pos($seg['start']) }}%; width: {{ $w }}%"
                    title="{{ $seg['time'] }} {{ $seg['label'] }}"
                >@if ($w > 14){{ $seg['label'] }}@endif</span>
            @endif
        @endforeach
        <span class="absolute inset-y-0 w-0.5 bg-warn" style="left: {{ $pos($timeline['now']) }}%" aria-hidden="true"></span>
    </div>
    @unless ($compact ?? false)
        <div class="relative h-3.5 font-mono text-[10px] text-ink-faint" aria-hidden="true">
            @foreach ($timeline['ticks'] as $i => $tick)
                <span class="absolute top-0 {{ $loop->first ? '' : ($loop->last ? '-translate-x-full' : '-translate-x-1/2') }}" style="left: {{ $pos($tick['hour']) }}%">{{ $tick['label'] }}</span>
            @endforeach
        </div>
    @endunless
    <figcaption class="text-sm text-ink-soft">{{ $timeline['free'] }}</figcaption>
</figure>
