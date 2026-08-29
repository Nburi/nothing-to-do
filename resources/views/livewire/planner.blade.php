@php
    $today = auth()->user()->localToday();
    $horizonEnd = $today->copy()->addDays(\App\Services\DayPlanner::HORIZON_DAYS - 1);
    $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
@endphp

<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ url('/app') }}" wire:navigate class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-medium text-ink">Planer</h1>
                <p class="text-xs text-ink-faint">Zieh eine Aufgabe auf einen Tag · nächste {{ \App\Services\DayPlanner::HORIZON_DAYS }} Tage</p>
            </div>
        </div>

        @if ($this->backlog->isNotEmpty())
            <button
                type="button"
                wire:click="autoFillBacklog"
                class="inline-flex flex-none items-center gap-1.5 rounded-card border border-line bg-surface px-3 py-2 text-sm text-ink-soft transition hover:text-ink active:scale-[0.98]"
            >
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2v3h-3"/></svg>
                Rest automatisch einplanen
            </button>
        @endif
    </div>

    {{-- Conflict banner — always visible when non-empty; this is the page's actual point. --}}
    @if ($this->conflicts->isNotEmpty())
        <div class="mb-5 rounded-card border border-signal/30 bg-signal-soft p-4">
            <p class="mb-3 text-sm font-medium text-signal">
                {{ $this->conflicts->count() }} {{ $this->conflicts->count() === 1 ? 'passt' : 'passen' }} zeitlich nicht mehr rein
            </p>
            <div class="divide-y divide-signal/15">
                @foreach ($this->conflicts as $item)
                    @include('livewire.partials.planner-conflict', ['item' => $item])
                @endforeach
            </div>
        </div>
    @else
        <p class="mb-5 text-sm text-ink-soft">Alles Geplante passt bis {{ $horizonEnd->isoFormat('D. MMM') }}.</p>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <aside class="flex w-full flex-none flex-col gap-2.5 rounded-card border border-dashed border-line bg-surface p-3 sm:w-56">
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-ink">Nicht eingeplant</h2>
                <span class="tnum text-xs text-ink-faint">{{ $this->backlog->count() }}</span>
            </div>

            <div
                data-backlog
                x-data
                x-init="window.plannerDaySortable($el, $wire)"
                class="flex min-h-[3rem] flex-col gap-1.5"
            >
                @forelse ($this->backlog as $item)
                    @include('livewire.partials.planner-task-chip', [...$item, 'showRemove' => false])
                @empty
                    <p class="text-xs text-ink-faint">Alles eingeplant oder erledigt.</p>
                @endforelse
            </div>
        </aside>

        <div class="flex flex-1 gap-3 overflow-x-auto pb-2" style="min-width: 0;">
            @foreach ($this->board as $dateKey => $day)
                @php
                    $date = $day['date'];
                    $label = match (true) {
                        $date->isSameDay($today) => 'Heute · '.$wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
                        $date->isSameDay($today->copy()->addDay()) => 'Morgen · '.$wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
                        default => $wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
                    };
                @endphp
                @include('livewire.partials.planner-day-column', [
                    'dateKey' => $dateKey,
                    'day' => $day,
                    'dayOffset' => $loop->index,
                    'isToday' => $date->isSameDay($today),
                    'label' => $label,
                ])
            @endforeach
        </div>
    </div>

    @include('livewire.partials.planner-day-picker-sheet')
</div>
