@php
    $startMin = $event->startMinutes();
    $endMin = $event->endMinutes();
    $token = $event->colorToken();
    $compact = $compact ?? false;
    // Short events would have their whole body covered by resize handles, blocking
    // the move gesture — only offer resize handles when there's room.
    $resizable = $event->durationMinutes() >= 30;

    $bufferBefore = (int) $event->buffer_before;
    $bufferAfter = (int) $event->buffer_after;

    // Topografie colour tokens → literal classes (kept literal for the JIT scanner).
    $styles = match ($token) {
        'forest'    => ['bg' => 'bg-forest-soft',    'bd' => 'border-forest/40',    'tx' => 'text-forest',    'bar' => 'bg-forest',    'buf' => 'tl-buf-forest'],
        'overprint' => ['bg' => 'bg-overprint-soft', 'bd' => 'border-overprint/40', 'tx' => 'text-overprint', 'bar' => 'bg-overprint', 'buf' => 'tl-buf-overprint'],
        'signal'    => ['bg' => 'bg-signal-soft',    'bd' => 'border-signal/40',    'tx' => 'text-signal',    'bar' => 'bg-signal',    'buf' => 'tl-buf-signal'],
        'ink-faint' => ['bg' => 'bg-surface',        'bd' => 'border-line',         'tx' => 'text-ink-faint', 'bar' => 'bg-ink-faint/50', 'buf' => 'tl-buf-ink'],
        'ink'       => ['bg' => 'bg-surface',        'bd' => 'border-ink-faint/40', 'tx' => 'text-ink',       'bar' => 'bg-ink-faint', 'buf' => 'tl-buf-ink'],
        default     => ['bg' => 'bg-contour-soft',   'bd' => 'border-contour/40',   'tx' => 'text-contour',   'bar' => 'bg-contour',   'buf' => 'tl-buf-contour'],
    };

    // One relation load, not one query per helper — pendingLinkedTasks/nextLinkedTask/
    // extraLinkedCount below all read from this same in-memory collection.
    $pendingLinkedTasks = $event->linkedTasks->reject(fn ($t) => $t->is_completed)->values();
    $nextLinkedTask = $pendingLinkedTasks->first();
    $extraLinkedCount = max(0, $pendingLinkedTasks->count() - 1);

    // Custom-attribute values (Kategorie-Attribute). Both tiers are rendered and CSS
    // picks: the full "label + value" line whenever the block's own body is tall
    // enough for an extra row, and otherwise just the 'select' values' colour dots,
    // squeezed into the title row. Which of the two applies is decided by the body's
    // measured height (the @container queries in app.css), not by a duration in
    // minutes — the same 30 minutes is a different number of pixels on the phone, in
    // the desktop week view, and at any other Tagesrahmen (see DayWindow::ppm).
    $attrRows = $event->attributeValues->isNotEmpty() ? $event->attributeDisplayRows() : collect();
    // Only the 'select' rows carry a dot colour — kept as full rows, not just colours, so the
    // dots stay accessible (an aria-label + per-dot title) instead of colour-only information.
    $dotRows = $attrRows->filter(fn (array $row) => $row['dot'] !== null)->values();
    $dotClass = fn (?string $token) => match ($token) {
        'forest' => 'bg-forest',
        'overprint' => 'bg-overprint',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
        default => 'bg-contour',
    };
@endphp
{{-- The wrapper only positions and carries the gestures; it is deliberately never
     clipped, because two things hang outside it on purpose: the day view's start-time
     label in the gutter, and the Weg-/Pufferzeit bands. The clipping happens one level
     down, on .tl-body — see the block comment in app.css. --}}
