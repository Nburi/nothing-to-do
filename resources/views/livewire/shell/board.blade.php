{{--
    The new app (refactor step 2.2). Two layouts in one component, chosen by CSS at 720 px:
    Werkbank (desktop) and Stapel (mobile). Inside each, a board region and a session region.

    Step 3.1, the signature moment "The board folds away" (CONVENTIONS 10, never tidy it away):
    the board region stays rendered during a session (inert, folded), so Livewire's morph keeps
    its elements and the switch of data-shell-mode is a CSS transition, not a page swap (risk C5).
    The Alpine part (shellFold in app.js) starts the fold on the click and lets the session card
    grow out of the card that was started. The fold-* classes and data-fold-* hooks belong to it.
--}}
@php
    $suggestion = $this->suggestion;
    $lists = $this->lists;
    $bundles = $this->bundles;
    // Step 4.8: the cards and rows of each column in the one board order, the cards the calm board lets rest, the „Wichtig" band.
    $entries = $this->entries;
    $resting = $this->resting;
    $band = $this->band;
    $todoSession = $this->todoSession;
    $counts = $this->counts;
    $suggestedId = $suggestion->hasPick() ? $suggestion->task?->id : null;
    $session = $this->session;
    $locked = $session->phase === \App\Planning\Session\Phase::Work;
    $timeline = $this->timeline;
    $done = $this->doneToday;
    $columns = ['inbox' => 'inbox', 'todos' => 'todos', 'tasks' => 'tasks', 'later' => 'later'];
    $inSession = $mode === 'session';
    // Step 3.2: the reset covers both layouts while it is offered, running or asking the battery.
    $reset = $this->resetStage;
    $covered = $reset->covers();
    // Step 3.4: free time. An idea from Later (or "Der Rest gehört dir.") takes the card's place when there is no work pick.
    $idea = $this->idea;
    $showIdea = ! $suggestion->hasPick() && $idea->status->showsCard() && array_sum($counts) > 0;
    $freePlain = $this->freeLabel($idea, false);
    // Step 4.2: the early warning (board: a line in the start strip; session: a quiet entry in the rail / drawer, hard only).
    $crunch = $this->crunch;
    // Step 4.4: Sunday and Monday, a quiet line to the Sunday review (board mode; it folds away with the board).
    $reviewOffer = $this->reviewOffer;
    // Step 4.6 / 4.8: „Wichtig" (R5). The marked To-dos and Tasks stand in a band at the very top; the tab holds every marked task.
    $important = $this->important;
    $showReminder = $band->isNotEmpty();
@endphp
<div
    id="content"
    class="shell min-h-dvh"
    data-shell-mode="{{ $mode }}"
    data-reset="{{ $reset->value }}"
    x-data="shellFold"
    @if ($open) x-init="peek = @js($open)" @endif
    @keydown.escape.window="peek = null"
    @shell-peek-close.window="peek = null"
