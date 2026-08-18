@php
    $tier = \App\Services\ProgressStats::streakTier($this->currentStreak);
    $ringPct = $this->goal > 0 ? min(1, $this->todayCount / $this->goal) : 0;
    $ringRadius = 15.5;
    $ringCircumference = 2 * M_PI * $ringRadius;
    $ringDash = $ringCircumference * $ringPct;
@endphp

<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Fortschritt</h1>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-card border border-line bg-surface p-5 shadow-map">
            <div class="flex items-center gap-3.5">
                <svg class="h-11 w-11 flex-none -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                    <circle cx="18" cy="18" r="{{ $ringRadius }}" fill="none" stroke="rgb(var(--line))" stroke-width="3" />
                    <circle
                        cx="18" cy="18" r="{{ $ringRadius }}" fill="none" stroke="rgb(var(--forest))" stroke-width="3"
                        stroke-linecap="round" stroke-dasharray="{{ $ringDash }} {{ $ringCircumference }}"
                    />
                </svg>
                <div class="min-w-0">
                    <p class="tnum text-2xl font-medium leading-none text-ink">
                        {{ $this->todayCount }}<span class="text-sm font-normal text-ink-faint"> / {{ $this->goal }}</span>
                    </p>
                    <p class="mt-1.5 text-xs text-ink-soft">Aufgaben heute erledigt</p>
                </div>
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-5 shadow-map">
            <div class="flex items-center gap-3.5">
                <div @class([
                    'grid h-11 w-11 flex-none place-items-center rounded-full',
                    'bg-paper text-ink-faint' => $tier <= 1,
                    'bg-contour-soft text-contour' => $tier === 2,
                    'bg-forest-soft text-forest' => $tier === 3,
                    'bg-forest text-white' => $tier === 4,
                ])>
                    <x-flame-icon class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="tnum text-2xl font-medium leading-none text-ink">{{ $this->currentStreak }}</p>
                    <p class="mt-1.5 text-xs text-ink-soft">
                        {{ $this->currentStreak === 1 ? 'Tag Serie' : 'Tage Serie' }} · Bestwert {{ $this->bestStreak }}
                    </p>
                    @if ($this->perfectDayRate !== null)
                        <p class="mt-0.5 text-xs text-ink-faint">
                            {{ $this->perfectDaysCount }} {{ $this->perfectDaysCount === 1 ? 'perfekter Tag' : 'perfekte Tage' }} ·
                            {{ $this->perfectDayRate }}% Erfolgsquote
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-5 shadow-map">
            <p class="tnum text-2xl font-medium leading-none text-ink-soft">{{ $this->totalCompleted }}</p>
            <p class="mt-1.5 text-xs text-ink-soft">Insgesamt erledigt</p>
        </div>
    </div>

    {{-- Heatmap --}}
    <div class="mt-8">
        <h2 class="mb-3 text-sm font-medium text-ink">Letzte 12 Wochen</h2>
        <div class="overflow-x-auto rounded-card border border-line bg-surface p-4 shadow-map">
            <div class="grid w-max grid-flow-col grid-rows-7 gap-[3px]">
                @foreach ($this->heatmap as $day)
                    @if ($day['isFuture'])
                        <div class="h-3 w-3 rounded-[3px]"></div>
                    @else
                        <div
                            title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }} · {{ $day['count'] === 1 ? '1 Aufgabe' : $day['count'].' Aufgaben' }}"
                            @class([
                                'h-3 w-3 rounded-[3px]',
                                'bg-line/60' => $day['level'] === 0,
                                'bg-forest/25' => $day['level'] === 1,
                                'bg-forest/55' => $day['level'] === 2,
                                'bg-forest/80' => $day['level'] === 3,
                                'bg-forest' => $day['level'] === 4,
                                // Plain "you are here" marker — deliberately ink, not a
                                // palette color that also means something else on this
                                // page (overprint = record, contour = perfect day, forest
                                // = goal/volume), so it never reads as a success signal.
                                'ring-1 ring-inset ring-ink' => $day['isToday'],
                            ])
                        ></div>
                    @endif
                @endforeach
            </div>
            <div class="mt-3 flex items-center justify-end gap-1.5 text-[11px] text-ink-faint">
                <span>weniger</span>
                <span class="h-3 w-3 rounded-[3px] bg-line/60"></span>
                <span class="h-3 w-3 rounded-[3px] bg-forest/25"></span>
                <span class="h-3 w-3 rounded-[3px] bg-forest/55"></span>
                <span class="h-3 w-3 rounded-[3px] bg-forest/80"></span>
                <span class="h-3 w-3 rounded-[3px] bg-forest"></span>
                <span>mehr</span>
            </div>
        </div>
    </div>

    @if ($this->bestDailyCount > 0)
        <p class="mt-4 text-sm text-ink-soft">
            Bestwert: <span class="font-medium text-ink">{{ $this->bestDailyCount }}</span>
            {{ $this->bestDailyCount === 1 ? 'Aufgabe' : 'Aufgaben' }} an einem Tag.
        </p>
    @endif
</div>
