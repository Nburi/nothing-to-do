@php
    $startMin = $event->startMinutes();
    $endMin = $event->endMinutes();
    $token = $event->colorToken();
    $compact = $compact ?? false;
    // Short events would have their whole body covered by resize handles, blocking
    // the move gesture — only offer resize handles when there's room.
    $resizable = $event->durationMinutes() >= 30;

    // Topografie colour tokens → literal classes (kept literal for the JIT scanner).
    $styles = match ($token) {
        'forest'    => ['bg' => 'bg-forest-soft',    'bd' => 'border-forest/40',    'tx' => 'text-forest',    'bar' => 'bg-forest'],
        'overprint' => ['bg' => 'bg-overprint-soft', 'bd' => 'border-overprint/40', 'tx' => 'text-overprint', 'bar' => 'bg-overprint'],
        'signal'    => ['bg' => 'bg-signal-soft',    'bd' => 'border-signal/40',    'tx' => 'text-signal',    'bar' => 'bg-signal'],
        'ink-faint' => ['bg' => 'bg-surface',        'bd' => 'border-line',         'tx' => 'text-ink-faint', 'bar' => 'bg-ink-faint/50'],
        'ink'       => ['bg' => 'bg-surface',        'bd' => 'border-ink-faint/40', 'tx' => 'text-ink',       'bar' => 'bg-ink-faint'],
        default     => ['bg' => 'bg-contour-soft',   'bd' => 'border-contour/40',   'tx' => 'text-contour',   'bar' => 'bg-contour'],
    };

    // One relation load, not one query per helper — pendingLinkedTasks/nextLinkedTask/
    // extraLinkedCount below all read from this same in-memory collection.
    $pendingLinkedTasks = $event->linkedTasks->reject(fn ($t) => $t->is_completed)->values();
    $nextLinkedTask = $pendingLinkedTasks->first();
    $extraLinkedCount = max(0, $pendingLinkedTasks->count() - 1);

    // Custom-attribute values (Kategorie-Attribute). Two tiers, depending on how much room the
    // block actually has: the full "label + value" summary whenever there's a whole extra line
    // to spare vertically (>=30min — the same threshold the resize handles already use, in both
    // the day view and the narrow desktop week view; a short column still truncates gracefully
    // via the row's own `truncate` class), and — for anything shorter than that, in either view —
    // a compact preview instead: just the 'select' values' own colour dots, with no label,
    // squeezed into the title row itself rather than a line of their own. A 'select' value's dot
    // colour is a literal class match, kept out of a dynamic string for the Tailwind JIT scanner.
    $allAttrRows = $event->attributeValues->isNotEmpty() ? $event->attributeDisplayRows() : collect();
    $fullAttrDisplay = $resizable;
    $attrRows = $fullAttrDisplay ? $allAttrRows : collect();
    // Only the 'select' rows carry a dot colour — kept as full rows, not just colours, so the
    // dots stay accessible (an aria-label + per-dot title) instead of colour-only information.
    $dotRows = $fullAttrDisplay ? collect() : $allAttrRows->filter(fn (array $row) => $row['dot'] !== null)->values();
    $dotClass = fn (?string $token) => match ($token) {
        'forest' => 'bg-forest',
        'overprint' => 'bg-overprint',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
        default => 'bg-contour',
    };
@endphp
<div
    wire:key="ev-{{ $event->id }}"
    x-data="scheduleEvent({ id: {{ $event->id }}, start: {{ $startMin }}, end: {{ $endMin }} })"
    x-bind:style="`top:${top}%; height:${height}%`"
    @pointerdown="begin('move', $event)"
    @pointermove="drag($event)"
    @pointerup="finish()"
    @pointercancel="finish()"
    style="touch-action: none"
    @class([
        'group absolute min-h-[16px] select-none rounded-[7px] border px-2 py-1 text-left',
        $styles['bg'], $styles['bd'],
        'inset-x-1' => $compact,
        'left-[3.75rem] right-2' => ! $compact,
        // The "Zeitplan" header badge's ?event= link lands here — a brief
        // highlight proves the badge showed exactly this block, not just
        // some page. See Schedule::$highlightEventId.
        'badge-jump-highlight' => ($highlightEventId ?? null) === $event->id,
    ])
    :class="kind ? 'z-20 cursor-grabbing shadow-map ring-1 ring-ink/10' : 'cursor-grab'"
