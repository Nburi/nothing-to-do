@php
    $statusTone = fn (int $code) => match (true) {
        $code >= 500 => 'bg-signal-soft text-signal',
        $code === 404 => 'bg-line text-ink-soft',
        default => 'bg-contour-soft text-contour',
    };
    $maxDayCount = max([1, ...array_values($dayCounts = $this->dayCounts)]);
    $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
@endphp
<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-5 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Fehler-Statistiken</h1>
    </div>

    @if (empty($this->statusCounts))
        <p class="rounded-card border border-line bg-surface p-5 text-sm text-ink-soft">Noch keine Fehler aufgetreten. Sobald jemand auf einen kaputten Link oder einen unerwarteten Fehler stösst, taucht er hier auf.</p>
    @else
        {{-- 14-Tage-Trend --}}
        <div class="mb-6 rounded-card border border-line bg-surface p-4 shadow-map">
            <p class="mb-3 text-xs font-medium text-ink-faint">Letzte 14 Tage</p>
            <div class="flex h-16 items-end gap-1.5">
                @foreach ($days as $day)
                    @php $count = $dayCounts[$day] ?? 0; @endphp
                    <div
                        class="min-w-0 flex-1 rounded-sm bg-contour-soft transition"
                        style="height: {{ $count > 0 ? max(8, round($count / $maxDayCount * 100)) : 3 }}%"
                        title="{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('D.M.') }} — {{ $count }} {{ $count === 1 ? 'Fehler' : 'Fehler' }}"
                    ></div>
                @endforeach
            </div>
        </div>

        {{-- Häufigste Pfade --}}
        @if ($this->topPaths->isNotEmpty())
            <div class="mb-6 rounded-card border border-line bg-surface p-4 shadow-map">
                <p class="mb-3 text-xs font-medium text-ink-faint">Häufigste Pfade</p>
                <div class="space-y-1.5">
                    @foreach ($this->topPaths as $row)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <code class="min-w-0 truncate text-ink-soft">{{ $row->path }}</code>
                            <span class="flex-none text-xs text-ink-faint">{{ $row->total }}×</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Status-Filter --}}
        <div class="mb-4 flex flex-wrap gap-1.5">
            <button type="button" wire:click="setStatusFilter(null)" @class(['rounded-full px-3.5 py-1.5 text-sm transition', 'bg-ink text-white' => $statusFilter === null, 'bg-surface text-ink-soft hover:text-ink' => $statusFilter !== null])>Alle · {{ array_sum($this->statusCounts) }}</button>
            @foreach ($this->statusCounts as $code => $count)
                <button type="button" wire:click="setStatusFilter({{ $code }})" @class(['rounded-full px-3.5 py-1.5 text-sm transition', 'bg-ink text-white' => $statusFilter === $code, 'bg-surface text-ink-soft hover:text-ink' => $statusFilter !== $code])>{{ $code }} · {{ $count }}</button>
            @endforeach
        </div>

        {{-- Letzte Vorkommnisse --}}
        <div class="space-y-2">
            @forelse ($this->occurrences as $occurrence)
                <div wire:key="err-{{ $occurrence->id }}" class="rounded-card border border-line bg-surface p-4 shadow-map">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none {{ $statusTone($occurrence->status_code) }}">{{ $occurrence->status_code }}</span>
                        <code class="truncate text-xs text-ink-soft">{{ $occurrence->method }} {{ $occurrence->path }}</code>
                        <span class="ml-auto text-xs text-ink-faint">{{ $occurrence->created_at->isoFormat('D.M.YYYY, HH:mm') }}</span>
                    </div>
                    @if ($occurrence->message)
                        <p class="mt-1 truncate text-sm text-ink-soft">{{ $occurrence->message }}</p>
                    @endif
                    <p class="mt-1 text-xs text-ink-faint">{{ $occurrence->exception_class ?? 'HTTP-Fehler' }}{{ $occurrence->user ? ' · '.$occurrence->user->name : ' · Gast' }}</p>
                </div>
            @empty
                <p class="text-sm text-ink-faint">Nichts hier.</p>
            @endforelse
        </div>
    @endif
</div>
