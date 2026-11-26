<div id="content" class="min-h-dvh">
    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'settings'])</div>
    <header class="flex items-center gap-2 px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <a href="{{ route('shell.board') }}" wire:navigate class="grid size-11 wb:size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.settings.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
        <span class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</span>
    </header>

    <main class="mx-auto flex max-w-3xl flex-col gap-5 px-4 pt-6 pb-28 wb:px-6 wb:py-10">
        <div>
            <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ __('shell.settings.title') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('shell.settings.intro') }}</p>
        </div>

        <section class="rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:theme-picker />
        </section>

        <section class="rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:shell.language-picker />
        </section>

        <section id="kategorien" class="scroll-mt-20 rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:shell.categories />
        </section>

        <section id="abschalten" class="scroll-mt-20 rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:shell.nudge-times />
        </section>

        <section class="rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <livewire:shell.capture-tokens />
        </section>

        <section class="flex flex-col gap-4 rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
            <div>
                <h3 class="mb-1 text-base font-medium text-ink">{{ __('onboarding.tour_card.title') }}</h3>
                <p class="text-sm leading-relaxed text-ink-soft">{{ __('onboarding.tour_card.intro') }}</p>
            </div>
            <a href="{{ route('shell.board', ['tour' => 1]) }}" wire:navigate
                class="inline-flex min-h-11 w-fit items-center rounded-card border border-line px-4 text-sm font-medium text-ink transition hover:border-ink-faint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('onboarding.tour_card.open') }}</a>
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
