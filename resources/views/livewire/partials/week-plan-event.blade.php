@php
    use App\Models\ScheduleEvent;

    $startMin = $template->startMinutes();
    $endMin = $template->endMinutes();
    $token = $template->colorToken();
    $days = $template->recurrenceDays();
    // Short blocks would have their whole body covered by resize handles, blocking
    // the move gesture — only offer resize handles when there's room.
    $resizable = $template->duration >= 30;

    $bufferBefore = (int) $template->buffer_before;
    $bufferAfter = (int) $template->buffer_after;

    // Topografie colour tokens → literal classes (kept literal for the JIT scanner).
    $styles = match ($token) {
        'forest'    => ['bg' => 'bg-forest-soft',    'bd' => 'border-forest/40',    'tx' => 'text-forest',    'bar' => 'bg-forest',    'buf' => 'tl-buf-forest'],
        'overprint' => ['bg' => 'bg-overprint-soft', 'bd' => 'border-overprint/40', 'tx' => 'text-overprint', 'bar' => 'bg-overprint', 'buf' => 'tl-buf-overprint'],
        'signal'    => ['bg' => 'bg-signal-soft',    'bd' => 'border-signal/40',    'tx' => 'text-signal',    'bar' => 'bg-signal',    'buf' => 'tl-buf-signal'],
        'ink-faint' => ['bg' => 'bg-surface',        'bd' => 'border-line',         'tx' => 'text-ink-faint', 'bar' => 'bg-ink-faint/50', 'buf' => 'tl-buf-ink'],
        'ink'       => ['bg' => 'bg-surface',        'bd' => 'border-ink-faint/40', 'tx' => 'text-ink',       'bar' => 'bg-ink-faint', 'buf' => 'tl-buf-ink'],
        default     => ['bg' => 'bg-contour-soft',   'bd' => 'border-contour/40',   'tx' => 'text-contour',   'bar' => 'bg-contour',   'buf' => 'tl-buf-contour'],
    };
@endphp
{{-- Same wrapper/clipped-body split as partials/schedule-event.blade.php, for the
     same reason — see the block comment in app.css. The two files stay separate
     copies on purpose: merging them into one component would mean reworking both
     pages' gesture wiring in the same change, and a block here carries a weekday
     recurrence instead of a date, linked tasks and attribute values. --}}
<div
    wire:key="wp-{{ $template->id }}-{{ $weekday }}"
    data-schedule-block
    x-data="scheduleEvent({ id: {{ $template->id }}, start: {{ $startMin }}, end: {{ $endMin }}, bufBefore: {{ $bufferBefore }}, bufAfter: {{ $bufferAfter }} })"
    x-bind:style="blockStyle"
    @pointerdown="begin('move', $event)"
    @pointermove="drag($event)"
    @pointerup="finish()"
    @pointercancel="finish()"
    @pointerenter="if ($event.pointerType === 'mouse') lift(false)"
    @pointerleave="if ($event.pointerType === 'mouse') settle()"
    @schedule-block-settle.window="settle()"
    style="touch-action: none"
    class="tl-block group absolute inset-x-1 min-h-[16px] select-none text-left"
    :class="(kind ? 'z-20 cursor-grabbing' : 'cursor-grab') + (lifted ? ' tl-block-lifted' : '')"
>
    @if ($bufferBefore > 0)
        <div
            class="tl-buf tl-buf-before {{ $styles['buf'] }}"
            x-bind:style="bufBeforeStyle"
            role="img"
            aria-label="{{ $bufferBefore }} Minuten Wegzeit davor — los um {{ ScheduleEvent::fromMinutes(max(0, $startMin - $bufferBefore)) }}"
            title="{{ $bufferBefore }} Min Weg · los um {{ ScheduleEvent::fromMinutes(max(0, $startMin - $bufferBefore)) }}"
        ></div>
    @endif
    @if ($bufferAfter > 0)
        <div
            class="tl-buf tl-buf-after {{ $styles['buf'] }}"
            x-bind:style="bufAfterStyle"
            role="img"
            aria-label="{{ $bufferAfter }} Minuten Wegzeit danach — zurück um {{ ScheduleEvent::fromMinutes(min(1440, $endMin + $bufferAfter)) }}"
            title="{{ $bufferAfter }} Min Weg · zurück um {{ ScheduleEvent::fromMinutes(min(1440, $endMin + $bufferAfter)) }}"
        ></div>
    @endif

    <div class="tl-body rounded-[7px] border {{ $styles['bg'] }} {{ $styles['bd'] }}" :class="kind && 'shadow-map ring-1 ring-ink/10'">
        {{-- The coloured "Strich" down the left edge. --}}
        <span class="absolute inset-y-0 left-0 w-1 rounded-l-[7px] {{ $styles['bar'] }}"></span>

        {{-- Resize handles (top / bottom of the strich) — only when there's room. --}}
        @if ($resizable)
            <div @pointerdown.stop="begin('top', $event)" class="absolute inset-x-0 top-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
        @endif

        <div class="tl-pad py-1 pl-3.5 pr-6">
            <p class="tl-title flex items-center gap-1 truncate text-[12px] leading-[18px] font-medium text-ink">
                <span class="truncate">{{ $template->displayName() }}</span>
                {{-- Same block, same time, on more than one weekday — a quiet reminder
                     that dragging this one instance moves it everywhere it repeats. --}}
                @if (count($days) > 1)
                    <span class="flex-none rounded-full bg-ink/10 px-1 text-[9px] font-semibold {{ $styles['tx'] }}">×{{ count($days) }}</span>
                @endif
            </p>
            <p class="tl-time tl-opt tl-opt-time tnum truncate text-[11px] leading-[15px] {{ $styles['tx'] }}">{{ $template->default_start }}–{{ ScheduleEvent::fromMinutes($endMin) }}</p>
        </div>

        <button
            type="button"
            wire:click="startEditEvent({{ $template->id }})"
            @pointerdown.stop
            class="tl-opt tl-opt-pencil absolute right-1 top-1 z-20 h-6 w-6 place-items-center rounded-md border border-line bg-paper/90 text-ink-soft opacity-0 transition group-hover:opacity-100 hover:text-ink"
            :class="lifted && 'opacity-100'"
            aria-label="Block bearbeiten"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        </button>

        @if ($resizable)
            <div @pointerdown.stop="begin('bottom', $event)" class="absolute inset-x-0 bottom-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
        @endif
    </div>
</div>
