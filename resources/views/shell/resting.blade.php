{{-- Step 4.8 (C29): the calm board. The board shows at most two project / group cards at once; the others rest
     in one line each under their column. A resting card is not suggested. "Nach oben holen" puts it at the
     top of the board, where it takes the place of the lowest awake card.
     @param string $list, Collection<Bundle> $cards (the resting cards of this column) --}}
<div data-resting="{{ $list }}" class="flex flex-col gap-1.5 rounded-card px-2.5 py-2" style="border: var(--t-bw) dashed var(--t-line)">
    <p class="font-mono text-[10.5px] uppercase tracking-wider text-ink-faint" title="{{ __('shell.order.resting_hint') }}">{{ __('shell.order.resting', ['count' => $cards->count()]) }}</p>
    <ul class="flex flex-wrap gap-1.5">
        @foreach ($cards as $card)
            <li wire:key="resting-{{ $card->ref() }}">
                <button type="button" wire:click="wake('{{ $card->ref() }}')" wire:loading.attr="disabled"
                    title="{{ __('shell.order.wake') }}" aria-label="{{ __('shell.order.wake_named', ['name' => $card->name]) }}"
                    class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-xs font-semibold text-ink-soft transition hover:border-accent hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50">
                    @include('shell.icon', ['name' => $card->isProject() ? 'project' : 'group', 'class' => 'size-3.5 shrink-0'])
                    <span class="truncate">{{ $card->name }}</span>
                </button>
            </li>
        @endforeach
    </ul>
</div>