>
    @if ($notice)
        <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status">
            <p class="pointer-events-auto flex max-w-md items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card">
                {{ $notice }}
                <button type="button" wire:click="$set('notice', null)" class="rounded-full p-1 opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="OK">
                    @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </p>
        </div>
    @endif

    {{-- The session card as the browser draws it before the server has answered Start (C1). --}}
    @include('shell.session-ghost')

    {{-- ════════════════════ Werkbank (desktop, ≥ 720 px) ════════════════════ --}}
    <div class="relative hidden min-h-dvh flex-col wb:flex" data-layout-root="werkbank" @if ($covered) inert aria-hidden="true" @endif>
        <section data-region="board" wire:key="werkbank-board" class="flex min-h-dvh flex-col" @if ($inSession) inert aria-hidden="true" @endif>
            <div class="fold-away sticky top-0 z-30">
                @include('shell.topbar', ['battery' => $suggestion->battery, 'active' => 'board'])
            </div>

            <div class="grid items-start gap-5 px-6 pt-4 lg:grid-cols-[minmax(0,1fr)_400px]">
                <div class="fold-away flex min-w-0 flex-col gap-3">
                    @include('shell.timeline', ['timeline' => $timeline, 'layout' => 'werkbank', 'mode' => 'board', 'idea' => $idea])
                    @include('shell.week-links', ['layout' => 'werkbank', 'mode' => 'board', 'keys' => ['move_event', 'pause_days'], 'class' => '-mt-1'])
                    @include('shell.crunch-line', ['layout' => 'werkbank', 'crunch' => $crunch])
                    @include('shell.review-line', ['offer' => $reviewOffer, 'class' => 'self-start'])
                    @if ($done->isNotEmpty())
                        <p class="fold-return flex items-center gap-2 self-start rounded-card bg-good-soft px-3 py-1.5 text-sm font-semibold text-good">
                            @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                            <span class="line-through decoration-1 opacity-90">{{ $done->first()->title }}</span>
                            <span class="font-mono text-xs no-underline">{{ trans_choice('shell.done_tonight', $done->count(), ['count' => $done->count()]) }}</span>
                        </p>
                    @endif
                </div>
                <div class="flex min-w-0 flex-col gap-3">
                    <div class="fold-card fold-return min-w-0">
                        @if ($showIdea)
                            @include('shell.idea', ['layout' => 'werkbank', 'idea' => $idea, 'free' => $timeline['free'], 'freePlain' => $freePlain])
                        @else
                            @include('shell.suggestion', ['layout' => 'werkbank', 'suggestion' => $suggestion, 'counts' => $counts, 'idea' => $idea])
                        @endif
                    </div>
                    @if ($showReminder)
                        @include('shell.important', ['layout' => 'werkbank', 'tasks' => $band, 'suggestedId' => $suggestedId])
                    @endif
                </div>
            </div>

            {{-- Four columns from 1024 px; two between 720 and 1024, so titles never break mid-word. --}}
            <div class="grid flex-1 grid-cols-2 items-start gap-3 px-6 pb-8 pt-4 lg:grid-cols-4">
                @foreach (array_values($columns) as $i => $list)
                    @php($colClass = 'fold-col flex min-w-0 flex-col gap-2 rounded-panel p-2.5')
                    @php($colStyle = '--i: '.$i.'; border: var(--t-bw) solid var(--t-line); background: color-mix(in srgb, var(--t-ink) 4%, var(--t-bg))')
                    @if ($list === 'later')
                        <x-shell.action key="browse_later" layout="werkbank" mode="board" as="section" class="{{ $colClass }}" style="{{ $colStyle }}" aria-labelledby="col-later">
                            @include('shell.column', ['list' => $list])
                        </x-shell.action>
                    @else
                        <section class="{{ $colClass }}" style="{{ $colStyle }}" aria-labelledby="col-{{ $list }}">
                            @include('shell.column', ['list' => $list])
                        </section>
                    @endif
                @endforeach
            </div>
        </section>

        @if ($inSession)
            <section data-region="session" wire:key="werkbank-session" class="relative flex min-h-dvh">
                {{-- The rail: the folded board (F6: one tap away). --}}
                <aside class="fold-rail sticky top-0 flex h-dvh w-[92px] shrink-0 flex-col items-center gap-4 bg-surface px-2 py-5" style="border-right: var(--t-bw) solid var(--t-line)">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-faint">{{ __('shell.session.board') }}</span>
                    <x-shell.action key="capture" layout="werkbank" mode="session" @click="$store.capture.open()" :title="__('capture.line.label').' (C)'" :aria-label="__('capture.line.label')"
                        class="grid size-10 place-items-center rounded-full border border-line text-ink-soft transition hover:border-accent hover:text-accent-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                        @include('shell.icon', ['name' => 'plus', 'class' => 'size-5'])
                    </x-shell.action>
                    @foreach (array_values($columns) as $i => $list)
                        @php($railClass = 'fold-rail-count flex w-full flex-col items-center gap-0.5 rounded-card py-2 text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                        @if ($list === 'later')
                            <x-shell.action key="browse_later" layout="werkbank" mode="session" @click="peek = 'later'" class="{{ $railClass }}" style="--i: {{ $i }}">
                                @include('shell.icon', ['name' => $list, 'class' => 'size-5'])
                                <b class="font-mono text-[15px] font-semibold text-ink">{{ $counts[$list] }}</b>
                                <small class="text-[10.5px]">{{ __('shell.lists.'.$list) }}</small>
                            </x-shell.action>
                        @else
                            <button type="button" @click="peek = '{{ $list }}'" class="{{ $railClass }}" style="--i: {{ $i }}">
                                @include('shell.icon', ['name' => $list, 'class' => 'size-5'])
                                <b class="font-mono text-[15px] font-semibold text-ink">{{ $counts[$list] }}</b>
                                <small class="text-[10.5px]">{{ __('shell.lists.'.$list) }}</small>
                            </button>
                        @endif
                    @endforeach
                    {{-- Step 4.6: while the board is folded, what is marked „Wichtig" stays one tap away. --}}
                    @if ($important->isNotEmpty())
                        <button type="button" data-important-rail @click="peek = 'important'" style="--i: 4"
                            class="fold-rail-count flex w-full flex-col items-center gap-0.5 rounded-card py-2 text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            @include('shell.icon', ['name' => 'flag', 'class' => 'size-5 text-warn', 'filled' => true])
                            <b class="font-mono text-[15px] font-semibold text-ink">{{ $important->count() }}</b>
                            <small class="text-[10.5px]">{{ __('shell.important.label') }}</small>
                        </button>
                    @endif
                    <x-shell.action key="projects" layout="werkbank" mode="session" as="a" :href="route('shell.projects')" wire:navigate
                        class="flex w-full flex-col items-center gap-0.5 rounded-card py-2 text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                        @include('shell.icon', ['name' => 'project', 'class' => 'size-5'])
                        <small class="text-[10.5px]">{{ __('projects.nav') }}</small>
                    </x-shell.action>
                    @if ($crunch->isHard())
                        <x-shell.action key="crunch" layout="werkbank" mode="session" wire:click="$dispatch('crunch-open')"
                            class="flex w-full flex-col items-center gap-1 rounded-card py-2 text-[10.5px] font-semibold text-warn transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="size-2 rounded-full bg-current" aria-hidden="true"></span>{{ __('shell.crunch.dot') }}
                        </x-shell.action>
                    @endif
                    <button type="button" @click="peek = peek ? null : 'todos'" class="mt-auto text-center text-[11px] font-semibold leading-tight text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                        x-text="peek ? @js(__('shell.session.close_board')) : @js(__('shell.session.open_board'))">{{ __('shell.session.open_board') }}</button>
                </aside>

                <main class="flex min-w-0 flex-1 flex-col gap-5 px-8 py-5">
                    <div class="fold-arrive mx-auto flex w-full max-w-[680px] items-start gap-4">
                        <div class="min-w-0 flex-1">
                            @include('shell.timeline', ['timeline' => $timeline, 'compact' => true, 'layout' => 'werkbank', 'mode' => 'session', 'idea' => $idea])
                            @include('shell.week-links', ['layout' => 'werkbank', 'mode' => 'session', 'keys' => ['week_plan', 'move_event', 'pause_days'], 'class' => 'mt-1'])
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @include('shell.reset-button', ['layout' => 'werkbank', 'mode' => 'session', 'label' => true])
                            @include('shell.battery', ['layout' => 'werkbank', 'mode' => 'session', 'battery' => $suggestion->battery])
                        </div>
                    </div>
                    <div class="flex flex-1 items-center justify-center pb-10">
                        @include('shell.session-card', ['layout' => 'werkbank', 'session' => $session, 'task' => $this->sessionTask, 'next' => $this->nextInSession, 'timer' => $this->timer])
                    </div>
                </main>

                {{-- Peek panel: the board's lists next to the rail, without leaving the session. --}}
                <div x-show="peek" x-cloak x-transition.opacity.duration.150ms class="absolute inset-y-0 left-[92px] z-30 flex w-[min(420px,calc(100vw-92px))] flex-col bg-surface pt-4 shadow-card" style="border-right: var(--t-bw) solid var(--t-line)" @click.outside="peek = null">
                    <div class="flex items-center justify-between px-4 pb-3">
                        <h2 class="font-display text-lg text-ink" style="font-weight: var(--t-dw)">{{ __('shell.session.peek') }}</h2>
                        <button type="button" @click="peek = null" class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.session.close_board') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-5'])</button>
                    </div>
                    @include('shell.lists-panel', ['layout' => 'werkbank', 'mode' => 'session', 'lists' => $lists, 'entries' => $entries, 'resting' => $resting, 'todoSession' => $todoSession, 'counts' => $counts, 'important' => $important, 'suggestedId' => null, 'locked' => $locked])
                </div>
            </section>
        @endif
    </div>

    {{-- ════════════════════ Stapel (mobile, < 720 px) ════════════════════ --}}
    <div class="relative flex min-h-dvh flex-col wb:hidden" data-layout-root="stapel" style="background: color-mix(in srgb, var(--t-warn) 5%, var(--t-bg))" @if ($covered) inert aria-hidden="true" @endif>
        <section data-region="board" wire:key="stapel-board" class="flex min-h-dvh flex-col pb-[calc(88px+env(safe-area-inset-bottom))]" @if ($inSession) inert aria-hidden="true" @endif>
            <header class="fold-away sticky top-0 z-20 flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))]" style="background: color-mix(in srgb, var(--t-warn) 5%, var(--t-bg))">
                <a href="{{ route('shell.board') }}" class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)" wire:navigate>nothing-to-do</a>
                <div class="ml-auto flex items-center gap-2">
                    @include('shell.reset-button', ['layout' => 'stapel', 'mode' => 'board'])
                    @include('shell.battery', ['layout' => 'stapel', 'mode' => 'board', 'battery' => $suggestion->battery])
                </div>
                <a href="{{ route('shell.settings') }}" wire:navigate class="grid size-9 place-items-center rounded-full border border-line bg-surface text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.nav.settings') }}">@include('shell.icon', ['name' => 'gear', 'class' => 'size-5'])</a>
            </header>

            <div class="flex flex-col gap-5 px-4 pt-2">
                {{-- Tonight tray: the suggestion on top of a short stack. --}}
                <section class="fold-tray flex flex-col gap-2 rounded-panel p-3" style="background: color-mix(in srgb, var(--t-ink) 5%, var(--t-surface)); border: var(--t-bw) solid var(--t-line)">
                    <p class="fold-away font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.tonight') }}</p>
                    <div class="relative isolate mt-2">
                        @if (($suggestion->hasPick() && $suggestion->alternatives > 0) || ($showIdea && $idea->alternatives > 0))
                            <span class="fold-away absolute inset-x-3 -top-1.5 -z-10 h-10 rounded-panel bg-surface" style="border: var(--t-bw) solid var(--t-line)" aria-hidden="true"></span>
                        @endif
                        <div class="fold-card fold-return">
                            @if ($showIdea)
                                @include('shell.idea', ['layout' => 'stapel', 'idea' => $idea, 'free' => $timeline['free'], 'freePlain' => $freePlain])
                            @else
                                @include('shell.suggestion', ['layout' => 'stapel', 'suggestion' => $suggestion, 'counts' => $counts, 'idea' => $idea])
                            @endif
                        </div>
                    </div>
                    @include('shell.free-line', ['layout' => 'stapel', 'mode' => 'board', 'idea' => $idea, 'free' => $timeline['free'], 'class' => 'fold-away'])
                    @include('shell.week-links', ['layout' => 'stapel', 'mode' => 'board', 'keys' => ['move_event', 'pause_days'], 'class' => 'fold-away'])
                    @include('shell.crunch-line', ['layout' => 'stapel', 'crunch' => $crunch, 'class' => 'fold-away'])
                    @include('shell.review-line', ['offer' => $reviewOffer, 'class' => 'fold-away'])
                    @if ($showReminder)
                        @include('shell.important', ['layout' => 'stapel', 'tasks' => $band, 'suggestedId' => $suggestedId])
                    @endif
                </section>

                {{-- Piles, 2 × 2: each shows its top card and its count; tap opens the list. --}}
                <div class="grid grid-cols-2 gap-x-3.5 gap-y-5">
                    @foreach (array_values($columns) as $i => $list)
                        <div class="fold-pile" style="--i: {{ $i }}; --row: {{ intdiv($i, 2) }}">
                            @include('shell.pile', ['list' => $list, 'top' => $this->topOf($list), 'count' => $counts[$list], 'mode' => 'board'])
                        </div>
                    @endforeach
                    @if ($done->isNotEmpty())
                        <div class="fold-pile fold-return" style="--i: 4; --row: 2">
                            @include('shell.pile', ['list' => 'done', 'top' => $done->first(), 'count' => $done->count(), 'mode' => 'board'])
                        </div>
                    @endif
                </div>
            </div>

            <x-shell.action key="capture" layout="stapel" mode="board" @click="$store.capture.sheetOpen = true" :aria-label="__('capture.line.label')"
                class="fold-away fixed right-4 bottom-[calc(84px+env(safe-area-inset-bottom))] z-30 grid size-14 place-items-center rounded-full bg-accent text-accent-fg shadow-card transition active:scale-95 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper">
                @include('shell.icon', ['name' => 'plus', 'class' => 'size-6'])
            </x-shell.action>

            <nav class="fold-tabs fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-2 pt-2 pb-[max(14px,env(safe-area-inset-bottom))]" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
                @php($tab = 'flex min-w-14 flex-col items-center gap-0.5 rounded-card px-1.5 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                <button type="button" @click="peek = null; window.scrollTo({ top: 0, behavior: 'smooth' })" class="{{ $tab }} text-accent-ink" aria-current="page">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></button>
                <x-shell.action key="week_plan" layout="stapel" mode="board" as="a" :href="route('shell.week')" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></x-shell.action>
                <x-shell.action key="browse_later" layout="stapel" mode="board" as="a" :href="route('shell.ideas')" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></x-shell.action>
                <x-shell.action key="projects" layout="stapel" mode="board" as="a" :href="route('shell.projects')" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'project'])<span>{{ __('projects.nav') }}</span></x-shell.action>
                <button type="button" @click="peek = 'inbox'" class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></button>
            </nav>
        </section>

        @if ($inSession)
            <section data-region="session" wire:key="stapel-session" class="flex min-h-dvh flex-col">
                <header class="fold-arrive flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))]">
                    <p class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.session.running') }}</p>
                    <div class="ml-auto flex items-center gap-2">
                        @include('shell.reset-button', ['layout' => 'stapel', 'mode' => 'session'])
                        @include('shell.battery', ['layout' => 'stapel', 'mode' => 'session', 'battery' => $suggestion->battery])
                    </div>
                </header>
                <div class="flex flex-1 flex-col items-center justify-center gap-3 px-4 pb-6">
                    @include('shell.session-card', ['layout' => 'stapel', 'session' => $session, 'task' => $this->sessionTask, 'next' => $this->nextInSession, 'timer' => $this->timer])
                    @include('shell.free-line', ['layout' => 'stapel', 'mode' => 'session', 'idea' => $idea, 'free' => $timeline['free'], 'class' => 'justify-center text-center'])
                    @include('shell.week-links', ['layout' => 'stapel', 'mode' => 'session', 'keys' => ['week_plan', 'move_event', 'pause_days'], 'class' => 'justify-center'])
                </div>
                {{-- The drawer: the piles slid away, one tap to peek. --}}
                <nav class="fold-drawer relative flex flex-wrap items-center justify-center gap-x-1 gap-y-1 bg-surface px-3 pt-4 pb-[max(16px,env(safe-area-inset-bottom))] shadow-card" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.session.drawer') }}">
                    <span class="absolute left-1/2 top-1.5 h-1 w-11 -translate-x-1/2 rounded-full bg-line" aria-hidden="true"></span>
                    <x-shell.action key="capture" layout="stapel" mode="session" @click="$store.capture.sheetOpen = true" :aria-label="__('capture.line.label')"
                        class="grid size-9 place-items-center rounded-full bg-accent text-accent-fg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                        @include('shell.icon', ['name' => 'plus', 'class' => 'size-5'])
                    </x-shell.action>
                    @if ($crunch->isHard())
                        <x-shell.action key="crunch" layout="stapel" mode="session" wire:click="$dispatch('crunch-open')"
                            class="flex items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-warn focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="size-2 rounded-full bg-current" aria-hidden="true"></span>{{ __('shell.crunch.dot') }}
                        </x-shell.action>
                    @endif
                    @foreach ($columns as $list)
                        @php($drawerClass = 'rounded-full px-3 py-2 text-sm text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                        @if ($list === 'later')
                            <x-shell.action key="browse_later" layout="stapel" mode="session" @click="peek = 'later'" class="{{ $drawerClass }}">{{ __('shell.lists.later') }} <b class="font-mono text-ink">{{ $counts[$list] }}</b></x-shell.action>
                        @else
                            <button type="button" @click="peek = '{{ $list }}'" class="{{ $drawerClass }}">{{ __('shell.lists.'.$list) }} <b class="font-mono text-ink">{{ $counts[$list] }}</b></button>
                        @endif
                    @endforeach
                    {{-- Step 4.6: while the board is folded, what is marked „Wichtig" stays one tap away. --}}
                    @if ($important->isNotEmpty())
                        <button type="button" data-important-drawer @click="peek = 'important'" class="rounded-full px-3 py-2 text-sm text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="inline-flex items-center gap-1">@include('shell.icon', ['name' => 'flag', 'class' => 'size-3.5 text-warn', 'filled' => true]){{ __('shell.important.label') }}</span> <b class="font-mono text-ink">{{ $important->count() }}</b>
                        </button>
                    @endif
                    <x-shell.action key="projects" layout="stapel" mode="session" as="a" :href="route('shell.projects')" wire:navigate class="rounded-full px-3 py-2 text-sm text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('projects.nav') }}</x-shell.action>
                </nav>
            </section>
        @endif

        {{-- The list sheet: piles, bottom tabs and the drawer open it. --}}
        <div x-show="peek" x-cloak class="fixed inset-0 z-40 flex flex-col justify-end" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/40" @click="peek = null" x-show="peek" x-transition.opacity></div>
            <div class="relative flex max-h-[82dvh] flex-col rounded-t-panel bg-surface pt-2 pb-[env(safe-area-inset-bottom)] shadow-card"
                x-show="peek" x-transition:enter="transition duration-200 ease-tactile" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                <div class="flex items-center justify-between px-4 pb-2">
                    <span class="h-1 w-11 rounded-full bg-line" aria-hidden="true"></span>
                    <button type="button" @click="peek = null" class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="OK">@include('shell.icon', ['name' => 'close', 'class' => 'size-5'])</button>
                </div>
                @include('shell.lists-panel', ['layout' => 'stapel', 'mode' => $mode, 'lists' => $lists, 'entries' => $entries, 'resting' => $resting, 'todoSession' => $todoSession, 'counts' => $counts, 'important' => $important, 'suggestedId' => $mode === 'board' ? $suggestedId : null, 'locked' => $locked])
            </div>
        </div>
    </div>

    {{-- Step 3.2: the reset and the battery question, a calm screen over both layouts. --}}
    @if ($covered)
        @include('shell.reset', ['stage' => $reset, 'timer' => $this->resetTimer])
    @endif

    {{-- The daily sort card, the same over both layouts. --}}
    {{-- A stable key: the board re-renders after every sort, the card must keep its round. --}}
    <livewire:shell.inbox-sort :key="'inbox-sort'" />
    <livewire:shell.item-editor :key="'item-editor'" />

    {{-- Step 4.2: the crunch screen, the same over both layouts (panel on the right / sheet from the bottom). --}}
    <livewire:shell.crunch :key="'crunch'" />
</div>
