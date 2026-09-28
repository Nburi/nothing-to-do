{{-- A task group entry in the groups quick-access strip. Same shell as
     project-card.blade.php (name, arrow, progress bar), but deliberately never
     previews a specific task's title — unlike a project, a group's own board
     columns already hide a plain (not important/today) grouped task on purpose,
     to cut noise (see TaskBoard::boardTasks()). Naming one of those hidden
     tasks here would quietly undo exactly the thing that hiding was for, so
     this card only ever shows the group's name and its open/done count.
     Expects $group with the `activeTasks` relation loaded + a `done_count`. --}}
@php
    $open = $group->activeTasks->count();
    $done = $group->done_count;
    $total = $open + $done;
    $pct = $total > 0 ? round(($done / $total) * 100) : 0;
@endphp

<a
    href="{{ route('group.show', $group) }}"
    wire:navigate
    class="group/grp block rounded-card border border-line border-l-[3px] border-l-ink-faint/55 bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50"
>
    <div class="flex items-start justify-between gap-2">
        <h3 class="min-w-0 break-words text-sm font-medium text-ink">{{ $group->name }}</h3>
        <svg class="mt-0.5 h-4 w-4 flex-none text-ink-faint transition group-hover/grp:translate-x-0.5 group-hover/grp:text-ink-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    @if ($total === 0)
        <p class="mt-2 text-[13px] text-ink-faint">Noch keine Aufgaben.</p>
    @elseif ($open === 0)
        <p class="mt-2 text-[13px] text-ink-faint">Alle Aufgaben erledigt.</p>
    @else
        <p class="mt-2 text-[13px] text-ink-soft">{{ $open }} {{ $open === 1 ? 'offene Aufgabe' : 'offene Aufgaben' }}</p>

        <div class="mt-3 flex items-center gap-2.5">
            <div class="h-1 flex-1 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ $total }}" aria-label="Fortschritt">
                <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
            </div>
            <span class="tnum flex-none text-[11px] text-ink-faint">{{ $done }}/{{ $total }}</span>
        </div>
    @endif
</a>
