{{-- Step 3.4: free time on the idle board (R6, CONVENTIONS 3, 4, 12). One idea from Later with "Starte mit",
     or the calm "Der Rest gehört dir." after "Meins behalten" or when every idea had its turn today.
     Buttons always in this order: Jetzt machen · Meins behalten · Andere (every battery level).
     A whole free day gets the bolder card (solid edge, more room), still only a suggestion.
     Same card in Werkbank and Stapel. @param $layout, $idea (IdeaOffer), $free and $freePlain (the free slot in words, with and without "gehört dir") --}}
@php
    $task = $idea->task;
    $bold = $idea->slot?->wholeDay ?? false;
    $btn = 'inline-flex min-h-11 items-center justify-center gap-2 whitespace-nowrap rounded-card px-3 font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60';
@endphp
@if ($idea->hasIdea())
    <article
        data-fold-from data-idea="{{ $bold ? 'whole-day' : 'slot' }}"
        @class(['flex min-w-0 flex-col rounded-panel shadow-card', 'gap-2.5 p-4 wb:p-5' => ! $bold, 'gap-3 p-5 wb:p-7' => $bold])
        style="border: {{ $bold ? 'calc(var(--t-bw) * 2) solid' : 'var(--t-bw) dashed' }} color-mix(in srgb, var(--t-warn) {{ $bold ? '75%' : '60%' }}, var(--t-line)); background: color-mix(in srgb, var(--t-warn) {{ $bold ? '8%' : '6%' }}, var(--t-surface))"
        aria-live="polite"
    >
        <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-warn">{{ __('shell.idea.eyebrow', ['free' => $free]) }}</span>
        @if ($task->area || $task->size)
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if ($task->area)
                    <span class="inline-flex items-center gap-1.5 font-semibold text-ink-soft">
                        <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
                    </span>
                @endif
                @if ($task->size)
                    <span class="rounded-[5px] border border-line px-1.5 py-px font-mono text-[10.5px] uppercase tracking-wider text-ink-faint">{{ \App\Shell\TaskFace::size($task) }}</span>
                @endif
            </div>
        @endif
        <h2 @class(['font-display leading-tight text-balance wrap-break-word text-ink', 'text-[21px]' => ! $bold, 'text-[26px] wb:text-[30px]' => $bold]) style="font-weight: var(--t-dw)">{{ $task->title }}</h2>
        <p class="text-sm wrap-break-word text-ink-soft">
            <span class="mr-1.5 font-mono text-[10.5px] font-medium uppercase tracking-wider text-warn">{{ __('shell.idea.start_with') }}</span>@if ($task->first_step){{ $task->first_step }}@else<span class="text-ink-faint">{{ __('shell.idea.no_start') }}</span>@endif
        </p>
        <div class="mt-1 flex flex-wrap gap-2">
            <x-shell.action
                key="start" :layout="$layout" mode="board"
                @click="foldInto($el, () => $wire.start({{ $task->id }}))" wire:loading.attr="disabled"
                class="{{ $btn }} flex-1 basis-32 bg-accent text-accent-fg hover:brightness-110 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            >
                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                {{ __('shell.idea.do_now') }}
            </x-shell.action>
            <x-shell.action
                key="keep_mine" :layout="$layout" mode="board"
                wire:click="keepMine" wire:loading.attr="disabled"
                :title="__('shell.free.keep_mine_hint')"
                class="{{ $btn }} flex-1 basis-32 border border-line bg-surface text-ink hover:bg-paper"
            >{{ __('shell.idea.keep_mine') }}</x-shell.action>
            <x-shell.action
                key="swap" :layout="$layout" mode="board"
                wire:click="anotherIdea" wire:loading.attr="disabled"
                class="{{ $btn }} basis-24 border border-transparent text-ink-soft hover:text-ink"
            >
                @include('shell.icon', ['name' => 'shuffle', 'class' => 'size-4'])
                {{ __('shell.idea.another') }}
            </x-shell.action>
        </div>
    </article>
@else
    {{-- Kept "mine", or Later had nothing new left today: a calm card, no buttons that push. --}}
    <article data-idea="{{ $idea->status->value }}" class="flex min-w-0 flex-col gap-1.5 rounded-panel p-4 wb:p-5"
        style="border: var(--t-bw) solid var(--t-line); background: color-mix(in srgb, var(--t-ink) 4%, var(--t-surface))" aria-live="polite">
        <span class="font-mono text-[11px] font-medium uppercase tracking-widest text-ink-faint">{{ $freePlain }}</span>
        <h2 class="font-display text-[21px] leading-tight text-ink" style="font-weight: var(--t-dw)">{{ __('shell.idea.mine_title') }}</h2>
        <p class="text-sm text-ink-soft">
            @if ($idea->status === \App\Services\Planning\IdeaStatus::Exhausted)
                {{ __('shell.idea.exhausted') }}
            @elseif ($idea->slot?->wholeDay)
                {{ __('shell.idea.mine_day') }}
            @elseif ($idea->slot?->untilMidnight)
                {{ __('shell.idea.mine_tonight') }}
            @else
                {{ __('shell.idea.mine_until', ['time' => $idea->slot?->until->format('H:i')]) }}
            @endif
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <a href="{{ route('shell.ideas') }}" wire:navigate class="font-medium text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.idea.browse') }}</a>
            @if ($idea->status === \App\Services\Planning\IdeaStatus::Mine)
                <button type="button" wire:click="undoMine" wire:loading.attr="disabled" class="font-medium text-ink-faint underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">{{ __('shell.idea.undo_mine') }}</button>
            @endif
        </div>
    </article>
@endif
