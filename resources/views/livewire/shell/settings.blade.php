<div id="content" class="min-h-dvh">
    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'settings'])</div>
    <header class="flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <a href="{{ route('shell.board') }}" wire:navigate class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.settings.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
        <span class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</span>
    </header>

    <main class="mx-auto flex max-w-3xl flex-col gap-5 px-4 py-6 wb:px-6 wb:py-10">
        <div>
            <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ __('shell.settings.title') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('shell.settings.intro') }}</p>
        </div>

        <section class="rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:theme-picker />
        </section>

        <section class="rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:shell.capture-tokens />
        </section>

        <p class="text-sm text-ink-faint">
            <a href="{{ route('app') }}" class="underline decoration-line underline-offset-4 hover:text-ink">{{ __('shell.settings.old_app') }}</a>
        </p>
    </main>

    {{-- Capture on every page (R3): + on mobile here too. --}}
    <button type="button" x-data @click="$store.capture.sheetOpen = true" aria-label="{{ __('capture.line.label') }}"
        class="fixed right-4 bottom-[max(20px,env(safe-area-inset-bottom))] z-30 grid size-14 place-items-center rounded-full bg-accent text-accent-fg shadow-card focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:hidden">
        @include('shell.icon', ['name' => 'plus', 'class' => 'size-6'])
    </button>
</div>
