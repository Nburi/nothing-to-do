{{-- Step d-bis-wann: the quiet „Bis wann?" after marking an undated task „Wichtig". One card for the whole board,
     whichever list, popover or sheet the flag was tapped in: a bottom sheet below 720 px, a small floating card from
     there. Never modal and never takes focus; it goes away by itself after a while (not while it is being used).
     Asked once per task (DeadlineOffer). @param ?array $offer (Board::$deadlineOffer), array $choices (choice => date) --}}
@php
    $asking = $offer !== null && $offer['set'] === null;
    $done = $offer !== null && $offer['set'] !== null;
    $live = $asking
        ? __('shell.important.when.label', ['title' => $offer['title']])
        : ($done ? __('shell.important.when.set', ['day' => $offer['label']]) : '');
    $btn = 'inline-flex h-11 wb:h-9 items-center justify-center rounded-full px-4 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60';
@endphp
{{-- Always there, so a screen reader announces what is put into it. --}}
<div class="sr-only" role="status" data-deadline-live>{{ $live }}</div>
@if ($offer !== null)
    <div
        wire:key="deadline-offer-{{ $offer['task'] }}-{{ $done ? 'set' : 'ask' }}"
        data-deadline-offer="{{ $done ? 'set' : 'ask' }}"
        role="group" aria-label="{{ $asking ? __('shell.important.when.label', ['title' => $offer['title']]) : __('shell.important.when.set', ['day' => $offer['label']]) }}"
        x-data="{
            timer: null,
            arm() { clearTimeout(this.timer); this.timer = setTimeout(() => $wire.dismissDeadline(), {{ $done ? 9000 : 14000 }}); },
            hold() { clearTimeout(this.timer); },
        }"
        x-init="arm()"
        @mouseenter="hold()" @mouseleave="arm()" @focusin="hold()" @focusout="arm()"
        @keydown.escape="$wire.dismissDeadline()"
        class="fixed inset-x-0 bottom-0 z-50 flex flex-col gap-2 rounded-t-panel bg-surface p-3 pb-[max(12px,env(safe-area-inset-bottom))] shadow-card wb:inset-x-auto wb:bottom-6 wb:left-1/2 wb:w-max wb:max-w-[min(34rem,calc(100vw-2rem))] wb:-translate-x-1/2 wb:rounded-card wb:pb-3"
        style="border: var(--t-bw) solid var(--t-line)">
        @if ($asking)
            <p class="min-w-0 px-1 text-sm text-ink-soft">
                <span class="font-semibold text-ink">{{ __('shell.important.when.ask') }}</span>
                <span class="block truncate wb:inline" data-deadline-title>{{ $offer['title'] }}</span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['today', 'tomorrow', 'week'] as $choice)
                    @if (isset($choices[$choice]))
                        <button type="button" wire:click="chooseDeadline('{{ $choice }}')" wire:loading.attr="disabled" data-choice="{{ $choice }}"
                            class="{{ $btn }} border border-line bg-paper text-ink hover:bg-accent-soft hover:text-accent-ink">{{ __('shell.important.when.'.$choice) }}</button>
                    @endif
                @endforeach
                <button type="button" wire:click="dismissDeadline" data-deadline-skip
                    class="{{ $btn }} text-ink-soft hover:text-ink">{{ __('shell.important.when.skip') }}</button>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <p class="px-1 text-sm text-ink">{{ __('shell.important.when.set', ['day' => $offer['label']]) }}</p>
                <button type="button" wire:click="undoDeadline" wire:loading.attr="disabled" data-deadline-undo
                    class="{{ $btn }} text-accent-ink underline underline-offset-4 hover:bg-paper">{{ __('shell.important.when.undo') }}</button>
                <button type="button" wire:click="dismissDeadline" aria-label="{{ __('shell.important.when.close') }}" title="{{ __('shell.important.when.close') }}"
                    class="ml-auto grid size-11 wb:size-9 place-items-center rounded-full text-ink-faint hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>
        @endif
    </div>
@endif
