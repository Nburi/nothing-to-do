@php
    use Illuminate\Support\Carbon;

    $today = auth()->user()->localToday();
    $horizonEnd = $today->copy()->addDays(\App\Services\WorkPlanner::HORIZON_DAYS);
    $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
@endphp

<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ url('/app') }}" wire:navigate class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-medium text-ink">Planer</h1>
                <p class="text-xs text-ink-faint">Nächste {{ \App\Services\WorkPlanner::HORIZON_DAYS }} Tage</p>
            </div>
        </div>

        <button
            type="button"
            x-data="{ armed: false, _t: null }"
            @click="if (armed) { $wire.regenerate(); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
            @click.outside="armed = false; clearTimeout(_t)"
            @keydown.escape.window="armed = false; clearTimeout(_t)"
            :class="armed ? 'border-signal bg-signal text-white' : 'border-line bg-surface text-ink-soft hover:text-ink'"
            class="inline-flex flex-none items-center gap-1.5 rounded-card border px-3 py-2 text-sm transition active:scale-[0.98]"
        >
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2v3h-3"/></svg>
            <span x-text="armed ? 'Wirklich neu planen?' : 'Neu planen'">Neu planen</span>
        </button>
    </div>

    {{-- Manuell platzierte Aufgaben bleiben stehen — nur die automatische Schicht wird neu verteilt. --}}
    <p class="mb-5 text-xs text-ink-faint">„Neu planen" lässt den Algorithmus alles neu verteilen — auch was du von Hand platziert hast.</p>

    {{-- Conflict banner — always visible when non-empty; this is the point of the page. --}}
    @if ($this->conflicts->isNotEmpty())
        <div class="mb-6 rounded-card border border-signal/30 bg-signal-soft p-4">
            <p class="mb-3 text-sm font-medium text-signal">
                {{ $this->conflicts->count() }} {{ $this->conflicts->count() === 1 ? 'passt' : 'passen' }} nicht mehr rein
            </p>
            <div class="divide-y divide-signal/15">
                @foreach ($this->conflicts as $item)
                    @include('livewire.partials.planner-conflict', ['item' => $item])
                @endforeach
            </div>
        </div>
    @else
        <p class="mb-6 text-sm text-ink-soft">Alles passt bis {{ $horizonEnd->isoFormat('D. MMM') }}.</p>
    @endif

    {{-- Upcoming work-blocks, day by day. --}}
    @forelse ($this->blocks as $dateStr => $dayBlocks)
        @php
            $day = Carbon::parse($dateStr);
            $dayLabel = match (true) {
                $day->isSameDay($today) => 'Heute · '.$wd[$day->dayOfWeek].' '.$day->isoFormat('D.M.'),
                $day->isSameDay($today->copy()->addDay()) => 'Morgen · '.$wd[$day->dayOfWeek].' '.$day->isoFormat('D.M.'),
                default => $wd[$day->dayOfWeek].' '.$day->isoFormat('D.M.'),
            };
        @endphp
        <div class="mb-6" wire:key="planner-day-{{ $dateStr }}">
            <p class="mb-2 text-xs font-medium text-ink-faint">{{ $dayLabel }}</p>
            <div class="space-y-3">
                @foreach ($dayBlocks as $block)
                    @include('livewire.partials.planner-block', ['block' => $block])
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-card border border-dashed border-line p-8 text-center">
            <p class="text-sm text-ink-soft">Keine Pomodoro-Arbeitsblöcke in den nächsten {{ \App\Services\WorkPlanner::HORIZON_DAYS }} Tagen.</p>
            <p class="mt-1 text-xs text-ink-faint">Der Planer verteilt Aufgaben nur auf Kategorie-Blöcke mit aktiviertem Pomodoro-Timer.</p>
            <a href="{{ route('weekplan') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-overprint hover:underline">Wochenplan öffnen →</a>
        </div>
    @endforelse

    @include('livewire.partials.schedule-event-form')
</div>
