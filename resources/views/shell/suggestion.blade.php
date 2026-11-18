{{-- The start strip's card (R1, CONVENTIONS 3 and 4): one suggestion with "Start with" and why,
     or why there is none. Same card in Werkbank and Stapel. @param $layout, $suggestion, $counts --}}
@php
    $s = $suggestion;
    $task = $s->task;
    $total = array_sum($counts);
@endphp
<article
    @if ($s->hasPick()) data-fold-from @endif
    class="flex min-w-0 flex-col gap-2.5 rounded-panel bg-surface p-4 shadow-card wb:p-5"
    style="border: var(--t-bw) solid {{ $s->hasPick() ? 'var(--t-accent)' : 'var(--t-line)' }}"
    aria-live="polite"
>
    @if ($s->hasPick() && $task)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-accent-ink">{{ __('shell.start_here') }}</span>
            @if ($s->battery === \App\Planning\Battery::Low)
                <span class="text-xs font-semibold text-warn">{{ __('shell.low_battery') }}</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($task->area)
                <span class="inline-flex items-center gap-1.5 font-semibold text-ink-soft">
                    <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
                </span>
            @endif
            <span class="rounded-[5px] border border-line px-1.5 py-px font-mono text-[10.5px] uppercase tracking-wider text-ink-faint">{{ \App\Shell\TaskFace::size($task) }}</span>
            @if ($hard = \App\Shell\TaskFace::hard($task))
                <span class="rounded-[5px] bg-warn-soft px-1.5 py-px font-mono text-[11px] text-warn">{{ $hard }}</span>
            @elseif ($soft = \App\Shell\TaskFace::soft($task))
                <span class="rounded-[5px] border border-dashed border-ink-faint px-1.5 py-px font-mono text-[11px] text-ink-soft">{{ $soft }}</span>
            @endif
        </div>
        <h2 class="font-display text-[22px] leading-tight text-balance wrap-break-word text-ink" style="font-weight: var(--t-dw)">{{ $task->title }}</h2>
        @if ($s->startWith)
            <p class="text-sm text-ink-soft"><span class="mr-1.5 font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('shell.start_with') }}</span>{{ $s->startWith }}</p>
        @endif
        @if ($s->why)
            <p class="text-sm text-ink-faint">{{ $s->why }}</p>
        @endif
        <div class="mt-1 flex flex-wrap gap-2">
            <x-shell.action
                key="start" :layout="$layout" mode="board"
                @click="foldInto($el, () => $wire.start({{ $task->id }}))" wire:loading.attr="disabled"
                class="inline-flex min-h-11 flex-1 basis-36 items-center justify-center gap-2 whitespace-nowrap rounded-card bg-accent px-3 font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
            >
                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                {{ __('shell.start', ['minutes' => $s->pick?->minutes ?? 25]) }}
            </x-shell.action>
            <x-shell.action
                key="swap" :layout="$layout" mode="board"
                wire:click="another" wire:loading.attr="disabled"
                class="inline-flex min-h-11 flex-1 basis-28 items-center justify-center gap-2 whitespace-nowrap rounded-card border border-line bg-surface px-3 font-semibold text-ink transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60"
            >
                @include('shell.icon', ['name' => 'shuffle', 'class' => 'size-4'])
                {{ __('shell.another') }}
            </x-shell.action>
        </div>
        <x-shell.action
            key="swap" :layout="$layout" mode="board"
            wire:click="shrink({{ $task->id }})" wire:loading.attr="disabled"
            class="self-start text-sm font-medium text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
        >{{ __('shell.shrink') }}</x-shell.action>
    @elseif ($total === 0)
        <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-accent-ink">{{ __('shell.empty_board.eyebrow') }}</span>
        <h2 class="font-display text-[22px] leading-tight text-ink" style="font-weight: var(--t-dw)">{{ __('shell.empty_board.title') }}</h2>
        <p class="text-sm text-ink-soft">{{ __('shell.empty_board.body') }}</p>
    @elseif ($s->status === \App\Services\Planning\SuggestionStatus::OutsideBlocks)
        <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-ink-faint">{{ __('shell.outside_blocks.eyebrow') }}</span>
        <h2 class="font-display text-[20px] leading-tight text-ink" style="font-weight: var(--t-dw)">{{ __('shell.outside_blocks.title') }}</h2>
        <p class="text-sm text-ink-soft">
            {{ $s->nextBlockAt ? __('shell.outside_blocks.next', ['when' => $this->whenLabel($s->nextBlockAt)]) : __('shell.outside_blocks.none') }}
        </p>
        <p class="text-sm text-ink-faint">{{ __('shell.outside_blocks.manual') }}</p>
    @else
        <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-ink-faint">{{ __('shell.nothing_open.eyebrow') }}</span>
        <h2 class="font-display text-[20px] leading-tight text-ink" style="font-weight: var(--t-dw)">{{ __('shell.nothing_open.title') }}</h2>
        <p class="text-sm text-ink-soft">{{ $counts['inbox'] > 0 ? __('shell.nothing_open.sort') : __('shell.nothing_open.body') }}</p>
    @endif
</article>
