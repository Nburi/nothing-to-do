@php
    $startMin = $event->startMinutes();
    $endMin = $event->endMinutes();
    $token = $event->colorToken();
    $compact = $compact ?? false;
    $isBreak = $event->isBreak();
    $isSession = $event->isWorkSession();

    // Topografie colour tokens → literal classes (kept literal for the JIT scanner).
    $styles = match ($token) {
        'forest'    => ['bg' => 'bg-forest-soft',    'bd' => 'border-forest/40',    'tx' => 'text-forest',    'bar' => 'bg-forest'],
        'overprint' => ['bg' => 'bg-overprint-soft', 'bd' => 'border-overprint/40', 'tx' => 'text-overprint', 'bar' => 'bg-overprint'],
        'signal'    => ['bg' => 'bg-signal-soft',    'bd' => 'border-signal/40',    'tx' => 'text-signal',    'bar' => 'bg-signal'],
        'ink-faint' => ['bg' => 'bg-surface',        'bd' => 'border-line',         'tx' => 'text-ink-faint', 'bar' => 'bg-ink-faint/50'],
        'ink'       => ['bg' => 'bg-surface',        'bd' => 'border-ink-faint/40', 'tx' => 'text-ink',       'bar' => 'bg-ink-faint'],
        default     => ['bg' => 'bg-contour-soft',   'bd' => 'border-contour/40',   'tx' => 'text-contour',   'bar' => 'bg-contour'],
    };
@endphp
<div
    wire:key="ev-{{ $event->id }}"
    x-data="scheduleEvent({ id: {{ $event->id }}, start: {{ $startMin }}, end: {{ $endMin }} })"
    x-bind:style="`top:${top}px; height:${height}px`"
    @pointerdown="begin('move', $event)"
    @pointermove="drag($event)"
    @pointerup="finish()"
    @pointercancel="finish()"
    style="touch-action: none"
    @class([
        'group absolute select-none rounded-[7px] border px-2 py-1 text-left',
        $styles['bg'], $styles['bd'],
        'inset-x-1' => $compact,
        'left-[3.75rem] right-2' => ! $compact,
    ])
    :class="kind ? 'z-20 cursor-grabbing shadow-map ring-1 ring-ink/10' : 'cursor-grab'"
>
    {{-- The coloured "Strich" down the left edge. --}}
    <span class="absolute inset-y-0 left-0 w-1 rounded-l-[7px] {{ $styles['bar'] }}"></span>

    {{-- Start time in the gutter (day view) — rides along while dragging. --}}
    @unless($compact)
        <span class="tnum absolute -left-[3.6rem] top-0 w-[3.2rem] text-right text-[11px] font-medium {{ $isBreak ? 'text-ink-faint' : 'text-ink' }}">{{ $event->start_time }}</span>
    @endunless

    {{-- Resize handles (top / bottom of the strich). --}}
    <div @pointerdown.stop="begin('top', $event)" class="absolute inset-x-0 top-0 z-10 h-2 cursor-ns-resize" aria-hidden="true"></div>

    <div class="overflow-hidden pl-1.5 {{ $compact ? '' : 'pr-6' }}">
        @if ($isBreak)
            <p class="truncate text-[11px] {{ $styles['tx'] }}">Pause · {{ $event->durationMinutes() }}′</p>
        @elseif ($isSession)
            <p class="truncate text-[12px] font-medium {{ $styles['tx'] }}">{{ $event->type === 'todo_session' ? 'To-Do-Session' : 'Work-Session' }}</p>
            @unless ($compact)
                <p class="truncate text-[11px] text-ink-soft">{{ $event->suggestedTask?->title ? 'Vorschlag: '.$event->suggestedTask->title : 'Fokuszeit' }}</p>
            @endunless
        @else
            <p class="truncate text-[12px] font-medium text-ink">{{ $event->title }}</p>
            @unless ($compact)
                <p class="tnum truncate text-[11px] {{ $styles['tx'] }}">{{ $event->start_time }}–{{ $event->end_time }}</p>
            @endunless
        @endif
    </div>

    {{-- Desktop: edit pencil on hover. --}}
    <button
        type="button"
        wire:click="startEditEvent({{ $event->id }})"
        @pointerdown.stop
        class="absolute right-1 top-1 hidden h-6 w-6 place-items-center rounded-md border border-line bg-paper/90 text-ink-soft opacity-0 transition group-hover:grid group-hover:opacity-100 hover:text-ink"
        aria-label="Termin bearbeiten"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    </button>

    <div @pointerdown.stop="begin('bottom', $event)" class="absolute inset-x-0 bottom-0 z-10 h-2 cursor-ns-resize" aria-hidden="true"></div>
</div>
