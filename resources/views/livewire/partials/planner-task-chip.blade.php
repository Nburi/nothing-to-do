{{--
    Shared by the backlog rail, every day column, and (read-only, for its
    tap-to-assign) the mobile day-picker sheet target. `$showRemove` is the
    only structural difference — only a chip already sitting on a day gets
    the small "×"; a backlog chip's only action is being picked up.

    data-duration/data-deadline-offset feed the client-side tier wave
    (see plannerClassifyDay in app.js) the instant a chip is picked up, so
    no round trip is needed just to show which days are available.
--}}
@php
    $deadlineOffset = $deadlineOffset ?? null;
    $deadlineLabel = $deadlineLabel ?? null;
@endphp
<div
    data-chip
    data-type="{{ $type }}"
    data-id="{{ $id }}"
    data-duration="{{ $duration }}"
    @if ($deadlineOffset !== null) data-deadline-offset="{{ $deadlineOffset }}" @endif
    wire:key="planner-chip-{{ $type }}-{{ $id }}"
    x-data
    x-init="window.plannerTap($el, $wire)"
    class="flex select-none items-center gap-1.5 rounded-card border border-line/70 bg-paper px-2 py-1.5"
>
    <span data-drag-handle class="grid h-6 w-6 flex-none touch-none cursor-grab place-items-center text-ink-faint active:cursor-grabbing" aria-hidden="true">
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" />
            <circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" />
            <circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" />
        </svg>
    </span>
    <span class="min-w-0 flex-1">
        <span class="chip-title block truncate text-sm text-ink">
            @if ($isImportant)<span class="text-overprint">★ </span>@endif{{ $title }}
        </span>
        <span class="flex items-center gap-1.5 text-[11px] text-ink-faint">
            <span class="tnum {{ $hasEstimate ? '' : 'italic' }}">{{ $hasEstimate ? '' : '≈' }}{{ $duration }} min</span>
            @if ($deadlineLabel === 'überfällig')
                <span class="text-signal">· überfällig</span>
            @elseif ($deadlineLabel)
                <span class="text-contour">· fällig {{ $deadlineLabel }}</span>
            @endif
        </span>
    </span>
    @if ($showRemove)
        <button
            type="button"
            wire:click="unassignTask({{ $id }})"
            class="grid h-5 w-5 flex-none place-items-center rounded text-ink-faint transition hover:bg-signal-soft hover:text-signal"
            aria-label="{{ $title }} nicht mehr für diesen Tag einplanen"
        >
            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
        </button>
    @endif
</div>
