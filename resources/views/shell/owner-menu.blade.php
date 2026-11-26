{{-- ui-fixes: the ⋯ menu in the header of a project or group page. "… löschen" lives here (Niels, test C48: at the end
     of a long page it was hard to find). It only asks (askDelete); the confirm itself is shell/confirm-delete.
     A bottom sheet below 720 px, a popover from there (resources/js/popover.js: it flips upward when needed).
     @param string $kind ('project'|'group'), string $name --}}
@php
    $item = 'flex min-h-11 w-full items-center gap-2.5 rounded-[6px] px-2.5 text-left text-sm text-bad transition hover:bg-bad-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:min-h-9';
@endphp
<div class="relative self-center" data-owner-menu x-data="popover()" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="toggle()" :aria-expanded="open" aria-haspopup="menu"
        class="grid size-11 wb:size-9 shrink-0 cursor-pointer place-items-center rounded-full text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
        title="{{ __('projects.owner_menu.title') }}" aria-label="{{ __('projects.owner_menu.open', ['name' => $name]) }}">
        @include('shell.icon', ['name' => 'dots', 'class' => 'size-4'])
    </button>
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-black/40 wb:hidden" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-cloak x-trap.noscroll="open" role="menu" aria-label="{{ __('projects.owner_menu.title') }}" x-ref="panel" :style="style"
        @keydown.arrow-down.prevent="$focus.wrap().next()" @keydown.arrow-up.prevent="$focus.wrap().previous()"
        class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col gap-0.5 overflow-y-auto rounded-t-panel bg-surface p-3 pb-[max(12px,env(safe-area-inset-bottom))] shadow-card wb:inset-x-auto wb:bottom-auto wb:w-56 wb:rounded-card wb:p-2"
        style="border: var(--t-bw) solid var(--t-line)">
        <p class="truncate px-2.5 pb-1 text-sm font-semibold text-ink wb:hidden">{{ $name }}</p>
        <button type="button" role="menuitem" @click="open = false" wire:click="askDelete" data-delete-owner class="{{ $item }}">
            @include('shell.icon', ['name' => 'trash', 'class' => 'size-4']) {{ __('projects.delete.'.$kind) }}
        </button>
    </div>
</div>
