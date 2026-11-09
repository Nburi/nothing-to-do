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
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center wb:items-center" role="dialog" aria-modal="true" aria-labelledby="sort-title">
            <div class="absolute inset-0 bg-black/45" wire:click="close"></div>

            {{-- Takes focus on open, so Enter places and 1 / 2 / 3 pick, not the button behind. --}}
            <div class="relative flex max-h-[92dvh] w-full flex-col gap-3.5 overflow-y-auto rounded-t-panel bg-surface p-4 pb-[max(16px,env(safe-area-inset-bottom))] shadow-card focus:outline-hidden wb:w-[min(540px,calc(100vw-32px))] wb:rounded-panel wb:p-6"
                style="border: var(--t-bw) solid var(--t-line)"
                tabindex="-1" x-init="$nextTick(() => $el.focus({ preventScroll: true }))"
                wire:key="sort-panel-{{ $total }}-{{ $placed }}-{{ count($queue) }}">
                @if ($task)
                    <div class="h-1 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--t-ink) 10%, var(--t-surface))" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                        <span class="block h-full bg-accent transition-all duration-300" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span>
                    </div>
                    <div class="flex items-center justify-between gap-3 font-mono text-[11px] uppercase tracking-widest text-ink-faint">
                        <span>{{ __('sort.title') }} · {{ __('sort.progress', ['current' => min($done + 1, $total), 'total' => $total]) }}</span>
                        <button type="button" wire:click="close" class="hidden normal-case tracking-normal hover:text-ink wb:inline">{{ __('sort.esc') }}</button>
                        <button type="button" wire:click="close" class="grid size-8 place-items-center rounded-full hover:bg-paper wb:hidden" aria-label="{{ __('sort.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
                    </div>

                    <h2 id="sort-title" class="font-display text-[21px] leading-snug wrap-break-word text-ink" style="font-weight: var(--t-dw)" wire:key="sort-title-{{ $task->id }}">{{ $task->title }}</h2>

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
                                    <kbd class="hidden rounded border border-line px-1 font-mono text-[10.5px] font-medium text-ink-faint wb:inline">{{ $i + 1 }}</kbd>
                                </span>
                                <span class="hidden text-xs text-ink-faint wb:block">{{ __('sort.targets.'.$target.'.hint') }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('list') <p class="-mt-2 text-xs font-semibold text-bad">{{ $message }}</p> @enderror

                    @unless ($isLater)
                        <div>
                            <span class="mb-1.5 block font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.size') }}</span>
                            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('sort.size') }}">
                                @foreach (\App\Planning\Size::cases() as $s)
                                    @php($on = ($size ?? 'short') === $s->value)
                                    <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('size', '{{ $s->value }}')"
                                        @class(['rounded-full border px-3 py-1.5 text-[13px] transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>
                                        <b class="font-semibold">{{ __('shell.sizes.'.$s->value) }}</b> · {{ __('sort.size_hints.'.$s->value) }}
                                    </button>
                                @endforeach
                            </div>
                            @error('size') <p class="mt-1 text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                        </div>
                    @endunless

                    @if ($more)
                        <div class="flex flex-col gap-3 border-t border-dashed border-line pt-3">
                            @unless ($isLater)
                                <div class="grid gap-3 wb:grid-cols-2">
                                    <label class="flex min-w-0 flex-col gap-1">
                                        <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.hard') }}</span>
                                        <input type="date" wire:model.live="hardDeadline" min="{{ auth()->user()->localToday()->toDateString() }}"
                                            @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('hard_deadline'), 'border-line' => ! $errors->has('hard_deadline')])
                                            style="border-width: var(--t-bw)" @error('hard_deadline') aria-invalid="true" @enderror>
                                        @error('hard_deadline')
                                            <span class="text-xs font-semibold text-bad">{{ $message }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">{{ __('sort.hard_hint') }}</span>
                                        @enderror
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1">
                                        <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.soft') }}</span>
                                        <input type="date" wire:model.live="softDeadline" min="{{ auth()->user()->localToday()->toDateString() }}"
                                            @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('soft_deadline'), 'border-line' => ! $errors->has('soft_deadline')])
                                            style="border-width: var(--t-bw)" @error('soft_deadline') aria-invalid="true" @enderror>
                                        @error('soft_deadline')
                                            <span class="text-xs font-semibold text-bad">{{ $message }}</span>
                                        @else
                                            @if ($hardDeadline && $softDeadline && $softDeadline > $hardDeadline)
                                                <span class="text-xs font-semibold text-warn">{{ __('sort.soft_after_hard') }}</span>
                                            @else
                                                <span class="text-xs text-ink-faint">{{ __('sort.soft_hint') }}</span>
                                            @endif
                                        @enderror
                                    </label>
                                </div>
                            @else
                                <label class="flex min-w-0 flex-col gap-1">
                                    <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.soft') }}</span>
                                    <input type="date" wire:model.live="softDeadline" min="{{ auth()->user()->localToday()->toDateString() }}" class="h-10 min-w-0 rounded-card border-line bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent" style="border-width: var(--t-bw)">
                                    @error('soft_deadline') <span class="text-xs font-semibold text-bad">{{ $message }}</span> @enderror
                                </label>
                            @endunless

                            @if ($this->areas->isNotEmpty())
                                <div>
                                    <span class="mb-1.5 block font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.area') }}</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($this->areas as $area)
                                            @php($on = $areaId === $area->id)
                                            <button type="button" wire:click="$set('areaId', {{ $area->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                                @class(['inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>
                                                <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($area->color) }}"></span>{{ $area->name }}
                                            </button>
                                        @endforeach
                                        <button type="button" wire:click="$set('areaId', null)" aria-pressed="{{ $areaId === null ? 'true' : 'false' }}"
                                            @class(['rounded-full border px-3 py-1.5 text-[13px] font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $areaId === null, 'border-line bg-surface text-ink-soft hover:text-ink' => $areaId !== null])>{{ __('sort.no_area') }}</button>
                                    </div>
                                    @error('area_id') <p class="mt-1 text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <label class="flex min-w-0 flex-col gap-1">
                                <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ $isLater ? __('sort.first_step_later') : __('sort.first_step') }}</span>
                                <input type="text" wire:model="firstStep" maxlength="255"
                                    placeholder="{{ $isLater ? __('sort.first_step_placeholder_later') : __('sort.first_step_placeholder') }}"
                                    @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('first_step'), 'border-line' => ! $errors->has('first_step')])
                                    style="border-width: var(--t-bw)">
                                @error('first_step') <span class="text-xs font-semibold text-bad">{{ $message }}</span> @enderror
                            </label>
                        </div>
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
                            <kbd class="hidden rounded border border-current px-1 font-mono text-[10.5px] opacity-70 wb:inline">Enter</kbd>
                        </button>
                    </div>
                @else
                    <div class="flex flex-col items-center gap-2 py-6 text-center">
                        <span class="grid size-12 place-items-center rounded-full bg-good-soft text-good">@include('shell.icon', ['name' => 'check', 'class' => 'size-6'])</span>
                        <h2 id="sort-title" class="font-display text-[22px] text-ink" style="font-weight: var(--t-dw)">{{ __('sort.done_title') }}</h2>
                        <p class="max-w-xs text-sm text-ink-soft">{{ __('sort.done_body') }}</p>
                        <button type="button" wire:click="close" class="mt-2 inline-flex min-h-11 items-center rounded-card border border-line bg-surface px-5 font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('sort.back_to_board') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($toast)
        <div wire:key="sort-toast-{{ $placed }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity
            class="fixed inset-x-0 bottom-[calc(96px+env(safe-area-inset-bottom))] z-[60] flex justify-center px-4 wb:bottom-6" role="status">
            <p class="flex items-center gap-3 rounded-card bg-deep py-2 pr-2 pl-4 text-sm font-semibold text-on-deep shadow-card">
                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                {{ $toast }}
                <button type="button" wire:click="undo" class="rounded-card px-2 py-1 underline underline-offset-2 hover:bg-white/10 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('sort.undo') }}</button>
            </p>
        </div>
    @endif
</div>
