{{-- Step 4.2, the early warning on the board (Niels' pick 2026-11-17: a line in the start strip).
     Werkbank: under the time line. Stapel: under "frei bis" in the Tonight tray. Hard = warn colour,
     soft = quiet and never red (F5). Nothing without a crunch. Opens the crunch screen (action crunch).
     @param $layout, $crunch (CrunchView), $class (optional) --}}
@if ($crunch->hasCrunch())
    <p @class(['flex flex-wrap items-center gap-x-2 gap-y-1 text-sm', $crunch->isHard() ? 'text-warn' : 'text-ink-faint', $class ?? '']) role="status" data-crunch-line="{{ $crunch->level->value }}">
        <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
        <span class="{{ $crunch->isHard() ? 'font-medium' : '' }}">
            {{ $crunch->isHard()
                ? trans_choice('shell.crunch.line_hard', $crunch->missingSessions, ['count' => $crunch->missingSessions, 'day' => \App\Services\Planning\DayLabel::for($crunch->windowEnd, \App\Support\UserClock::now(auth()->user()))])
                : __('shell.crunch.line_soft') }}
        </span>
        <x-shell.action key="crunch" :layout="$layout" mode="board" wire:click="$dispatch('crunch-open')"
            class="font-semibold text-accent-ink underline decoration-1 underline-offset-4 transition hover:decoration-2 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
        >{{ __('shell.crunch.open') }}</x-shell.action>
    </p>
@endif
