{{-- "Ideen" (step 3.4): the browse view of Later as cards, both layouts in one render (Werkbank top bar from 720 px,
     Stapel header and bottom tabs below). Empty state, loading states on every button, calm notices. --}}
@php
    $ideas = $this->ideas;
    $shown = $ideas->take(\App\Livewire\Shell\Ideas::LIMIT);
    $offeredId = $this->offeredId;
    $locked = $this->locked;
    $btn = 'inline-flex min-h-11 wb:min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60';
@endphp
<div id="content" class="min-h-dvh pb-[calc(88px+env(safe-area-inset-bottom))] wb:pb-0">
    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'ideas'])</div>
    <header class="sticky top-0 z-20 flex items-center gap-2 bg-paper px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <a href="{{ route('shell.board') }}" wire:navigate class="grid size-11 wb:size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.ideas.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
        <span class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</span>
    </header>

    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status">
            @if ($notice)
            <p class="pointer-events-auto flex max-w-md items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card">
                <span class="min-w-0 wrap-break-word">{{ $notice }}</span>
                @if ($undoId)
                    <button type="button" wire:click="undoDone" wire:loading.attr="disabled" class="shrink-0 font-semibold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('shell.ideas.undo') }}</button>
                @endif
                <button type="button" wire:click="dismiss" class="shrink-0 -my-2 -mr-2.5 grid size-11 place-items-center rounded-full opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="{{ __('shell.close') }}">
                    @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </p>
            @endif
    </div>

    <main class="mx-auto flex max-w-6xl flex-col gap-5 px-4 pt-4 wb:px-6 wb:py-8">
        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
            <div class="min-w-0">
                <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ __('shell.ideas.title') }}</h1>
                <p class="mt-1 max-w-[62ch] text-sm text-ink-soft">{{ __('shell.ideas.intro') }}</p>
            </div>
            @if ($ideas->isNotEmpty())
                <p class="font-mono text-xs text-ink-faint">{{ trans_choice('shell.ideas.count', $ideas->count(), ['count' => $ideas->count()]) }}</p>
            @endif
        </div>

        @if ($ideas->isEmpty())
            <section class="flex flex-col items-start gap-3 rounded-panel border border-dashed border-line p-6 wb:p-8" data-ideas-empty>
                <span class="grid size-11 place-items-center rounded-full text-warn" style="background: color-mix(in srgb, var(--t-warn) 12%, var(--t-surface))">@include('shell.icon', ['name' => 'later', 'class' => 'size-5'])</span>
                <h2 class="font-display text-xl text-ink" style="font-weight: var(--t-dw)">{{ __('shell.ideas.empty_title') }}</h2>
                <p class="max-w-[58ch] text-sm text-ink-soft">{{ __('shell.ideas.empty_body') }}</p>
                <button type="button" x-data @click="window.matchMedia('(min-width: 720px)').matches ? $store.capture.open() : ($store.capture.sheetOpen = true)"
                    class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">
                    @include('shell.icon', ['name' => 'plus', 'class' => 'size-4'])
                    {{ __('shell.ideas.empty_capture') }}
                </button>
            </section>
        @else
            <ul class="grid grid-cols-1 gap-3 wb:grid-cols-2 lg:grid-cols-3" data-ideas>
                @foreach ($shown as $task)
                    @php($tonight = $task->id === $offeredId)
                    <li wire:key="idea-{{ $task->id }}" data-idea-card
                        class="flex min-w-0 flex-col gap-2 rounded-panel bg-surface p-4"
                        style="border: var(--t-bw) {{ $tonight ? 'dashed color-mix(in srgb, var(--t-warn) 60%, var(--t-line))' : 'solid var(--t-line)' }}{{ $tonight ? '; background: color-mix(in srgb, var(--t-warn) 6%, var(--t-surface))' : '' }}">
                        @if ($tonight)
                            <span class="font-mono text-[10.5px] font-medium uppercase tracking-widest text-warn">{{ __('shell.ideas.tonight') }}</span>
                        @endif
                        <div class="flex items-start gap-1">
                            <h2 class="line-clamp-4 min-w-0 flex-1 font-display text-[16px] leading-snug wrap-break-word text-ink" style="font-weight: var(--t-dw)" title="{{ $task->title }}">{{ $task->title }}</h2>
                            {{-- Step 4.9: edit, move to another list, delete (the same ⋯ menu as the board rows). --}}
                            @include('shell.item-menu', ['task' => $task, 'trigger' => '-mr-2 -mt-1.5 grid size-11 wb:size-9 shrink-0 cursor-pointer place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent'])
                        </div>
                        @if ($task->area || $task->size)
                            <p class="flex flex-wrap items-center gap-x-1.5 text-xs text-ink-soft">
                                @if ($task->area)
                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                        <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
                                    </span>
                                @endif
                                @if ($task->area && $task->size)<span aria-hidden="true" class="text-ink-faint">·</span>@endif
                                @if ($task->size)<span>{{ \App\Shell\TaskFace::size($task) }}</span>@endif
                            </p>
                        @endif
                        <p class="text-sm wrap-break-word text-ink-soft">
                            <span class="mr-1 font-mono text-[10px] font-medium uppercase tracking-wider text-warn">{{ __('shell.ideas.start_with') }}</span>@if ($task->first_step){{ $task->first_step }}@else<span class="text-ink-faint">{{ __('shell.ideas.no_start') }}</span>@endif
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-1">
                            <button type="button" wire:click="doNow({{ $task->id }})" wire:loading.attr="disabled" wire:target="doNow({{ $task->id }})"
                                @disabled($locked) @if ($locked) title="{{ __('shell.ideas.not_now') }}" @endif
                                class="{{ $btn }} flex-1 basis-28 bg-accent text-accent-fg hover:brightness-110">
                                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                                <span wire:loading.remove wire:target="doNow({{ $task->id }})">{{ __('shell.ideas.do_now') }}</span>
                                <span wire:loading wire:target="doNow({{ $task->id }})">{{ __('shell.ideas.starting') }}</span>
                            </button>
                            <button type="button" wire:click="done({{ $task->id }})" wire:loading.attr="disabled" wire:target="done({{ $task->id }})"
                                class="{{ $btn }} border border-line bg-surface text-ink-soft hover:bg-paper hover:text-ink" aria-label="{{ __('shell.ideas.done') }}: {{ $task->title }}">
                                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                                {{ __('shell.ideas.done') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($ideas->count() > $shown->count())
                <p class="text-sm text-ink-faint">{{ __('shell.ideas.more', ['count' => $ideas->count() - $shown->count()]) }}</p>
            @endif
        @endif
    </main>

    <livewire:shell.item-editor :key="'item-editor'" />

    {{-- Stapel: the bottom tabs, "Später" is this page. Capture: + above them (R3). --}}
    <button type="button" x-data @click="$store.capture.sheetOpen = true" aria-label="{{ __('capture.line.label') }}"
        class="fixed right-4 bottom-[calc(84px+env(safe-area-inset-bottom))] z-30 grid size-14 place-items-center rounded-full bg-accent text-accent-fg shadow-card transition active:scale-95 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper wb:hidden">
        @include('shell.icon', ['name' => 'plus', 'class' => 'size-6'])
    </button>
    <nav class="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-2 pt-2 pb-[max(14px,env(safe-area-inset-bottom))] wb:hidden" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
        @php($tab = 'flex min-w-14 flex-col items-center gap-0.5 rounded-card px-1.5 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
        <a href="{{ route('shell.board') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></a>
        <a href="{{ route('shell.week') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></a>
        <a href="{{ route('shell.ideas') }}" wire:navigate class="{{ $tab }} text-accent-ink" aria-current="page">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></a>
        <a href="{{ route('shell.projects') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'project'])<span>{{ __('projects.nav') }}</span></a>
        <a href="{{ route('shell.board', ['open' => 'inbox']) }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></a>
    </nav>
</div>
