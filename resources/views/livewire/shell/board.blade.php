{{--
    The new app (refactor step 2.2). Two layouts in one component, chosen by CSS at 720 px:
    Werkbank (desktop) and Stapel (mobile). Inside each, a board region (idle) or a session
    region (focus). data-shell-mode on the root is the hook for step 3.1's signature moment
    "The board folds away" (CONVENTIONS 10): keep board and session as sibling regions.
--}}
@php
    $suggestion = $this->suggestion;
    $lists = $this->lists;
    $counts = $this->counts;
    $suggestedId = $suggestion->hasPick() ? $suggestion->task?->id : null;
    $session = $this->session;
    $locked = $session->phase === \App\Planning\Session\Phase::Work;
    $timeline = $this->timeline;
    $done = $this->doneToday;
    $columns = ['inbox' => 'inbox', 'todos' => 'todos', 'tasks' => 'tasks', 'later' => 'later'];
@endphp
<div
    id="content"
    class="shell min-h-dvh"
    data-shell-mode="{{ $mode }}"
    x-data="{ peek: null }"
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

    {{-- ════════════════════ Werkbank (desktop, ≥ 720 px) ════════════════════ --}}
    <div class="hidden min-h-dvh flex-col wb:flex" data-layout-root="werkbank">
        @if ($mode === 'board')
            <section data-region="board" class="flex min-h-dvh flex-col">
                @include('shell.topbar', ['battery' => $suggestion->battery])

                <div class="grid items-start gap-5 px-6 pt-4 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <div class="flex min-w-0 flex-col gap-3">
                        @include('shell.timeline', ['timeline' => $timeline])
                        @if ($done->isNotEmpty())
                            <p class="flex items-center gap-2 self-start rounded-card bg-good-soft px-3 py-1.5 text-sm font-semibold text-good">
                                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                                <span class="line-through decoration-1 opacity-90">{{ $done->first()->title }}</span>
                                <span class="font-mono text-xs no-underline">{{ trans_choice('shell.done_tonight', $done->count(), ['count' => $done->count()]) }}</span>
                            </p>
                        @endif
                    </div>
                    @include('shell.suggestion', ['layout' => 'werkbank', 'suggestion' => $suggestion, 'counts' => $counts])
                </div>

                {{-- Four columns from 1024 px; two between 720 and 1024, so titles never break mid-word. --}}
                <div class="grid flex-1 grid-cols-2 items-start gap-3 px-6 pb-8 pt-4 lg:grid-cols-4">
                    @foreach ($columns as $list)
                        @php($colClass = 'flex min-w-0 flex-col gap-2 rounded-panel p-2.5')
                        @php($colStyle = 'border: var(--t-bw) solid var(--t-line); background: color-mix(in srgb, var(--t-ink) 4%, var(--t-bg))')
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
        @else
            <section data-region="session" class="relative flex min-h-dvh">
                {{-- The rail: the folded board (F6: one tap away). --}}
                <aside class="sticky top-0 flex h-dvh w-[92px] shrink-0 flex-col items-center gap-4 bg-surface px-2 py-5" style="border-right: var(--t-bw) solid var(--t-line)">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-faint">{{ __('shell.session.board') }}</span>
                    @foreach ($columns as $list)
                        @php($railClass = 'flex w-full flex-col items-center gap-0.5 rounded-card py-2 text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                        @if ($list === 'later')
                            <x-shell.action key="browse_later" layout="werkbank" mode="session" @click="peek = 'later'" class="{{ $railClass }}">
                                @include('shell.icon', ['name' => $list, 'class' => 'size-5'])
                                <b class="font-mono text-[15px] font-semibold text-ink">{{ $counts[$list] }}</b>
                                <small class="text-[10.5px]">{{ __('shell.lists.'.$list) }}</small>
                            </x-shell.action>
                        @else
                            <button type="button" @click="peek = '{{ $list }}'" class="{{ $railClass }}">
                                @include('shell.icon', ['name' => $list, 'class' => 'size-5'])
                                <b class="font-mono text-[15px] font-semibold text-ink">{{ $counts[$list] }}</b>
                                <small class="text-[10.5px]">{{ __('shell.lists.'.$list) }}</small>
                            </button>
                        @endif
                    @endforeach
                    <button type="button" @click="peek = peek ? null : 'todos'" class="mt-auto text-center text-[11px] font-semibold leading-tight text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                        x-text="peek ? @js(__('shell.session.close_board')) : @js(__('shell.session.open_board'))">{{ __('shell.session.open_board') }}</button>
                </aside>

                <main class="flex min-w-0 flex-1 flex-col gap-5 px-8 py-5">
                    <div class="mx-auto flex w-full max-w-[680px] items-start gap-4">
                        <div class="min-w-0 flex-1">@include('shell.timeline', ['timeline' => $timeline, 'compact' => true])</div>
                        @include('shell.battery', ['layout' => 'werkbank', 'mode' => 'session', 'battery' => $suggestion->battery])
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
                    @include('shell.lists-panel', ['layout' => 'werkbank', 'mode' => 'session', 'lists' => $lists, 'counts' => $counts, 'suggestedId' => null, 'locked' => $locked])
                </div>
            </section>
        @endif
    </div>

    {{-- ════════════════════ Stapel (mobile, < 720 px) ════════════════════ --}}
    <div class="flex min-h-dvh flex-col wb:hidden" data-layout-root="stapel" style="background: color-mix(in srgb, var(--t-warn) 5%, var(--t-bg))">
        @if ($mode === 'board')
            <section data-region="board" class="flex min-h-dvh flex-col pb-[calc(88px+env(safe-area-inset-bottom))]">
                <header class="sticky top-0 z-20 flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))]" style="background: color-mix(in srgb, var(--t-warn) 5%, var(--t-bg))">
                    <a href="{{ route('shell.board') }}" class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)" wire:navigate>nothing-to-do</a>
                    <div class="ml-auto">@include('shell.battery', ['layout' => 'stapel', 'mode' => 'board', 'battery' => $suggestion->battery])</div>
                    <a href="{{ route('shell.settings') }}" wire:navigate class="grid size-9 place-items-center rounded-full border border-line bg-surface text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.nav.settings') }}">@include('shell.icon', ['name' => 'gear', 'class' => 'size-5'])</a>
                </header>

                <div class="flex flex-col gap-5 px-4 pt-2">
                    {{-- Tonight tray: the suggestion on top of a short stack. --}}
                    <section class="flex flex-col gap-2 rounded-panel p-3" style="background: color-mix(in srgb, var(--t-ink) 5%, var(--t-surface)); border: var(--t-bw) solid var(--t-line)">
                        <p class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.tonight') }}</p>
                        <div class="relative isolate mt-2">
                            @if ($suggestion->hasPick() && $suggestion->alternatives > 0)
                                <span class="absolute inset-x-3 -top-1.5 -z-10 h-10 rounded-panel bg-surface" style="border: var(--t-bw) solid var(--t-line)" aria-hidden="true"></span>
                            @endif
                            @include('shell.suggestion', ['layout' => 'stapel', 'suggestion' => $suggestion, 'counts' => $counts])
                        </div>
                        <p class="text-sm text-ink-soft">{{ $timeline['free'] }}</p>
                    </section>

                    {{-- Piles, 2 × 2: each shows its top card and its count; tap opens the list. --}}
                    <div class="grid grid-cols-2 gap-x-3.5 gap-y-5">
                        @foreach ($columns as $list)
                            @include('shell.pile', ['list' => $list, 'top' => $lists[$list]->first(), 'count' => $counts[$list], 'mode' => 'board'])
                        @endforeach
                        @if ($done->isNotEmpty())
                            @include('shell.pile', ['list' => 'done', 'top' => $done->first(), 'count' => $done->count(), 'mode' => 'board'])
                        @endif
                    </div>
                </div>

                <nav class="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-2 pt-2 pb-[max(14px,env(safe-area-inset-bottom))]" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
                    @php($tab = 'flex min-w-16 flex-col items-center gap-0.5 rounded-card px-2 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                    <button type="button" @click="peek = null; window.scrollTo({ top: 0, behavior: 'smooth' })" class="{{ $tab }} text-accent-ink" aria-current="page">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></button>
                    <a href="{{ route('weekplan') }}" class="{{ $tab }}">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></a>
                    <x-shell.action key="browse_later" layout="stapel" mode="board" @click="peek = 'later'" class="{{ $tab }}">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></x-shell.action>
                    <button type="button" @click="peek = 'inbox'" class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></button>
                </nav>
            </section>
        @else
            <section data-region="session" class="flex min-h-dvh flex-col">
                <header class="flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))]">
                    <p class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.session.running') }}</p>
                    <div class="ml-auto">@include('shell.battery', ['layout' => 'stapel', 'mode' => 'session', 'battery' => $suggestion->battery])</div>
                </header>
                <div class="flex flex-1 flex-col items-center justify-center gap-3 px-4 pb-6">
                    @include('shell.session-card', ['layout' => 'stapel', 'session' => $session, 'task' => $this->sessionTask, 'next' => $this->nextInSession, 'timer' => $this->timer])
                </div>
                {{-- The drawer: the piles slid away, one tap to peek. --}}
                <nav class="relative flex flex-wrap items-center justify-center gap-x-1 gap-y-1 bg-surface px-3 pt-4 pb-[max(16px,env(safe-area-inset-bottom))] shadow-card" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.session.drawer') }}">
                    <span class="absolute left-1/2 top-1.5 h-1 w-11 -translate-x-1/2 rounded-full bg-line" aria-hidden="true"></span>
                    @foreach ($columns as $list)
                        @php($drawerClass = 'rounded-full px-3 py-2 text-sm text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
                        @if ($list === 'later')
                            <x-shell.action key="browse_later" layout="stapel" mode="session" @click="peek = 'later'" class="{{ $drawerClass }}">{{ __('shell.lists.later') }} <b class="font-mono text-ink">{{ $counts[$list] }}</b></x-shell.action>
                        @else
                            <button type="button" @click="peek = '{{ $list }}'" class="{{ $drawerClass }}">{{ __('shell.lists.'.$list) }} <b class="font-mono text-ink">{{ $counts[$list] }}</b></button>
                        @endif
                    @endforeach
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
                @include('shell.lists-panel', ['layout' => 'stapel', 'mode' => $mode, 'lists' => $lists, 'counts' => $counts, 'suggestedId' => $mode === 'board' ? $suggestedId : null, 'locked' => $locked])
            </div>
        </div>
    </div>
</div>
