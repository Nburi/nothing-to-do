{{-- Step 3.2: the ten minute reset and the battery question at its end (R2, G4; Niels' pick A,
     "a calm full screen"). The board is not visible while this shows. Same screen on both layouts.
     The countdown is the sessionTimer island (wall-clock based); the server owns the state, so a
     reload or the second device shows the same stage. Skipping is silent: no message, nothing counted.
     @param $stage (ResetStage), $timer (array: remaining, total, running, key) --}}
@php
    use App\Planning\ResetStage;
    $running = $stage === ResetStage::Running;
@endphp
<section
    data-region="reset"
    data-reset-stage="{{ $stage->value }}"
    wire:key="reset-screen"
    class="reset-screen fixed inset-0 z-[45] flex flex-col overflow-y-auto overscroll-contain bg-paper"
    role="dialog" aria-modal="true" aria-labelledby="reset-title"
    x-data
    x-init="$nextTick(() => $el.querySelector('[data-autofocus]')?.focus({ preventScroll: true }))"
>
    <div class="m-auto flex w-full max-w-[460px] flex-col items-center gap-7 px-6 pt-[max(40px,env(safe-area-inset-top))] pb-[max(40px,env(safe-area-inset-bottom))] text-center">
        @if ($stage === ResetStage::Battery)
            <p class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.checkin.eyebrow') }}</p>
            <h1 id="reset-title" class="font-display text-[28px] leading-tight text-balance text-ink wb:text-[34px]" style="font-weight: var(--t-dw)">{{ __('shell.checkin.title') }}</h1>

            <div class="grid w-full grid-cols-3 gap-2.5" role="group" aria-labelledby="reset-title">
                @foreach (\App\Planning\Battery::cases() as $level)
                    <button type="button" wire:click="answerBattery('{{ $level->value }}')" wire:loading.attr="disabled"
                        @if ($level === \App\Planning\Battery::Okay) data-autofocus @endif
                        class="flex min-h-24 flex-col items-center justify-center gap-1 rounded-card border border-line bg-surface px-2 py-4 text-ink shadow-card transition hover:border-accent hover:bg-accent-soft hover:text-accent-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper disabled:opacity-60">
                        <span class="font-display text-lg" style="font-weight: var(--t-dw)">{{ __('shell.checkin.'.$level->value) }}</span>
                        <span class="text-xs leading-snug text-ink-soft">{{ __('shell.checkin.hint.'.$level->value) }}</span>
                    </button>
                @endforeach
            </div>

            <div class="flex flex-col items-center gap-2">
                <button type="button" wire:click="answerBattery('later')" wire:loading.attr="disabled"
                    class="rounded-card px-3 py-2 text-sm font-semibold text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">
                    {{ __('shell.checkin.later') }}
                </button>
                <p class="max-w-xs text-xs text-ink-faint">{{ __('shell.checkin.note') }}</p>
            </div>
        @else
            <p class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.reset.eyebrow') }}</p>

            <div
                wire:key="reset-timer-{{ $timer['key'] }}"
                x-data="sessionTimer({{ $timer['remaining'] }}, {{ max(1, $timer['total']) }}, {{ $timer['running'] ? 'true' : 'false' }})"
                class="flex w-full flex-col items-center gap-4"
                role="timer"
                aria-live="off"
                :aria-label="label"
            >
                <b class="font-display text-[84px] leading-none tabular-nums tracking-tight text-ink wb:text-[112px]" style="font-weight: var(--t-dw)" x-text="label">{{ sprintf('%02d:%02d', intdiv($timer['remaining'], 60), $timer['remaining'] % 60) }}</b>
                {{-- A quiet line that empties with the time; full while it waits for "Los". --}}
                <span class="relative h-1 w-40 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--t-ink) 10%, var(--t-bg))" aria-hidden="true">
                    <span class="reset-bar absolute inset-y-0 left-0 rounded-full bg-accent" style="width: {{ round(100 * $timer['remaining'] / max(1, $timer['total']), 2) }}%" :style="`width: ${(100 * left / total).toFixed(2)}%`"></span>
                </span>
            </div>

            <h1 id="reset-title" class="font-display text-[26px] leading-tight text-balance text-ink wb:text-[30px]" style="font-weight: var(--t-dw)">{{ __('shell.reset.title') }}</h1>

            <ul class="flex flex-wrap justify-center gap-2" aria-label="{{ __('shell.reset.ideas_label') }}">
                @foreach (__('shell.reset.ideas') as $idea)
                    <li class="rounded-full bg-accent-soft px-3.5 py-1.5 text-sm font-semibold text-accent-ink">{{ $idea }}</li>
                @endforeach
            </ul>

            @if ($running)
                <div class="flex min-h-24 flex-col items-center gap-2">{{-- same height as Los + Überspringen, so the timer never moves --}}
                    <p class="text-sm text-ink-soft">{{ __('shell.reset.running') }}</p>
                    <button type="button" wire:click="closeReset" wire:loading.attr="disabled" data-autofocus
                        class="rounded-card px-3 py-2 text-sm font-semibold text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">
                        {{ __('shell.reset.end_early') }}
                    </button>
                </div>
            @else
                <div class="flex min-h-24 w-full flex-col items-center gap-3">
                    <button type="button" wire:click="beginReset" wire:loading.attr="disabled" data-autofocus
                        class="inline-flex min-h-12 w-full max-w-60 items-center justify-center gap-2 rounded-card bg-accent px-6 text-base font-semibold text-accent-fg shadow-card transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper disabled:opacity-60">
                        @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                        {{ __('shell.reset.go') }}
                    </button>
                    <button type="button" wire:click="closeReset" wire:loading.attr="disabled"
                        class="rounded-card px-3 py-2 text-sm font-semibold text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">
                        {{ __('shell.reset.skip') }}
                    </button>
                </div>
            @endif
        @endif
    </div>
</section>
