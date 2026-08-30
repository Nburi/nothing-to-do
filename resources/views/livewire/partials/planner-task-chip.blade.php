{{--
    Shared by the backlog rail, every day column, and (read-only, for its
    tap-to-assign) the mobile day-picker sheet target. `$showRemove` is the
    only structural difference — only a chip already sitting on a day gets
    the small "×"; a backlog chip's only action is being picked up.

    `$fixedWidth` (a Tailwind width/max-width class, e.g. "max-w-56") is
    opt-in — the day-column list is `flex-col`, where a chip naturally
    stretches to the column's own width for free; the backlog list is a
    wrapping row instead, where a chip needs its own cap so it reads as a
    tidy pill rather than growing to its title's full natural width. Left
    unset for the day-column call site so its existing sizing is untouched.
    Applied directly on this root (not a wrapper div) so `[data-chip]`
    stays a *direct* child of the Sortable container either way — Sortable
    reads its `draggable` selector against direct children only.

    data-duration/data-deadline-offset feed the client-side tier wave
    (see plannerClassifyDay in app.js) the instant a chip is picked up, so
    no round trip is needed just to show which days are available.
    data-subject rides along too, purely for the mobile day-picker sheet's
    header (plannerChipInfo() in app.js) — that sheet reads `.chip-title`
    straight off the DOM rather than going through Livewire again, and
    since $title below is now just the entry's own title (the subject
    moved into its own tag), the sheet needs a separate way to reconstruct
    "Subject: Title" for its heading.

    The small tag above the title is what tells items apart at a glance on
    a board that otherwise mixes To-Dos, Tasks, Projekt-tasks and homework
    in one flat list — a `type=agenda` chip only ever exists here (once
    placed on a day it's promoted into a real Task, see
    DayPlanner::resolveOrPromote()), so it's the one case worth its own
    colour: `forest`, matching the same "Hausaufgabe" tone the Agenda page
    already uses. A plain task's tag is neutral instead, since To-Do/Task/
    Projekt aren't a meaningful/urgent/important distinction the way a
    deadline or the star is — just which list it lives in.
--}}
@php
    $deadlineOffset = $deadlineOffset ?? null;
    $deadlineLabel = $deadlineLabel ?? null;
    $fixedWidth = $fixedWidth ?? null;
    $subject = $subject ?? null;
    $list = $list ?? null;
    $listLabel = ['todos' => 'To-Do', 'tasks' => 'Task', 'projects' => 'Projekt'][$list] ?? null;
@endphp
<div
    data-chip
    data-type="{{ $type }}"
    data-id="{{ $id }}"
    data-duration="{{ $duration }}"
    @if ($deadlineOffset !== null) data-deadline-offset="{{ $deadlineOffset }}" @endif
    @if ($subject) data-subject="{{ $subject }}" @endif
    wire:key="planner-chip-{{ $type }}-{{ $id }}"
    x-data
    x-init="window.plannerTap($el, $wire)"
    class="flex select-none items-center gap-1.5 rounded-card border border-line/70 bg-paper px-2 py-1.5 {{ $fixedWidth ? 'flex-none '.$fixedWidth : '' }}"
>
    <span data-drag-handle class="grid h-6 w-6 flex-none touch-none cursor-grab place-items-center text-ink-faint active:cursor-grabbing" aria-hidden="true">
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" />
            <circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" />
            <circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" />
        </svg>
    </span>
    <span class="min-w-0 flex-1">
        @if ($subject)
            <span class="mb-0.5 inline-block max-w-full truncate rounded-full bg-forest-soft px-1.5 py-0.5 text-[10px] font-medium leading-none text-forest">{{ $subject }}</span>
        @elseif ($listLabel)
            <span class="mb-0.5 inline-block rounded-full bg-line px-1.5 py-0.5 text-[10px] font-medium leading-none text-ink-faint">{{ $listLabel }}</span>
        @endif
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
