{{-- Step 4.3: the frame of the Projekte pages. Werkbank top bar from 720 px; below it the Stapel
     header with a back link, and (as $part = 'tabs') the bottom tabs with "Projekte" active and
     the + for capture. @param string $part ('head'|'tabs'), ?string $back (url), ?string $backLabel --}}
@if ($part === 'head')
    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'projects'])</div>
    <header class="sticky top-0 z-20 flex items-center gap-2 bg-paper px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <a href="{{ $back ?? route('shell.board') }}" wire:navigate class="grid size-11 wb:size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ $backLabel ?? __('shell.ideas.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
        <span class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</span>
    </header>
@else
    <button type="button" x-data @click="$store.capture.sheetOpen = true" aria-label="{{ __('capture.line.label') }}"
        class="fixed right-4 bottom-[calc(84px+env(safe-area-inset-bottom))] z-30 grid size-14 place-items-center rounded-full bg-accent text-accent-fg shadow-card transition active:scale-95 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper wb:hidden">
        @include('shell.icon', ['name' => 'plus', 'class' => 'size-6'])
    </button>
    <nav class="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-1 pt-2 pb-[max(14px,env(safe-area-inset-bottom))] wb:hidden" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
        @php($tab = 'flex min-w-14 flex-col items-center gap-0.5 rounded-card px-1.5 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
        <a href="{{ route('shell.board') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></a>
        <a href="{{ route('weekplan') }}" class="{{ $tab }}">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></a>
        <a href="{{ route('shell.ideas') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></a>
        <a href="{{ route('shell.projects') }}" wire:navigate class="{{ $tab }} text-accent-ink" aria-current="page">@include('shell.icon', ['name' => 'project'])<span>{{ __('projects.nav') }}</span></a>
        <a href="{{ route('shell.board', ['open' => 'inbox']) }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></a>
    </nav>
@endif
