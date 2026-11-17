{{-- Werkbank top bar: logo, Board / Woche / Ideen, capture line (C), battery, settings. @param $battery (?Battery) --}}
<header class="sticky top-0 z-30 flex h-14 items-center gap-4 bg-surface px-6" style="border-bottom: var(--t-bw) solid var(--t-line)">
    <a href="{{ route('shell.board') }}" wire:navigate class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</a>
    <nav class="flex gap-1 text-sm font-semibold" aria-label="{{ __('shell.nav.board') }}">
        <a href="{{ route('shell.board') }}" wire:navigate
            @class(['rounded-card px-3 py-1.5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'bg-accent-soft text-accent-ink' => ($active ?? '') === 'board', 'text-ink-soft hover:text-ink' => ($active ?? '') !== 'board'])
            @if (($active ?? '') === 'board') aria-current="page" @endif>{{ __('shell.nav.board') }}</a>
        {{-- Step 4.1: the week plan editor (Zeitplan, with the switch to the Wochenplan). --}}
        <x-shell.action key="week_plan" layout="werkbank" mode="board" as="a" :href="route('shell.week')" wire:navigate
            @class(['rounded-card px-3 py-1.5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'bg-accent-soft text-accent-ink' => ($active ?? '') === 'week', 'text-ink-soft hover:text-ink' => ($active ?? '') !== 'week'])
            :aria-current="($active ?? '') === 'week' ? 'page' : null">{{ __('shell.nav.week') }}</x-shell.action>
        {{-- Step 3.4: "Ideen", the browse view of Later. --}}
        <x-shell.action key="browse_later" layout="werkbank" mode="board" as="a" :href="route('shell.ideas')" wire:navigate
            @class(['rounded-card px-3 py-1.5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'bg-accent-soft text-accent-ink' => ($active ?? '') === 'ideas', 'text-ink-soft hover:text-ink' => ($active ?? '') !== 'ideas'])
            :aria-current="($active ?? '') === 'ideas' ? 'page' : null">{{ __('shell.nav.ideas') }}</x-shell.action>
    </nav>
    <div class="ml-auto flex items-center gap-2">
        @include('shell.capture-field', ['mode' => 'board'])
        @isset($battery)
            @include('shell.reset-button', ['layout' => 'werkbank', 'mode' => 'board', 'label' => true])
            @include('shell.battery', ['layout' => 'werkbank', 'mode' => 'board', 'battery' => $battery])
        @endisset
        <a href="{{ route('shell.settings') }}" wire:navigate
            @class(['grid size-9 place-items-center rounded-full border bg-surface transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-accent text-accent-ink' => ($active ?? '') === 'settings', 'border-line text-ink-soft hover:text-ink' => ($active ?? '') !== 'settings'])
            aria-label="{{ __('shell.nav.settings') }}">@include('shell.icon', ['name' => 'gear', 'class' => 'size-5'])</a>
    </div>
</header>