<div
    wire:key="ev-{{ $event->id }}"
    data-schedule-block
    x-data="scheduleEvent({ id: {{ $event->id }}, start: {{ $startMin }}, end: {{ $endMin }}, bufBefore: {{ $bufferBefore }}, bufAfter: {{ $bufferAfter }} })"
    x-bind:style="blockStyle"
    @pointerdown="begin('move', $event)"
    @pointermove="drag($event)"
    @pointerup="finish()"
    @pointercancel="finish()"
    {{-- Hover lifts on a mouse only. Read from the event's own pointerType rather
         than a device-level media query: a touchscreen laptop driven by a real mouse
         must still get the hover behaviour (see CLAUDE.md §10 on `pointer: coarse`). --}}
    @pointerenter="if ($event.pointerType === 'mouse') lift(false)"
    @pointerleave="if ($event.pointerType === 'mouse') settle()"
    @schedule-block-settle.window="settle()"
    style="touch-action: none"
    @class([
        'tl-block group absolute min-h-[16px] select-none text-left',
        'inset-x-1' => $compact,
        'left-[3.75rem] right-2' => ! $compact,
    ])
    :class="(kind ? 'z-20 cursor-grabbing' : 'cursor-grab') + (lifted ? ' tl-block-lifted' : '')"
>
    {{-- Weg-/Pufferzeit: a hatched extension of the block, no title and no gestures
         of its own. Positioned in percent of the block, so a drag-move or a
         drag-resize carries it along with no second geometry to keep in sync. --}}
    @if ($bufferBefore > 0)
        <div
            class="tl-buf tl-buf-before {{ $styles['buf'] }}"
            x-bind:style="bufBeforeStyle"
            role="img"
            aria-label="{{ $bufferBefore }} Minuten Wegzeit davor — los um {{ $event->departureTime() }}"
            title="{{ $bufferBefore }} Min Weg · los um {{ $event->departureTime() }}"
        ></div>
    @endif
    @if ($bufferAfter > 0)
        <div
            class="tl-buf tl-buf-after {{ $styles['buf'] }}"
            x-bind:style="bufAfterStyle"
            role="img"
            aria-label="{{ $bufferAfter }} Minuten Wegzeit danach — zurück um {{ \App\Models\ScheduleEvent::fromMinutes($event->occupiedEndMinutes()) }}"
            title="{{ $bufferAfter }} Min Weg · zurück um {{ \App\Models\ScheduleEvent::fromMinutes($event->occupiedEndMinutes()) }}"
        ></div>
    @endif

    {{-- Start time in the gutter (day view) — rides along while dragging. With a
         Weg-/Pufferzeit before it, the departure time sits quietly above it: knowing
         when to leave is the whole point of having entered one, and it would
         otherwise only be readable by opening the form. --}}
    @unless($compact)
        <span class="tnum absolute -left-[3.6rem] top-0 w-[3.2rem] text-right text-[11px] font-medium text-ink">{{ $event->start_time }}</span>
        @if ($bufferBefore > 0)
            <span class="tnum absolute -left-[3.6rem] -top-[0.95rem] w-[3.2rem] text-right text-[10px] {{ $styles['tx'] }} opacity-80">{{ $event->departureTime() }}</span>
        @endif
    @endunless

    <div
        @class([
            'tl-body rounded-[7px] border',
            $styles['bg'], $styles['bd'],
            // The "Zeitplan" header badge's ?event= link lands here — a brief
            // highlight proves the badge showed exactly this block, not just
            // some page. See Schedule::$highlightEventId.
            'badge-jump-highlight' => ($highlightEventId ?? null) === $event->id,
        ])
        :class="kind && 'shadow-map ring-1 ring-ink/10'"
    >
        {{-- The coloured "Strich" down the left edge. --}}
        <span class="absolute inset-y-0 left-0 w-1 rounded-l-[7px] {{ $styles['bar'] }}"></span>

        {{-- Resize handles (top / bottom of the strich) — only when there's room. --}}
        @if ($resizable)
            <div @pointerdown.stop="begin('top', $event)" class="absolute inset-x-0 top-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
        @endif

        <div class="tl-pad py-1 pl-3.5 {{ $compact ? 'pr-2' : 'pr-6' }}">
            <p class="tl-title flex items-center gap-1 truncate text-[12px] leading-[18px] font-medium text-ink" x-data="{ revealed: false, _t: null }">
                @if ($event->category?->pomodoro_enabled)
                    <svg class="h-3 w-3 flex-none {{ $styles['tx'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/></svg>
                @endif
                @if ($nextLinkedTask !== null)
                    {{-- Signature moment of the linked-task feature: tap briefly reveals the next
                         open linked task's title (the one the focus timer would suggest too — see
                         TaskSuggestor) in place of the entry's own title, plus a "+N" if more are
                         bound; a second tap (within the same window) opens that task on the board. --}}
                    <button
                        type="button"
                        @pointerdown.stop
                        @click.stop="if (revealed) { $wire.navigateToLinkedTask({{ $event->id }}); clearTimeout(_t); revealed = false; } else { revealed = true; clearTimeout(_t); _t = setTimeout(() => revealed = false, 2000); }"
                        aria-label="Nächste verknüpfte Aufgabe {{ $nextLinkedTask->title }}{{ $extraLinkedCount > 0 ? ", plus {$extraLinkedCount} weitere" : '' }} — antippen zum Öffnen"
                        class="flex-none {{ $styles['tx'] }} transition hover:opacity-70"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 6.5l4 4L7 21H3v-4L13.5 6.5z"/><path d="M12 8l4 4"/></svg>
                    </button>
                    <span class="truncate" :class="revealed && 'italic'" x-text="revealed ? @js($nextLinkedTask->title) : @js($event->displayTitle())"></span>
                    @if ($extraLinkedCount > 0)
                        <span class="flex-none text-[10px] {{ $styles['tx'] }} opacity-70">+{{ $extraLinkedCount }}</span>
                    @endif
                @else
                    <span class="truncate">{{ $event->displayTitle() }}</span>
                @endif
                {{-- Compact attribute preview for a block with no room for the full line
                     below: just the 'select' values' own colour dots, still enough to scan a
                     whole week for "which kind of training was when". Never colour-only — the
                     group carries an aria-label and each dot a hover title. --}}
                @if ($dotRows->isNotEmpty())
                    <span class="tl-opt-dots flex-none items-center gap-0.5" aria-label="{{ $dotRows->pluck('display')->implode(', ') }}">
                        @foreach ($dotRows as $row)
                            <span class="h-1.5 w-1.5 flex-none rounded-full {{ $dotClass($row['dot']) }}" title="{{ $row['display'] }}"></span>
                        @endforeach
                    </span>
                @endif
            </p>

            <p class="tl-time tl-opt tl-opt-time tnum truncate text-[11px] leading-[15px] {{ $styles['tx'] }}">{{ $event->start_time }}–{{ $event->end_time }}</p>

            {{-- Kategorie-Attribute: the full summary, only when the block has room for
                 another row. A 'select' value's own colour shows as a small dot — glance at
                 a week and see which kind of training happened when, without tapping. --}}
            @if ($attrRows->isNotEmpty())
                <p class="tl-opt tl-opt-attrs mt-0.5 flex-wrap items-center gap-x-2 gap-y-0.5 truncate text-[10px] leading-[14px] {{ $styles['tx'] }} opacity-80">
                    @foreach ($attrRows as $row)
                        <span class="inline-flex items-center gap-1">
                            @if ($row['dot'])
                                <span class="h-1.5 w-1.5 flex-none rounded-full {{ $dotClass($row['dot']) }}"></span>
                            @endif
                            {{ $row['display'] }}
                        </span>
                    @endforeach
                </p>
            @endif
        </div>

        {{-- Edit pencil. Its *presence* is decided by the block's height (the
             @container query on .tl-opt-pencil), its *visibility* by hover on a mouse
             and by the lift on touch — where it never existed before this, since
             group-hover simply never fires there. --}}
        <button
            type="button"
            wire:click="startEditEvent({{ $event->id }})"
            {{-- Re-arms the lift window: on touch the block would otherwise be
                 free to lie back down between pressing the pencil and the click
                 landing, taking the pencil with it. --}}
            @pointerdown.stop="lift(true)"
            class="tl-opt tl-opt-pencil absolute right-1 top-1 z-20 h-6 w-6 place-items-center rounded-md border border-line bg-paper/90 text-ink-soft opacity-0 transition group-hover:opacity-100 hover:text-ink"
            :class="lifted && 'opacity-100'"
            aria-label="Eintrag bearbeiten"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        </button>

        @if ($resizable)
            <div @pointerdown.stop="begin('bottom', $event)" class="absolute inset-x-0 bottom-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
        @endif
    </div>
</div>
