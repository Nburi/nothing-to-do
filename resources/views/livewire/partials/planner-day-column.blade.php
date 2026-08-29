{{--
    One day of the board. Its own capacity bar comes from that day's
    Pomodoro-enabled blocks only (App\Services\DayPlanner::dayCapacities) —
    a day with none shows no bar and is still a fully valid drop target,
    unlike the old block-based Planer where such a day simply didn't exist
    on the page at all.

    data-day-offset/data-date/data-capacity-* feed the client-side tier
    wave and the mobile day-picker sheet (see app.js) — both read these
    directly rather than round-tripping to the server.
--}}
<div
    data-day-column
    data-day-offset="{{ $dayOffset }}"
    data-date="{{ $dateKey }}"
    data-day-label="{{ $label }}"
    data-capacity-total="{{ $day['capacityTotal'] }}"
    data-capacity-used="{{ $day['capacityUsed'] }}"
    wire:key="planner-daycol-{{ $dateKey }}"
    class="planner-day flex w-48 flex-none flex-col gap-2.5 rounded-card border border-line bg-surface p-3 shadow-map {{ $isToday ? 'border-overprint/40' : '' }}"
>
    <p class="text-sm font-medium text-ink">{{ $label }}</p>

    @if ($day['capacityTotal'] > 0)
        <div class="flex flex-col gap-1">
            <div class="h-1 overflow-hidden rounded-full bg-line">
                <div class="h-full rounded-full bg-ink-soft" style="width: {{ min(100, round(($day['capacityUsed'] / $day['capacityTotal']) * 100)) }}%"></div>
            </div>
            <p class="tnum truncate text-[11px] text-ink-faint">{{ $day['capacityUsed'] }}/{{ $day['capacityTotal'] }} min · {{ implode(' · ', $day['blockLabels']) }}</p>
        </div>
    @else
        <p class="text-[11px] italic text-ink-faint">Kein Arbeitsblock geplant</p>
    @endif

    <div
        data-date="{{ $dateKey }}"
        x-data
        x-init="window.plannerDaySortable($el, $wire)"
        class="flex min-h-[3rem] flex-1 flex-col gap-1.5"
    >
        @forelse ($day['tasks'] as $item)
            @include('livewire.partials.planner-task-chip', [...$item, 'showRemove' => true])
        @empty
            <p class="rounded-card border border-dashed border-line px-2 py-3 text-center text-[11px] text-ink-faint">Hierhin ziehen</p>
        @endforelse
    </div>
</div>
