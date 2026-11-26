{{-- Step 4.9: the ⋯ menu of one item (a row on the board, a card on the Ideen page): Bearbeiten, Verschieben nach,
     Löschen. The items only send an event (item-edit / item-move / item-delete); the ItemEditor component of
     the page does the work and leaves the toast with "Rückgängig". A bottom sheet below 720 px, a popover from
     there that is placed from the trigger (position: fixed, resources/js/popover.js): it flips upward when there is
     no room below, so a scrolling panel never clips it and the last row of a list stays reachable.
     @param Task $task, ?string $layout and ?string $mode (the registered entry points; null on the Ideen page,
            where both layouts share one card), string $trigger (the trigger's classes) --}}
@php
    $layout = $layout ?? null;
    $mode = $mode ?? null;
    $item = 'flex min-h-11 w-full items-center gap-2.5 rounded-[6px] px-2.5 text-left text-sm text-ink transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:min-h-9';
    $heading = 'px-2.5 pt-2 pb-1 font-mono text-[10.5px] uppercase tracking-widest text-ink-faint';
@endphp
<div class="contents" data-item-menu x-data="popover()"
    @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="toggle()" :aria-expanded="open" :class="{ 'wb:opacity-100!': open }" aria-haspopup="menu" class="{{ $trigger }}"
        title="{{ __('items.menu.title') }}" aria-label="{{ __('items.menu.open', ['title' => $task->title]) }}">
        @include('shell.icon', ['name' => 'dots', 'class' => 'size-4'])
    </button>
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-black/40 wb:hidden" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-cloak x-trap.noscroll="open" role="menu" aria-label="{{ __('items.menu.title') }}"
        @keydown.arrow-down.prevent="$focus.wrap().next()" @keydown.arrow-up.prevent="$focus.wrap().previous()"
        x-ref="panel" :style="style"
        class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col gap-0.5 overflow-y-auto rounded-t-panel bg-surface p-3 pb-[max(12px,env(safe-area-inset-bottom))] shadow-card wb:inset-x-auto wb:bottom-auto wb:w-56 wb:rounded-card wb:p-2"
        style="border: var(--t-bw) solid var(--t-line)">
        <p class="truncate px-2.5 pb-1 text-sm font-semibold text-ink wb:hidden">{{ $task->title }}</p>
        <x-shell.action key="edit" :layout="$layout" :mode="$mode" role="menuitem" @click="open = false"
            wire:click="$dispatch('item-edit', { id: {{ $task->id }} })" class="{{ $item }}">
            @include('shell.icon', ['name' => 'edit', 'class' => 'size-4']) {{ __('items.menu.edit') }}
        </x-shell.action>
        <p class="{{ $heading }}">{{ __('items.menu.move') }}</p>
        @foreach (\App\Models\Task::LISTS as $target)
            @continue($target === $task->list)
            <x-shell.action key="move" :layout="$layout" :mode="$mode" role="menuitem" @click="open = false"
                wire:click="$dispatch('item-move', { id: {{ $task->id }}, list: '{{ $target }}' })" class="{{ $item }}">
                @include('shell.icon', ['name' => 'arrow-right', 'class' => 'size-4']) {{ __('items.menu.move_to', ['list' => __('shell.lists.'.$target)]) }}
            </x-shell.action>
        @endforeach
        <div class="my-1 border-t border-line" role="separator"></div>
        <x-shell.action key="delete" :layout="$layout" :mode="$mode" role="menuitem" @click="open = false"
            wire:click="$dispatch('item-delete', { id: {{ $task->id }} })" class="{{ $item }} text-bad">
            @include('shell.icon', ['name' => 'trash', 'class' => 'size-4']) {{ __('items.menu.delete') }}
        </x-shell.action>
    </div>
</div>
