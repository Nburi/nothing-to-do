{{-- Step 3.1, finding C1 ("Verzögerung vor dem Timer"): the session card as the browser can draw it
     before the server has answered Start. On the click it flies from the started card to where the
     session card will stand and shows the title and the timer when the columns are gone, however
     long the round trip takes; the server's card takes its place when it has landed
     (shellFold in app.js: launchGhost, enter). Keep the classes in step with
     shell/session-card.blade.php. wire:ignore: Livewire's morph never touches this element. --}}
@php($minutes = (new \App\Planning\Rhythm)->workMinutes)
<div wire:ignore data-session-ghost hidden inert aria-hidden="true" class="pointer-events-none fixed left-0 top-0 z-40">
    <article data-ghost-card class="flex w-full flex-col gap-4 rounded-panel bg-surface p-5 shadow-card wb:p-7" style="border: var(--t-bw) solid var(--t-line)">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-wider text-good">
                <span class="size-1.5 rounded-full bg-good motion-safe:animate-pulse"></span>{{ __('shell.session.live') }}
            </span>
            <span data-ghost-area hidden class="inline-flex items-center gap-1.5 font-semibold text-ink-soft"><span data-ghost-dot class="size-2 rounded-full"></span><span data-ghost-area-name></span></span>
            <span data-ghost-size hidden class="rounded-[5px] border border-line px-1.5 py-px font-mono text-[10.5px] uppercase tracking-wider text-ink-faint"></span>
        </div>
        <h2 data-ghost-title class="font-display text-[26px] leading-tight text-balance wrap-break-word text-ink wb:text-[30px]" style="font-weight: var(--t-dw)"></h2>
        <div class="relative size-40 self-center wb:size-44">
            <svg viewBox="0 0 128 128" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="64" cy="64" r="54" fill="none" stroke-width="9" style="stroke: color-mix(in srgb, var(--t-ink) 10%, var(--t-surface))"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <b data-ghost-digits class="font-mono text-[34px] font-medium tabular-nums tracking-tight text-ink">{{ sprintf('%02d:%02d', $minutes, 0) }}</b>
                <span class="text-xs text-ink-faint">{{ __('shell.session.of', ['minutes' => $minutes]) }}</span>
            </div>
        </div>
        <p data-ghost-start hidden class="flex flex-col gap-0.5 rounded-card bg-accent-soft px-3 py-2.5 font-semibold text-ink">
            <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('shell.start_with') }}</span><span data-ghost-start-text></span>
        </p>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex min-h-11 flex-1 basis-32 items-center justify-center gap-2 rounded-card bg-accent px-4 font-semibold text-accent-fg">
                @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
                {{ __('shell.session.done') }}
            </span>
            <span class="inline-flex min-h-11 flex-1 basis-28 items-center justify-center gap-2 rounded-card border border-line bg-surface px-4 font-semibold text-ink">
                @include('shell.icon', ['name' => 'pause', 'class' => 'size-4'])
                {{ __('shell.session.pause') }}
            </span>
        </div>
        <p class="text-center text-sm text-ink-faint">&nbsp;</p>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-line pt-3 text-sm">
            <span class="inline-flex items-center gap-1.5 font-medium text-ink-soft">@include('shell.icon', ['name' => 'shrink', 'class' => 'size-4']){{ __('shell.shrink') }}</span>
            <span class="inline-flex items-center gap-1.5 font-medium text-ink-soft">@include('shell.icon', ['name' => 'stop', 'class' => 'size-4']){{ __('shell.session.stop') }}</span>
        </div>
    </article>
</div>
