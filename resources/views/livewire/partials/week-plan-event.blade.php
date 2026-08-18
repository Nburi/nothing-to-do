@php
    use App\Models\ScheduleEvent;

    $startMin = $template->startMinutes();
    $endMin = $template->endMinutes();
    $token = $template->colorToken();
    $days = $template->recurrenceDays();
    // Short blocks would have their whole body covered by resize handles, blocking
    // the move gesture — only offer resize handles when there's room.
    $resizable = $template->duration >= 30;

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
    wire:key="wp-{{ $template->id }}-{{ $weekday }}"
    x-data="scheduleEvent({ id: {{ $template->id }}, start: {{ $startMin }}, end: {{ $endMin }} })"
    x-bind:style="`top:${top}%; height:${height}%`"
    @pointerdown="begin('move', $event)"
    @pointermove="drag($event)"
    @pointerup="finish()"
    @pointercancel="finish()"
    style="touch-action: none"
    class="group absolute inset-x-1 min-h-[16px] select-none rounded-[7px] border px-2 py-1 text-left {{ $styles['bg'] }} {{ $styles['bd'] }}"
    :class="kind ? 'z-20 cursor-grabbing shadow-map ring-1 ring-ink/10' : 'cursor-grab'"
>
    {{-- The coloured "Strich" down the left edge. --}}
    <span class="absolute inset-y-0 left-0 w-1 rounded-l-[7px] {{ $styles['bar'] }}"></span>

    {{-- Resize handles (top / bottom of the strich) — only when there's room. --}}
    @if ($resizable)
        <div @pointerdown.stop="begin('top', $event)" class="absolute inset-x-0 top-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
    @endif

    <div class="overflow-hidden pl-1.5 pr-6">
        <p class="flex items-center gap-1 truncate text-[12px] font-medium text-ink">
            <span class="truncate">{{ $template->displayName() }}</span>
            {{-- Same block, same time, on more than one weekday — a quiet reminder
                 that dragging this one instance moves it everywhere it repeats. --}}
            @if (count($days) > 1)
                <span class="flex-none rounded-full bg-ink/10 px-1 text-[9px] font-semibold {{ $styles['tx'] }}">×{{ count($days) }}</span>
            @endif
        </p>
        <p class="tnum truncate text-[11px] {{ $styles['tx'] }}">{{ $template->default_start }}–{{ ScheduleEvent::fromMinutes($endMin) }}</p>
    </div>

    {{-- Desktop: edit pencil on hover. --}}
    <button
        type="button"
        wire:click="startEditEvent({{ $template->id }})"
        @pointerdown.stop
        class="absolute right-1 top-1 hidden h-6 w-6 place-items-center rounded-md border border-line bg-paper/90 text-ink-soft opacity-0 transition group-hover:grid group-hover:opacity-100 hover:text-ink"
        aria-label="Block bearbeiten"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    </button>

    @if ($resizable)
        <div @pointerdown.stop="begin('bottom', $event)" class="absolute inset-x-0 bottom-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
    @endif
</div>
