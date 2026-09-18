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
                {{-- Same --ember-* fire gradient as the header streak badge (see
                     partials/header-badge.blade.php) — kept consistent so the two
                     renderings of "current streak" can never visually disagree. --}}
                <div @class([
                    'grid h-11 w-11 flex-none place-items-center rounded-full',
                    'bg-paper text-[rgb(var(--ember-glow))]' => $tier <= 1,
                    'bg-[rgb(var(--ember-warm-soft))] text-[rgb(var(--ember-warm))]' => $tier === 2,
                    'bg-[rgb(var(--ember-hot-soft))] text-[rgb(var(--ember-hot))]' => $tier === 3,
                    'bg-[rgb(var(--ember-blaze))] text-white streak-tier-4' => $tier === 4,
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
                    {{-- Only shown once a freeze was actually spent this week — an
                         unused budget needs no explanation, but a survived gap does. --}}
                    @if ($this->freezesUsedThisWeek > 0)
                        <p class="mt-0.5 text-xs text-ink-faint">
                            {{ $this->freezesUsedThisWeek }}/{{ \App\Services\ProgressStats::MAX_FREEZES_PER_WEEK }}
                            Ruhetage diese Woche genutzt
                        </p>
                    @endif
                    {{-- UX research finding: today can have real completions while the
                         streak still shows 0, because the streak specifically counts
                         days where every "Heute"-flagged task got done — a day with
                         completions but no "Heute" flag at all otherwise reads as
                         "broken" rather than "not started". Easiest to hit in
                         Eisenhower, where "Heute" is one small toggle pill per card
                         rather than a whole visible zone. --}}
                    @if ($this->todayHasCompletionsButNoTodayList)
                        <p class="mt-1.5 text-xs leading-relaxed text-ink-faint">
                            Serie zählt nur Tage mit erledigten „Heute"-Aufgaben — heute ist noch keine markiert.
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

    {{-- "Für die Serie heute" — the concrete answer to "what do I still need
         to do", not just the abstract streak number. Hidden entirely once
         today is already secured, matching the app's "no sad/empty state
         when there's nothing to show" convention. --}}
    @unless ($this->streakTasksNeeded['secured'])
        <div class="mt-4 rounded-card border border-line bg-surface p-5 shadow-map">
            @if ($this->streakTasksNeeded['openTasks']->isNotEmpty())
                <h2 class="text-sm font-medium text-ink">Für die Serie heute</h2>
                <p class="mt-0.5 text-xs text-ink-soft">
                    {{ $this->streakTasksNeeded['openTasks']->count() === 1
                        ? 'Noch diese Aufgabe erledigen:'
                        : 'Noch diese ' . $this->streakTasksNeeded['openTasks']->count() . ' Aufgaben erledigen:' }}
                </p>
                <ul class="mt-3 space-y-2">
                    @foreach ($this->streakTasksNeeded['openTasks'] as $task)
                        <li class="flex items-center gap-2.5" wire:key="streak-task-{{ $task->id }}">
                            <button
                                type="button"
                                wire:click="toggleStreakTask({{ $task->id }})"
                                class="grid h-5 w-5 flex-none place-items-center rounded-full border-2 border-line text-transparent transition hover:border-forest hover:text-forest focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                                aria-label="Erledigt markieren: {{ $task->title }}"
                            >
                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                    <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <a
                                href="{{ url('/app') }}?task={{ $task->id }}"
                                class="min-w-0 flex-1 truncate text-sm text-ink transition hover:text-forest"
                                wire:navigate
                            >{{ $task->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <h2 class="text-sm font-medium text-ink">Für die Serie heute</h2>
                <p class="mt-1.5 text-xs leading-relaxed text-ink-soft">
                    Noch {{ $this->streakTasksNeeded['remainingForGoal'] }}
                    {{ $this->streakTasksNeeded['remainingForGoal'] === 1 ? 'Aufgabe (egal welche)' : 'Aufgaben (egal welche)' }}
                    für heute — oder das ganze Board leeren.
                </p>
                <a href="{{ url('/app') }}" class="hit-area mt-2 inline-block text-xs font-medium text-forest hover:underline" wire:navigate>
                    Zum Board →
                </a>
            @endif
        </div>
    @endunless

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
                            title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }} · {{ $day['count'] === 1 ? '1 Aufgabe' : $day['count'].' Aufgaben' }}{{ $day['isStreakDay'] ? ' · Serientag' : ($day['isFrozen'] ? ' · Ruhetag' : '') }}"
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
                                // Takes priority over the streak-day ring below when both
                                // apply, since "today" already has its own status pill.
                                'ring-1 ring-inset ring-ink' => $day['isToday'],
                                // A day that counted toward the streak — a different axis
                                // than the fill color (volume): a low-volume day can still
                                // be a perfect streak day, and a high-volume one might not
                                // ever have been flagged "today" at all.
                                'ring-1 ring-inset ring-contour' => $day['isStreakDay'] && ! $day['isToday'],
                                // A "frozen" rest day (see Fortschritt's Ruhetage) — spent
                                // one of the week's two freezes rather than breaking.
                                'border border-dashed border-ink-faint' => $day['isFrozen'] && ! $day['isToday'],
                            ])
                        ></div>
                    @endif
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-end gap-x-4 gap-y-1.5 text-[11px] text-ink-faint">
                <span class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded-[3px] ring-1 ring-inset ring-contour"></span>
                    Serientag
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded-[3px] border border-dashed border-ink-faint"></span>
                    Ruhetag
                </span>
                <span class="flex items-center gap-1.5">
                    <span>weniger</span>
                    <span class="h-3 w-3 rounded-[3px] bg-line/60"></span>
                    <span class="h-3 w-3 rounded-[3px] bg-forest/25"></span>
                    <span class="h-3 w-3 rounded-[3px] bg-forest/55"></span>
                    <span class="h-3 w-3 rounded-[3px] bg-forest/80"></span>
                    <span class="h-3 w-3 rounded-[3px] bg-forest"></span>
                    <span>mehr</span>
                </span>
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
