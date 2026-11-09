{{-- The session view's card (R1, R9): only the task, its first step and the timer. Shared by
     Werkbank and Stapel (risk C13). The timer is an Alpine island that counts down what the
     server said and asks the server again when a phase runs out (risk C5).
     @param $layout, $session (SessionState), $task (?Task), $next (?Task), $timer (array) --}}
@php
    use App\Planning\Session\Phase;
    $phase = $session->phase;
    $isBreak = $phase->isBreak();
    $isReady = $phase === Phase::Ready;
    $circumference = 339.29; // 2 * pi * 54
@endphp
<article class="flex w-full max-w-[520px] flex-col gap-4 rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @if ($phase === Phase::Work)
            <span class="inline-flex items-center gap-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-wider text-good">
                <span class="size-1.5 rounded-full bg-good {{ $timer['paused'] ? '' : 'motion-safe:animate-pulse' }}"></span>{{ $timer['paused'] ? __('shell.session.paused') : __('shell.session.live') }}
            </span>
        @else
            <span class="font-mono text-[10.5px] font-semibold uppercase tracking-wider text-accent-ink">
                {{ $isReady ? __('shell.session.ready') : ($phase === Phase::LongBreak ? __('shell.session.long_break') : __('shell.session.break')) }}
            </span>
        @endif
        @if ($task?->area)
            <span class="inline-flex items-center gap-1.5 font-semibold text-ink-soft">
                <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
            </span>
        @endif
        @if ($task)
            <span class="rounded-[5px] border border-line px-1.5 py-px font-mono text-[10.5px] uppercase tracking-wider text-ink-faint">{{ \App\Shell\TaskFace::size($task) }}</span>
        @endif
    </div>

    @if ($task)
        <h2 class="font-display text-[26px] leading-tight text-balance wrap-break-word text-ink wb:text-[30px]" style="font-weight: var(--t-dw)">{{ $task->title }}</h2>
    @endif

    @if ($phase->isTimed())
        <div
            wire:key="timer-{{ $layout }}-{{ $timer['key'] }}"
            x-data="sessionTimer({{ $timer['remaining'] }}, {{ max(1, $timer['total']) }}, {{ $timer['running'] ? 'true' : 'false' }})"
            class="relative size-40 self-center wb:size-44"
            role="timer"
            :aria-label="label"
        >
            <svg viewBox="0 0 128 128" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="64" cy="64" r="54" fill="none" stroke-width="9" style="stroke: color-mix(in srgb, var(--t-ink) 10%, var(--t-surface))"/>
                <circle cx="64" cy="64" r="54" fill="none" stroke-width="9" stroke-linecap="round"
                    class="{{ $isBreak ? 'stroke-good' : 'stroke-accent' }}"
                    stroke-dasharray="{{ $circumference }}"
                    :stroke-dashoffset="offset({{ $circumference }})"
                    stroke-dashoffset="{{ round($circumference * ($timer['remaining'] / max(1, $timer['total'])), 2) }}"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <b class="font-mono text-[34px] font-medium tabular-nums tracking-tight text-ink" x-text="label">{{ sprintf('%02d:%02d', intdiv($timer['remaining'], 60), $timer['remaining'] % 60) }}</b>
                <span class="text-xs text-ink-faint">{{ __('shell.session.of', ['minutes' => intdiv($timer['total'], 60)]) }}</span>
            </div>
        </div>
    @endif

    @if ($phase === Phase::Work)
        @if ($task && ($startWith = \App\Services\Planning\Suggestions::startWith($task)))
            <p class="flex flex-col gap-0.5 rounded-card bg-accent-soft px-3 py-2.5 font-semibold text-ink">
                <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('shell.start_with') }}</span>{{ $startWith }}
            </p>
        @endif
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="done" wire:loading.attr="disabled"
                class="inline-flex min-h-11 flex-1 basis-32 items-center justify-center gap-2 rounded-card bg-accent px-4 font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60">
                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                {{ __('shell.session.done') }}
            </button>
            <button type="button" wire:click="{{ $timer['paused'] ? 'resume' : 'pause' }}" wire:loading.attr="disabled"
                class="inline-flex min-h-11 flex-1 basis-28 items-center justify-center gap-2 rounded-card border border-line bg-surface px-4 font-semibold text-ink transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">
                @include('shell.icon', ['name' => $timer['paused'] ? 'play' : 'pause', 'class' => 'size-4'])
                {{ $timer['paused'] ? __('shell.session.resume') : __('shell.session.pause') }}
            </button>
        </div>
        <p class="text-center text-sm text-ink-faint">{{ $next ? __('shell.session.then', ['title' => $next->title]) : __('shell.session.then_free') }}</p>
    @else
        <p class="text-sm text-ink-soft">{{ $isReady ? __('shell.session.ready_body') : __('shell.session.break_body') }}</p>
        @if ($isBreak)
            <div class="flex">
                <button type="button" wire:click="{{ $timer['paused'] ? 'resume' : 'pause' }}" wire:loading.attr="disabled"
                    class="text-sm font-medium text-ink-soft underline decoration-line underline-offset-4 hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    {{ $timer['paused'] ? __('shell.session.resume') : __('shell.session.pause') }}
                </button>
            </div>
        @endif
        <x-shell.action
            key="start" :layout="$layout" mode="session"
            wire:click="start" wire:loading.attr="disabled"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-card {{ $isReady ? 'bg-accent text-accent-fg hover:brightness-110' : 'border border-line bg-surface text-ink hover:bg-paper' }} px-4 font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60"
        >
            @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
            {{ $task ? __('shell.session.continue') : __('shell.session.next_session') }}
        </x-shell.action>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-line pt-3 text-sm">
        @if ($task)
            <x-shell.action
                key="swap" :layout="$layout" mode="session"
                wire:click="shrink({{ $task->id }})" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 font-medium text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
            >@include('shell.icon', ['name' => 'shrink', 'class' => 'size-4']){{ __('shell.shrink') }}</x-shell.action>
        @endif
        <button type="button" wire:click="stop" wire:loading.attr="disabled"
            class="inline-flex items-center gap-1.5 font-medium text-ink-soft transition hover:text-bad focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            @include('shell.icon', ['name' => 'stop', 'class' => 'size-4']){{ __('shell.session.stop') }}
        </button>
    </div>
</article>
