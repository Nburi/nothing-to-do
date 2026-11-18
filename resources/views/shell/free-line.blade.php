{{-- The free time in words, and "Meins behalten" for it (step 3.4, action keep_mine): the free slot running
     now or the next one today stays free, no idea in it. In every layout and mode (A11); hidden while the
     idea card shows its own "Meins behalten" button, and once the slot is kept.
     @param $layout, $mode, $idea (IdeaOffer), $free (string), $class (optional) --}}
<p @class(['flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-soft', $class ?? ''])>
    <span>{{ $free }}</span>
    @if ($idea->canKeepMine() && ! ($mode === 'board' && $idea->hasIdea()))
        <span aria-hidden="true" class="text-ink-faint">·</span>
        <x-shell.action
            key="keep_mine" :layout="$layout" :mode="$mode"
            wire:click="keepMine" wire:loading.attr="disabled"
            :title="__('shell.free.keep_mine_hint')"
            class="font-medium text-ink-soft underline decoration-line underline-offset-4 transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60"
        >{{ __('shell.free.keep_mine') }}</x-shell.action>
    @endif
</p>
