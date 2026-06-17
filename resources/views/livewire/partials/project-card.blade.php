{{-- A project entry in the Projects list. Tapping it opens the project page.
     Expects $project with the `activeTasks` relation loaded + a `done_count`. --}}
@php
    $next = $project->activeTasks->first();
    $open = $project->activeTasks->count();
    $done = $project->done_count;
    $total = $open + $done;
    $pct = $total > 0 ? round(($done / $total) * 100) : 0;
@endphp

<a
    href="{{ route('project.show', $project) }}"
    wire:navigate
    wire:key="project-{{ $project->id }}"
    class="group/proj block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50"
>
    <div class="flex items-start justify-between gap-2">
        <h3 class="min-w-0 break-words text-sm font-medium text-ink">{{ $project->name }}</h3>
        <svg class="mt-0.5 h-4 w-4 flex-none text-ink-faint transition group-hover/proj:translate-x-0.5 group-hover/proj:text-ink-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    @if ($total === 0)
        <p class="mt-2 text-[13px] text-ink-faint">Noch keine Aufgaben.</p>
    @else
        @if ($next)
            <p class="mt-2 truncate text-[13px] text-ink-soft">{{ $next->title }}</p>
            @if ($open > 1)
                <p class="mt-0.5 text-[11px] text-ink-faint">+{{ $open - 1 }} weitere offen</p>
            @endif
        @else
            <p class="mt-2 text-[13px] text-ink-faint">Alle Aufgaben erledigt.</p>
        @endif

        <div class="mt-3 flex items-center gap-2.5">
            <div class="h-1 flex-1 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ $total }}" aria-label="Fortschritt">
                <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
            </div>
            <span class="tnum flex-none text-[11px] text-ink-faint">{{ $done }}/{{ $total }}</span>
        </div>
    @endif
</a>
