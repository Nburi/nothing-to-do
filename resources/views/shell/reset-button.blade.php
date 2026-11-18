{{-- "Abschalten": start the ten minute reset by hand (step 3.2, action `reset`). Quiet, next to the
     battery chip in every layout and mode (A11). @param $layout, $mode, $label (bool: show the word) --}}
@php
    $withLabel = $label ?? false;
    $cls = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-full border border-line bg-surface text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60',
        'px-3' => $withLabel,
        'w-9' => ! $withLabel,
    ]);
@endphp
<x-shell.action
    key="reset" :layout="$layout" :mode="$mode"
    wire:click="beginReset" wire:loading.attr="disabled"
    :title="__('shell.reset.start_by_hand_hint')"
    :aria-label="__('shell.reset.start_by_hand_hint')"
    class="{{ $cls }}"
>
    @include('shell.icon', ['name' => 'reset', 'class' => 'size-4'])
    @if ($withLabel)
        <span>{{ __('shell.reset.start_by_hand') }}</span>
    @endif
</x-shell.action>
