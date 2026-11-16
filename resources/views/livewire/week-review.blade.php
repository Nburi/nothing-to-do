@php
    $r = $this->review;
    $max = max(1, collect($r['days'])->max('count'));
    $rangeLabel = $r['start']->format('d.m.').' – '.$r['end']->format('d.m.Y');
    $chartLabel = 'Erledigte Aufgaben pro Tag: '.collect($r['days'])->map(fn ($d) => $d['label'].' '.$d['count'])->implode(', ');
@endphp

<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('progress') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zu Fortschritt" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Wochenrückblick</h1>
    </div>

    {{-- Week pager --}}
    <div class="flex items-center justify-between gap-3">
        <button type="button" wire:click="previousWeek" class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest" aria-label="Vorherige Woche">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3.5-4.5 4.5L10 12.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="text-center">
            <p class="tnum text-sm font-medium text-ink">{{ $rangeLabel }}</p>
            <p class="text-xs text-ink-faint">
                @if ($r['isCurrent'])
                    Diese Woche
                @elseif ($weekOffset === -1)
                    Letzte Woche
                @else
                    Vor {{ abs($weekOffset) }} Wochen
                @endif
            </p>
        </div>
        <button type="button" wire:click="nextWeek" @disabled($weekOffset >= 0) class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest disabled:cursor-not-allowed disabled:opacity-40" aria-label="Nächste Woche">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 3.5 4.5 4.5L6 12.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>

    {{-- The one honest sentence about the week --}}
    <p class="mt-6 text-lg font-medium leading-snug text-ink" wire:key="verdict-{{ $weekOffset }}">{{ $r['verdict'] }}</p>

    {{-- Bars: pure CSS heights out of the per-day counts (the same counts the
         Fortschritt heatmap reads, so the two can never disagree). --}}
    <div class="mt-5 rounded-card border border-line bg-surface p-5 shadow-map">
        <div class="flex h-36 items-end justify-between gap-2" role="img" aria-label="{{ $chartLabel }}">
            @foreach ($r['days'] as $day)
                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5" wire:key="bar-{{ $day['date'] }}">
                    <span class="tnum text-xs {{ $day['count'] > 0 ? 'text-ink-soft' : 'text-transparent' }}">{{ $day['count'] }}</span>
                    <div
                        @class([
                            'w-full max-w-9 rounded-t-md',
                            'bg-forest' => $day['count'] > 0,
                            'bg-line' => $day['count'] === 0,
                            'ring-2 ring-contour ring-offset-2 ring-offset-surface' => $day['isToday'],
                        ])
                        style="height: {{ $day['count'] > 0 ? max(6, round(100 * $day['count'] / $max * 0.78)) : 3 }}%"
                    ></div>
                    <span @class(['text-[11px]', 'font-medium text-ink' => $day['isToday'], 'text-ink-faint' => ! $day['isToday']])>{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Numbers --}}
    <div class="mt-3 grid grid-cols-3 gap-3">
        <div class="rounded-card border border-line bg-surface p-4 shadow-map">
            <p class="tnum text-2xl font-medium leading-none text-ink">{{ $r['total'] }}</p>
            <p class="mt-1.5 text-xs text-ink-soft">erledigt</p>
        </div>
        <div class="rounded-card border border-line bg-surface p-4 shadow-map">
            <p class="tnum text-2xl font-medium leading-none text-ink">{{ $r['activeDays'] }}<span class="text-sm font-normal text-ink-faint"> / 7</span></p>
            <p class="mt-1.5 text-xs text-ink-soft">aktive Tage</p>
        </div>
        <div class="rounded-card border border-line bg-surface p-4 shadow-map">
            @if ($r['bestDay'])
                <p class="truncate text-base font-medium leading-none text-ink">{{ $r['bestDay']['label'] }}</p>
                <p class="mt-1.5 text-xs text-ink-soft">stärkster Tag · {{ $r['bestDay']['count'] }}</p>
            @else
                <p class="text-base font-medium leading-none text-ink-faint">—</p>
                <p class="mt-1.5 text-xs text-ink-soft">stärkster Tag</p>
            @endif
        </div>
    </div>

    {{-- Still open from this week --}}
    @if ($r['openCount'] > 0)
        <div class="mt-4 rounded-card border border-line bg-contour-soft p-5">
            <h2 class="text-sm font-medium text-ink">
                {{ $r['isCurrent'] ? 'Noch offen diese Woche' : 'Liegengeblieben' }}
                <span class="tnum ml-1 text-ink-soft">· {{ $r['openCount'] }}</span>
            </h2>
            <ul class="mt-2.5 space-y-1.5">
                @foreach ($r['open'] as $task)
                    <li class="flex items-baseline justify-between gap-3 text-sm" wire:key="open-{{ $task['id'] }}">
                        <a href="{{ route('app', ['task' => $task['id']]) }}" wire:navigate class="min-w-0 truncate text-ink transition hover:text-forest">{{ $task['title'] }}</a>
                        <span class="tnum flex-none text-xs text-ink-faint">{{ $task['dateLabel'] }}</span>
                    </li>
                @endforeach
                @if ($r['openCount'] > count($r['open']))
                    <li class="text-xs text-ink-faint">… und {{ $r['openCount'] - count($r['open']) }} weitere</li>
                @endif
            </ul>
        </div>
    @endif

    {{-- What was finished --}}
    @if ($r['completed'] !== [])
        <div class="mt-4 rounded-card border border-line bg-surface p-5 shadow-map">
            <h2 class="text-sm font-medium text-ink">Geschafft</h2>
            <div class="mt-3 space-y-4">
                @foreach ($r['completed'] as $group)
                    <div wire:key="done-{{ $group['label'] }}">
                        <p class="text-xs font-medium text-ink-faint">{{ $group['label'] }}</p>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($group['titles'] as $title)
                                <li class="flex gap-2 text-sm text-ink-soft">
                                    <svg class="mt-1 h-3 w-3 flex-none text-forest" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span class="min-w-0 break-words">{{ $title }}</span>
                                </li>
                            @endforeach
                            @if ($group['more'] > 0)
                                <li class="text-xs text-ink-faint">… und {{ $group['more'] }} weitere</li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif (! $r['isFuture'] && $r['total'] === 0)
        <p class="mt-6 text-center text-sm text-ink-faint">Nichts zu zeigen — {{ $r['isCurrent'] ? 'die Woche gehört dir.' : 'eine ruhige Woche.' }}</p>
    @endif
</div>
