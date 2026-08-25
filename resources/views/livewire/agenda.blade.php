<div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-6 md:pb-12">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Agenda</h1>
            <p class="mt-1 text-[13px] text-ink-faint">Hausaufgaben &amp; Prüfungen — unabhängig von deinen Listen</p>
        </div>
        <div class="flex flex-none items-center gap-2">
            <button
                type="button"
                wire:click="openSpaces"
                @class([
                    'flex items-center gap-1.5 rounded-card border px-3 py-2 text-sm transition',
                    'border-contour/40 bg-contour-soft text-contour' => $this->spaces->isNotEmpty(),
                    'border-line text-ink-soft hover:bg-surface hover:text-ink' => $this->spaces->isEmpty(),
                ])
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 19v-1.4a3.4 3.4 0 0 0-3.4-3.4H7.4A3.4 3.4 0 0 0 4 17.6V19"/><circle cx="10" cy="8.2" r="3.1"/><path d="M20 19v-1.4a3.4 3.4 0 0 0-2.6-3.3M15.4 5.3a3.4 3.4 0 0 1 0 6"/>
                </svg>
                <span class="hidden sm:inline">Klassen</span>
                @if ($this->spaces->isNotEmpty())
                    <span class="tnum text-[12px] font-medium">{{ $this->spaces->count() }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="openCreateForm"
                class="rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
            >
                + Eintrag
            </button>
        </div>
    </div>

    @if (session('agenda-joined'))
        <div class="mt-4 flex items-center gap-2.5 rounded-card border border-forest/30 bg-forest-soft px-3.5 py-2.5">
            <svg class="h-4 w-4 flex-none text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m5 12.5 4.5 4.5L19 7.5"/>
            </svg>
            <p class="text-[13px] text-forest">Du bist „{{ session('agenda-joined') }}" beigetreten.</p>
        </div>
    @endif

    <div class="mt-5 inline-flex gap-1 rounded-card border border-line bg-surface p-1">
        @foreach (['all' => 'Alle', 'homework' => 'Hausaufgaben', 'exam' => 'Prüfungen'] as $val => $lbl)
            <button
                type="button"
                wire:click="setFilter('{{ $val }}')"
                @class([
                    'rounded-[0.4rem] px-3 py-1.5 text-sm transition',
                    'bg-forest text-white' => $filterType === $val,
                    'text-ink-soft hover:text-ink' => $filterType !== $val,
                ])
            >{{ $lbl }}</button>
        @endforeach
    </div>

    {{-- The whole subject filter only exists once there's at least one subject to
         filter by — an agenda with no entries yet shows no empty dropdown. --}}
    @if ($this->existingSubjects->isNotEmpty())
        <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative mt-2.5 inline-block">
            <button
                type="button"
                @click="open = !open"
                @class([
                    'flex items-center gap-1.5 rounded-card border px-2.5 py-1.5 text-[12.5px] transition',
                    'border-contour/40 bg-contour-soft text-contour' => $filterSubject !== 'all',
                    'border-line text-ink-soft hover:bg-surface hover:text-ink' => $filterSubject === 'all',
                ])
            >
                <span>Fach: {{ $filterSubject === 'all' ? 'Alle' : $filterSubject }}</span>
                <svg class="h-3 w-3 transition" :class="open ? '-scale-y-100' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute left-0 z-10 mt-1 max-h-52 w-44 overflow-y-auto rounded-card border border-line bg-surface p-1 shadow-map"
                style="display: none;"
            >
                <button
                    type="button"
                    @click="open = false"
                    wire:click="setSubjectFilter('all')"
                    @class([
                        'block w-full rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition',
                        'bg-contour-soft font-medium text-contour' => $filterSubject === 'all',
                        'text-ink-soft hover:bg-paper hover:text-ink' => $filterSubject !== 'all',
                    ])
                >Alle Fächer</button>
                @foreach ($this->existingSubjects as $subject)
                    <button
                        type="button"
                        wire:key="subject-filter-{{ $subject }}"
                        @click="open = false"
                        wire:click="setSubjectFilter('{{ $subject }}')"
                        @class([
                            'block w-full truncate rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition',
                            'bg-contour-soft font-medium text-contour' => $filterSubject === $subject,
                            'text-ink-soft hover:bg-paper hover:text-ink' => $filterSubject !== $subject,
                        ])
                    >{{ $subject }}</button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- The whole space row only exists once there is a class to filter by, so
         a solo user's Agenda looks exactly as it always did. --}}
    @if ($this->spaces->isNotEmpty())
        <div class="mt-2.5 flex flex-wrap items-center gap-x-1 gap-y-1.5 px-0.5">
            @php
                $spaceChips = collect([['all', 'Alle Räume'], ['mine', 'Nur ich']])
                    ->concat($this->spaces->map(fn ($s) => [(string) $s->id, $s->shortName()]));
            @endphp
            @foreach ($spaceChips as [$val, $lbl])
                <button
                    type="button"
                    wire:click="setSpaceFilter('{{ $val }}')"
                    wire:key="space-chip-{{ $val }}"
                    @class([
                        'rounded-card px-2.5 py-1 text-[12.5px] transition',
                        'bg-contour-soft font-medium text-contour' => $filterSpace === $val,
                        'text-ink-faint hover:text-ink-soft' => $filterSpace !== $val,
                    ])
                >{{ $lbl }}</button>
            @endforeach

            <button
                type="button"
                wire:click="toggleGrouping"
                @class([
                    'ml-auto flex items-center gap-1 rounded-card px-2 py-1 text-[12px] transition',
                    'text-contour' => $groupBySpace,
                    'text-ink-faint hover:text-ink-soft' => !$groupBySpace,
                ])
                title="{{ $groupBySpace ? 'Nach Datum sortieren' : 'Nach Klasse gruppieren' }}"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @if ($groupBySpace)
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    @else
                        <path d="M4 5h16M4 9h16M4 15h16M4 19h16"/>
                    @endif
                </svg>
                {{ $groupBySpace ? 'nach Klasse' : 'nach Datum' }}
            </button>
        </div>

        {{-- Soft ambient hint that a classmate is actively creating or editing an
             entry right now — the whole point is to notice before you write the
             same thing down twice. Always rendered (just collapsed to nothing)
             rather than an @if, so appearing/disappearing is a smooth height +
             opacity transition instead of a hard DOM swap on the next poll.
             This is also the one poll for the whole drafting feature: the same
             tick that refreshes what classmates are up to doubles as this
             user's own heartbeat (heartbeatDraft() no-ops unless their own form
             is open) — a second, independent poll on the create-form sheet used
             to run alongside this one and occasionally collided with it. --}}
        <div
            wire:poll.3s.visible="heartbeatDraft"
            @class([
                'overflow-hidden transition-all duration-300 ease-out',
                'mt-3 max-h-40 opacity-100' => $this->draftLines->isNotEmpty(),
                'mt-0 max-h-0 opacity-0' => $this->draftLines->isEmpty(),
            ])
        >
            <div class="space-y-1.5 rounded-card bg-paper px-3 py-2">
                @foreach ($this->draftLines as $line)
                    <p wire:key="draft-line-{{ $line['space']->id }}" class="flex items-center gap-2 text-[12.5px] text-ink-soft">
                        <span class="h-1.5 w-1.5 flex-none animate-pulse rounded-full bg-forest" aria-hidden="true"></span>
                        <span class="truncate">{{ $line['text'] }}{{ $this->spaces->count() > 1 ? ' — '.$line['space']->shortName() : '' }}</span>
                    </p>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->openEntries->isEmpty())
        <div class="mt-4 rounded-card border border-dashed border-line p-10 text-center">
            <svg class="mx-auto mb-3 h-8 w-8 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
            </svg>
            {{-- Three different nothings: everything ticked off, nothing matching
                 the current filter, and a genuinely empty agenda. "Leg deine erste
                 Hausaufgabe an" is wrong for the first two. --}}
            <p class="text-sm text-ink-faint">
                @if ($this->doneEntries->isNotEmpty())
                    Alles erledigt.
                @elseif ($filterSpace !== 'all' || $filterType !== 'all' || $filterSubject !== 'all')
                    Hier ist gerade nichts offen.
                @else
                    Keine Einträge — leg deine erste Hausaufgabe oder Prüfung an.
                @endif
            </p>
        </div>
    @elseif ($groupBySpace)
        @foreach ($this->openGroups as $group)
            <div wire:key="group-{{ $group['key'] }}" class="mt-4">
                <div class="mb-2 flex items-baseline gap-2 px-0.5">
                    <h2 class="text-[12px] font-medium uppercase tracking-wide text-ink-faint">{{ $group['label'] }}</h2>
                    @if ($group['meta'])
                        <span class="text-[11.5px] text-ink-faint">· {{ $group['meta'] }}</span>
                    @endif
                </div>
                <div class="space-y-2">
                    @foreach ($group['entries'] as $entry)
                        @include('livewire.partials.agenda-entry', ['entry' => $entry, 'showSpaceBadge' => false])
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="mt-4 space-y-2">
            @foreach ($this->openEntries as $entry)
                @include('livewire.partials.agenda-entry', ['entry' => $entry])
            @endforeach
        </div>
    @endif

    @if ($this->doneEntries->isNotEmpty())
        <div x-data="{ show: false }" class="mt-4">
            <button type="button" @click="show = !show" class="px-0.5 text-[12.5px] text-ink-faint transition hover:text-ink-soft">
                <span x-show="!show">{{ $this->doneEntries->count() }} erledigt · anzeigen</span>
                <span x-show="show" style="display: none;">{{ $this->doneEntries->count() }} erledigt · ausblenden</span>
            </button>
            <div x-show="show" x-transition class="mt-2 space-y-2" style="display: none;">
                @foreach ($this->doneEntries as $entry)
                    @include('livewire.partials.agenda-entry', ['entry' => $entry])
                @endforeach
            </div>
        </div>
    @endif

    @include('livewire.partials.agenda-entry-form')
    @include('livewire.partials.agenda-spaces-sheet')
</div>
