<div
    x-data
    @keydown.window="
        if (! $wire.open) return;
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName);
        if ($event.key === 'Escape') { $wire.close(); return; }
        if ($event.key === 'Enter' && ! $event.shiftKey && $event.target.tagName !== 'TEXTAREA' && ! ($event.target.tagName === 'BUTTON' && $event.target.closest('[role=dialog]'))) { $event.preventDefault(); $wire.place(); return; }
        if (typing || $event.metaKey || $event.ctrlKey || $event.altKey) return;
        if ($event.key === '1') $wire.pick('todos');
        if ($event.key === '2') $wire.pick('tasks');
        if ($event.key === '3') $wire.pick('later');
    "
>
    @if ($open)
        @php
            $task = $this->current;
            $isLater = $list === 'later';
            $done = $total - count($queue);
            $left = count($queue);
            $counts = $this->counts;
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center wb:items-center" role="dialog" aria-modal="true" aria-label="{{ __('sort.title') }}">
            <div class="absolute inset-0 hidden bg-black/45 wb:block" wire:click="close"></div>

            {{-- ═════ Desktop (Werkbank): the card with 1 / 2 / 3 and Enter. ═════ --}}
            {{-- Takes focus on open, so Enter places and 1 / 2 / 3 pick, not the button behind. --}}
            <div class="relative hidden max-h-[92dvh] w-[min(540px,calc(100vw-32px))] flex-col gap-3.5 overflow-y-auto rounded-panel bg-surface p-6 shadow-card focus:outline-hidden wb:flex"
                style="border: var(--t-bw) solid var(--t-line)"
                tabindex="-1" x-init="$nextTick(() => { if ($el.getClientRects().length) $el.focus({ preventScroll: true }) })"
                wire:key="sort-panel-{{ $total }}-{{ $placed }}-{{ $left }}">
                @if ($task)
                    <div class="h-1 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--t-ink) 10%, var(--t-surface))" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                        <span class="block h-full bg-accent transition-all duration-300" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span>
                    </div>
                    <div class="flex items-center justify-between gap-3 font-mono text-[11px] uppercase tracking-widest text-ink-faint">
                        <span>{{ __('sort.title') }} · {{ __('sort.progress', ['current' => min($done + 1, $total), 'total' => $total]) }}</span>
                        <button type="button" wire:click="close" class="normal-case tracking-normal hover:text-ink">{{ __('sort.esc') }}</button>
                    </div>

                    <h2 class="font-display text-[21px] leading-snug wrap-break-word text-ink" style="font-weight: var(--t-dw)" wire:key="sort-title-{{ $task->id }}">{{ $task->title }}</h2>

                    @if ($notice)
                        <p class="rounded-card bg-accent-soft px-3 py-2 text-sm text-ink" role="status">{{ $notice }}</p>
                    @endif

                    <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ __('sort.title') }}">
                        @foreach (\App\Services\Planning\InboxSort::TARGETS as $i => $target)
                            <button type="button" role="radio" aria-checked="{{ $list === $target ? 'true' : 'false' }}"
                                wire:click="pick('{{ $target }}')"
                                @class([
                                    'flex min-w-0 flex-col items-start gap-0.5 rounded-card px-3 py-2.5 text-left transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent',
                                    'bg-accent-soft' => $list === $target,
                                    'bg-surface hover:bg-paper' => $list !== $target,
                                ])
                                style="border: var(--t-bw) solid {{ $list === $target ? 'var(--t-accent)' : 'var(--t-line)' }}; {{ $list === $target ? 'box-shadow: inset 0 0 0 1px var(--t-accent)' : '' }}">
                                <span class="flex w-full items-center justify-between gap-1 font-semibold text-ink">
                                    {{ __('sort.targets.'.$target.'.label') }}
                                    <kbd class="rounded border border-line px-1 font-mono text-[10.5px] font-medium text-ink-faint">{{ $i + 1 }}</kbd>
                                </span>
                                <span class="text-xs text-ink-faint">{{ __('sort.targets.'.$target.'.hint') }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('list') <p class="-mt-2 text-xs font-semibold text-bad">{{ $message }}</p> @enderror

                    @unless ($isLater)
                        <div>
                            <span class="mb-1.5 block font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.size') }}</span>
                            @include('shell.sort-sizes')
                        </div>
                    @endunless

                    @if ($more)
                        @include('shell.sort-details', ['isLater' => $isLater, 'deck' => false])
                    @else
                        <button type="button" wire:click="$set('more', true)" class="self-start text-sm font-semibold text-accent-ink underline decoration-1 underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            {{ $isLater ? __('sort.more_later') : __('sort.more') }}
                        </button>
                    @endif

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="button" wire:click="skip" class="text-sm font-medium text-ink-soft underline decoration-line underline-offset-4 hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('sort.skip') }}</button>
                        <span class="flex-1"></span>
                        <button type="button" wire:click="place" wire:loading.attr="disabled" wire:target="place"
                            class="inline-flex min-h-11 items-center gap-2 rounded-card bg-accent px-5 font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60">
                            <span wire:loading.remove wire:target="place">{{ __('sort.place') }}</span>
                            <span wire:loading wire:target="place">…</span>
                            <kbd class="rounded border border-current px-1 font-mono text-[10.5px] opacity-70">Enter</kbd>
                        </button>
                    </div>
                @else
                    @include('shell.sort-done')
                @endif
            </div>

            {{-- ═════ Phone (Stapel): the Inbox as a deck, three piles to throw it on. ═════
                 Pick the size on the top card, then drag it onto To-dos, Tasks or Später, or tap a pile.
                 The card follows the finger 1:1 and springs back if it is not over a pile (CONVENTIONS 7). --}}
            <div class="relative flex h-dvh w-full flex-col wb:hidden" style="background: color-mix(in srgb, var(--t-warn) 5%, var(--t-bg))" x-data="sortDeck">
                <header class="flex items-center gap-3 px-4 pb-2 pt-[max(14px,env(safe-area-inset-top))]">
                    <h2 class="font-display text-[17px] text-ink" style="font-weight: var(--t-dw)">{{ __('sort.title') }}</h2>
                    @if ($task)
                        <span class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('sort.progress', ['current' => min($done + 1, $total), 'total' => $total]) }}</span>
                    @endif
                    <button type="button" wire:click="close" class="ml-auto grid size-10 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('sort.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-5'])</button>
                </header>
                <div class="mx-4 h-1 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--t-ink) 10%, var(--t-surface))" aria-hidden="true">
                    <span class="block h-full bg-accent transition-all duration-300" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span>
                </div>

                @if ($task)
                    @if ($notice)
                        <p class="mx-4 mt-3 rounded-card bg-accent-soft px-3 py-2 text-sm text-ink" role="status">{{ $notice }}</p>
                    @endif

                    <div class="relative mx-5 mt-4 min-h-0 flex-1">
                        @if ($left > 2)
                            <span class="absolute inset-x-0 top-0 h-[min(100%,300px)] rounded-panel bg-surface" style="border: var(--t-bw) solid var(--t-line); transform: translateY(14px) rotate(2deg) scale(.94)" aria-hidden="true"></span>
                        @endif
                        @if ($left > 1)
                            <span class="absolute inset-x-0 top-0 h-[min(100%,300px)] rounded-panel bg-surface" style="border: var(--t-bw) solid var(--t-line); transform: translateY(7px) rotate(-2.5deg) scale(.97)" aria-hidden="true"></span>
                        @endif

                        <article
                            x-ref="card"
                            wire:key="deck-card-{{ $task->id }}-{{ $placed }}"
                            :style="cardStyle"
                            @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
                            @class([
                                'absolute inset-x-0 top-0 flex max-h-full min-h-[min(100%,300px)] flex-col gap-3 rounded-panel bg-surface p-4 shadow-card select-none',
                                'touch-none' => ! $more,
                                'overflow-y-auto overscroll-contain' => $more,
                            ])
                            style="border: var(--t-bw) solid var(--t-line)"
                           
                        >
                            <span data-grip class="-mt-1 h-1 w-10 self-center rounded-full bg-line {{ $more ? 'touch-none' : '' }}" aria-hidden="true"></span>
                            <span x-show="hot" x-cloak x-text="hotLabel" :style="'color:' + hotColor"
                                class="absolute top-3 right-3 rotate-6 rounded-md border-2 border-current px-2 py-0.5 font-mono text-xs font-bold uppercase tracking-wider"></span>
                            <span class="font-mono text-[10.5px] uppercase tracking-widest text-ink-faint">{{ __('shell.lists.inbox') }}</span>
                            <h3 class="font-display text-[21px] leading-snug wrap-break-word text-ink" style="font-weight: var(--t-dw)">{{ $task->title }}</h3>
                            <div>
                                <span class="mb-1.5 block font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.how_long') }}</span>
                                @include('shell.sort-sizes')
                            </div>
                            @if ($more)
                                @include('shell.sort-details', ['isLater' => false, 'deck' => true])
                            @else
                                <button type="button" wire:click="$set('more', true)" class="self-start text-sm font-semibold text-accent-ink underline decoration-1 underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('sort.more') }}</button>
                            @endif
                            <p class="text-center text-xs text-ink-faint">{{ __('sort.deck_hint') }}</p>
                        </article>
                    </div>

                    <div class="flex justify-center py-2">
                        <button type="button" wire:click="skip" class="text-sm font-medium text-ink-soft underline decoration-line underline-offset-4 hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('sort.skip') }}</button>
                    </div>

                    <div class="grid grid-cols-3 gap-3 px-3.5 pt-1 pb-[max(20px,env(safe-area-inset-bottom))]">
                        @foreach (['todos' => 'var(--t-area-2)', 'tasks' => 'var(--t-area-1)', 'later' => 'var(--t-area-3)'] as $target => $edge)
                            <button type="button" data-sort-target="{{ $target }}" data-sort-label="{{ __('shell.lists.'.$target) }}" data-sort-color="{{ $edge }}"
                                @click="throwTo('{{ $target }}')" :disabled="flying"
                                class="group flex min-w-0 flex-col gap-1.5 text-left focus:outline-hidden"
                                aria-label="{{ __('sort.onto', ['list' => __('shell.lists.'.$target)]) }}">
                                <span class="relative isolate block h-[74px] w-full">
                                    <span class="absolute inset-0 -z-10 rounded-card bg-surface" style="border: var(--t-bw) solid var(--t-line); transform: rotate(-3deg) translate(-3px, 5px)" aria-hidden="true"></span>
                                    <span class="absolute inset-0 flex items-end rounded-card bg-surface px-2.5 py-2 text-[11px] text-ink-faint shadow-card transition duration-150 group-focus-visible:ring-3 group-focus-visible:ring-accent"
                                        :class="hot === '{{ $target }}' ? '-translate-y-1.5 scale-105 ring-3 ring-accent-soft' : ''"
                                        style="border: var(--t-bw) solid var(--t-line); border-top: 4px solid {{ $edge }}">{{ __('sort.targets.'.$target.'.hint') }}</span>
                                </span>
                                <span class="flex items-baseline justify-between px-0.5">
                                    <b class="font-display text-[13px] text-ink" style="font-weight: var(--t-dw)">{{ __('shell.lists.'.$target) }}</b>
                                    <em class="font-mono text-[11.5px] not-italic text-ink-faint">{{ $counts[$target] ?? 0 }}</em>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-1 items-center justify-center px-6">@include('shell.sort-done')</div>
                @endif
            </div>
        </div>
    @endif

    @if ($toast)
        <div wire:key="sort-toast-{{ $placed }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity
            class="fixed inset-x-0 top-[max(56px,calc(env(safe-area-inset-top)+44px))] z-[60] flex justify-center px-4 wb:top-auto wb:bottom-6" role="status">
            <p class="flex items-center gap-3 rounded-card bg-deep py-2 pr-2 pl-4 text-sm font-semibold text-on-deep shadow-card">
                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                {{ $toast }}
                <button type="button" wire:click="undo" class="rounded-card px-2 py-1 underline underline-offset-2 hover:bg-white/10 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('sort.undo') }}</button>
            </p>
        </div>
    @endif
</div>