>
    {{-- The coloured "Strich" down the left edge. --}}
    <span class="absolute inset-y-0 left-0 w-1 rounded-l-[7px] {{ $styles['bar'] }}"></span>

    {{-- Start time in the gutter (day view) — rides along while dragging. --}}
    @unless($compact)
        <span class="tnum absolute -left-[3.6rem] top-0 w-[3.2rem] text-right text-[11px] font-medium text-ink">{{ $event->start_time }}</span>
    @endunless

    {{-- Resize handles (top / bottom of the strich) — only when there's room. --}}
    @if ($resizable)
        <div @pointerdown.stop="begin('top', $event)" class="absolute inset-x-0 top-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
    @endif

    <div class="overflow-hidden pl-1.5 {{ $compact ? '' : 'pr-6' }}">
        <p class="flex items-center gap-1 truncate text-[12px] font-medium text-ink" x-data="{ revealed: false, _t: null }">
            @if ($event->category?->pomodoro_enabled)
                <svg class="h-3 w-3 flex-none {{ $styles['tx'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/></svg>
            @endif
            @if ($nextLinkedTask !== null)
                {{-- Signature moment: tap briefly reveals the next open linked task's title
                     (the one the focus timer would suggest too — see TaskSuggestor) in place
                     of the entry's own title, plus a "+N" if more are bound; a second tap
                     (within the same window) opens that task on the board. Same "armed window"
                     shape as this app's destructive double-click confirms, repurposed here for
                     a reveal-then-navigate. --}}
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
            {{-- Compact attribute preview: shown only for a block too short (<30min) for the full
                 "Lauf 60 Min" line below — just the 'select' values' own colour dots ride along in
                 the title row instead, still enough to scan a whole week for "which kind of
                 training was when". Never colour-only: the group carries an aria-label and each
                 dot a hover title, so the value is never conveyed by colour alone. --}}
            @if ($dotRows->isNotEmpty())
                <span class="flex flex-none items-center gap-0.5" aria-label="{{ $dotRows->pluck('display')->implode(', ') }}">
                    @foreach ($dotRows as $row)
                        <span class="h-1.5 w-1.5 flex-none rounded-full {{ $dotClass($row['dot']) }}" title="{{ $row['display'] }}"></span>
                    @endforeach
                </span>
            @endif
        </p>
        @unless ($compact)
            <p class="tnum truncate text-[11px] {{ $styles['tx'] }}">{{ $event->start_time }}–{{ $event->end_time }}</p>
        @endunless

        {{-- Kategorie-Attribute: a compact summary, only when the block has room. A 'select'
             value's own colour shows as a small dot — glance at a week and see which kind of
             training (etc.) happened when, without tapping a single block. --}}
        @if ($attrRows->isNotEmpty())
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 truncate text-[10px] {{ $styles['tx'] }} opacity-80">
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

    {{-- Desktop: edit pencil on hover. --}}
    <button
        type="button"
        wire:click="startEditEvent({{ $event->id }})"
        @pointerdown.stop
        class="absolute right-1 top-1 hidden h-6 w-6 place-items-center rounded-md border border-line bg-paper/90 text-ink-soft opacity-0 transition group-hover:grid group-hover:opacity-100 hover:text-ink"
        aria-label="Eintrag bearbeiten"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    </button>

    @if ($resizable)
        <div @pointerdown.stop="begin('bottom', $event)" class="absolute inset-x-0 bottom-0 z-10 h-1.5 cursor-ns-resize" aria-hidden="true"></div>
    @endif
</div>
