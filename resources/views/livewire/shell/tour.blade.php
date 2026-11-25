<div>
    @if ($open)
        {{--
            The first-run tour (step 5.1). Three steps, all in the page already (no server round trip
            between them); only the end asks the server (Tour::finish). Esc, "Skip" and "Let's go" are
            the same exit. The pictures are decorative (aria-hidden): the words carry the meaning, and
            they say nothing about controls that are still changing, so the tour stays true.
            Tab stays inside the dialog while it is open.
        --}}
        <div
            x-data="{
                step: 0,
                total: 3,
                closed: false,
                progress: @js(__('onboarding.tour.progress')),
                get label() { return this.progress.replace(':current', this.step + 1).replace(':total', this.total); },
                forward() { if (this.step < this.total - 1) this.step++; },
                next() { if (this.step < this.total - 1) { this.step++; } else { this.close(); } },
                back() { if (this.step > 0) { this.step--; } },
                close() {
                    if (this.closed) return;
                    this.closed = true;
                    try {
                        const url = new URL(window.location.href);
                        if (url.searchParams.has('tour')) {
                            url.searchParams.delete('tour');
                            window.history.replaceState(window.history.state, '', url);
                        }
                    } catch (e) {}
                    $wire.finish();
                },
                trap(event) {
                    const items = [...this.$root.querySelectorAll('button:not([disabled])')].filter((el) => el.offsetParent !== null);
                    if (items.length === 0) return;
                    const first = items[0];
                    const last = items[items.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                },
            }"
            x-init="$nextTick(() => $refs.next.focus())"
            x-show="!closed"
            @keydown.escape.window="close()"
            @keydown.tab="trap($event)"
            @keydown.arrow-right.window="forward()"
            @keydown.arrow-left.window="back()"
            class="fixed inset-0 z-[80] flex items-end justify-center wb:items-center wb:p-6"
            style="background: color-mix(in srgb, var(--t-ink) 48%, transparent)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tour-title"
            data-tour
        >
            <div class="flex max-h-dvh w-full max-w-md flex-col gap-5 overflow-y-auto rounded-t-panel bg-surface p-5 pb-[max(20px,env(safe-area-inset-bottom))] shadow-card motion-safe:animate-rise wb:rounded-panel wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-mono text-xs uppercase tracking-wider text-ink-faint" x-text="label" aria-live="polite">{{ __('onboarding.tour.progress', ['current' => 1, 'total' => 3]) }}</p>
                    {{-- Hidden with visibility, not display, so the dialog keeps its height on the last step. --}}
                    <button type="button" @click="close()" :class="{ 'invisible': step === total - 1 }"
                        class="-mr-2 min-h-11 rounded-card px-3 text-sm text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('onboarding.tour.skip') }}</button>
                </div>

                {{-- The picture of the step. --}}
                <div class="relative h-36 overflow-hidden rounded-card bg-paper" style="border: var(--t-bw) solid var(--t-line)" aria-hidden="true">
                    {{-- 1 · Capture: one line, then it sits in the Inbox. --}}
                    <div x-show="step === 0" class="absolute inset-0 flex flex-col justify-center gap-2 px-4">
                        <div class="flex items-center gap-2 rounded-card border border-accent bg-surface px-3 py-2 shadow-card">
                            <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ __('onboarding.tour.example') }}<span class="ml-px inline-block h-4 w-px translate-y-0.5 bg-ink"></span></span>
                            <span class="shrink-0 rounded-card bg-paper px-1.5 py-0.5 font-mono text-[10px] text-ink-faint" style="border: var(--t-bw) solid var(--t-line)">Enter</span>
                        </div>
                        <div class="flex flex-col gap-1.5 px-1">
                            <span class="block h-2 w-3/4 rounded-full bg-ink opacity-15"></span>
                            <span class="block h-2 w-1/2 rounded-full bg-ink opacity-15"></span>
                        </div>
                    </div>
                    {{-- 2 · Sort: one card, three places. --}}
                    <div x-show="step === 1" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-4">
                        <div class="w-full max-w-[250px] rounded-card bg-surface px-3 py-2 text-sm text-ink shadow-card" style="border: var(--t-bw) solid var(--t-line)">
                            <span class="block truncate">{{ __('onboarding.tour.example') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="rounded-full bg-accent px-3 py-1 font-medium text-accent-fg">{{ __('shell.lists.todos') }}</span>
                            <span class="rounded-full border border-line px-3 py-1 text-ink-soft">{{ __('shell.lists.tasks') }}</span>
                            <span class="rounded-full border border-line px-3 py-1 text-ink-soft">{{ __('shell.lists.later') }}</span>
                        </div>
                    </div>
                    {{-- 3 · Evening: the board with its columns, then one card and the timer. --}}
                    <div x-show="step === 2" x-cloak class="absolute inset-0 flex items-center justify-center gap-3 px-4">
                        <div class="grid h-20 w-24 grid-cols-4 gap-1 rounded-card bg-surface p-1.5" style="border: var(--t-bw) solid var(--t-line)">
                            @foreach (range(1, 4) as $column)
                                <span class="flex flex-col gap-1 rounded-[3px] bg-paper p-0.5">
                                    <span class="block h-1.5 rounded-full bg-ink opacity-20"></span>
                                    <span class="block h-1.5 rounded-full bg-ink opacity-20"></span>
                                </span>
                            @endforeach
                        </div>
                        @include('shell.icon', ['name' => 'arrow-right', 'class' => 'size-5 text-ink-faint'])
                        <div class="flex h-20 w-24 flex-col items-center justify-center gap-1.5 rounded-card bg-surface shadow-card" style="border: var(--t-bw) solid var(--t-line)">
                            <span class="block h-1.5 w-12 rounded-full bg-ink opacity-30"></span>
                            <span class="font-mono text-lg text-ink">25:00</span>
                        </div>
                    </div>
                </div>

                {{-- The words of the step. --}}
                <div class="min-h-[132px]">
                    @foreach ([1, 2, 3] as $n)
                        <div x-show="step === {{ $n - 1 }}" @unless ($n === 1) x-cloak @endunless class="flex flex-col gap-2">
                            <h2 @if ($n === 1) id="tour-title" @endif class="font-display text-xl text-ink" style="font-weight: var(--t-dw)">{{ __("onboarding.tour.step{$n}.title") }}</h2>
                            <p class="text-sm leading-relaxed text-ink-soft">{{ __("onboarding.tour.step{$n}.body") }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5" aria-hidden="true">
                        @foreach ([0, 1, 2] as $dot)
                            <span class="h-1.5 rounded-full transition-all" :class="step === {{ $dot }} ? 'w-5 bg-accent' : 'w-1.5 bg-line'"></span>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="back()" x-show="step > 0" x-cloak
                            class="min-h-11 rounded-card px-4 text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('onboarding.tour.back') }}</button>
                        <button type="button" x-ref="next" @click="next()"
                            class="min-h-11 rounded-card bg-accent px-5 text-sm font-semibold text-accent-fg transition hover:brightness-110 active:scale-[0.98] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                            <span x-show="step < total - 1">{{ __('onboarding.tour.next') }}</span>
                            <span x-show="step === total - 1" x-cloak>{{ __('onboarding.tour.done') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
