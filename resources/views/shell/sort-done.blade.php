{{-- End of a sort round: the Inbox is empty. --}}
<div class="flex flex-col items-center gap-2 py-6 text-center">
    <span class="grid size-12 place-items-center rounded-full bg-good-soft text-good">@include('shell.icon', ['name' => 'check', 'class' => 'size-6'])</span>
    <h2 class="font-display text-[22px] text-ink" style="font-weight: var(--t-dw)">{{ __('sort.done_title') }}</h2>
    <p class="max-w-xs text-sm text-ink-soft">{{ __('sort.done_body') }}</p>
    <button type="button" wire:click="close" class="mt-2 inline-flex min-h-11 items-center rounded-card border border-line bg-surface px-5 font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('sort.back_to_board') }}</button>
</div>
