{{-- Werkbank top bar: logo, Board / Woche, capture (2.3), battery, settings. @param $battery (?Battery) --}}
<header class="sticky top-0 z-30 flex h-14 items-center gap-4 bg-surface px-6" style="border-bottom: var(--t-bw) solid var(--t-line)">
    <a href="{{ route('shell.board') }}" wire:navigate class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</a>
    <nav class="flex gap-1 text-sm font-semibold" aria-label="{{ __('shell.nav.board') }}">
        <a href="{{ route('shell.board') }}" wire:navigate
            @class(['rounded-card px-3 py-1.5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'bg-accent-soft text-accent-ink' => request()->routeIs('shell.board'), 'text-ink-soft hover:text-ink' => ! request()->routeIs('shell.board')])
            @if (request()->routeIs('shell.board')) aria-current="page" @endif>{{ __('shell.nav.board') }}</a>
        {{-- Until the week plan editor (4.1) the old one does the job; the board already reads it. --}}
        <a href="{{ route('weekplan') }}" class="inline-flex items-center gap-1.5 rounded-card px-3 py-1.5 text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            {{ __('shell.nav.week') }}
            <span class="rounded-[4px] border border-line px-1 font-mono text-[9.5px] font-medium uppercase text-ink-faint">{{ __('shell.nav.old') }}</span>
        </a>
    </nav>
    <div class="ml-auto flex items-center gap-2">
        {{ $capture ?? '' }}
        @isset($battery)
            @include('shell.battery', ['layout' => 'werkbank', 'mode' => 'board', 'battery' => $battery])
        @endisset
        <a href="{{ route('shell.settings') }}" wire:navigate
            @class(['grid size-9 place-items-center rounded-full border bg-surface transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-accent text-accent-ink' => request()->routeIs('shell.settings'), 'border-line text-ink-soft hover:text-ink' => ! request()->routeIs('shell.settings')])
            aria-label="{{ __('shell.nav.settings') }}">@include('shell.icon', ['name' => 'gear', 'class' => 'size-5'])</a>
    </div>
</header>
