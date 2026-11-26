{{-- Step 4.3c: the ⋯ menu of a step. "Verschieben" is the way without dragging (Niels, 2026-11-24:
     the arrows are gone; the menu and the keyboard replace them): to the top or bottom of its list and,
     on a project, into any other stage or into none. "Wartet auf" stays here too (projects only). Every
     item is a plain server call (moveStep / setWaitsOn), so the toast with "Rückgängig" works the same
     as after a drag. A bottom sheet below 720 px, a popover from there.
     @param Task $task, string $lane, bool $first, bool $last, string $icon (the trigger's classes, with
            the fade of a waiting step), ?Task $blocker, ?Collection $stages (project), ?Collection $others --}}
@php
    $item = 'flex min-h-11 w-full items-center gap-2.5 rounded-[6px] px-2.5 text-left text-sm text-ink transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-40 disabled:hover:bg-transparent wb:min-h-9';
    $heading = 'px-2.5 pt-2 pb-1 font-mono text-[10.5px] uppercase tracking-widest text-ink-faint';
@endphp
<div class="relative" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" class="{{ $icon }} cursor-pointer"
        aria-label="{{ __('projects.move.menu', ['title' => $task->title]) }}">
        @include('shell.icon', ['name' => 'dots', 'class' => 'size-4'])
    </button>
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-black/40 wb:hidden" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-cloak x-trap.noscroll="open" role="menu" data-step-menu aria-label="{{ __('projects.move.menu_title') }}"
        class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col gap-0.5 overflow-y-auto rounded-t-panel bg-surface p-3 pb-[max(12px,env(safe-area-inset-bottom))] shadow-card wb:absolute wb:inset-x-auto wb:bottom-auto wb:right-0 wb:top-full wb:z-20 wb:mt-1 wb:max-h-none wb:w-72 wb:rounded-card wb:p-2"
        style="border: var(--t-bw) solid var(--t-line)">
        <p class="truncate px-2.5 pb-1 text-sm font-semibold text-ink wb:hidden">{{ $task->title }}</p>
        <p class="{{ $heading }}">{{ __('projects.move.section') }}</p>
        <button type="button" role="menuitem" @disabled($first) @click="open = false" wire:click="moveStep({{ $task->id }}, '{{ $lane }}', 0)" class="{{ $item }}">
            @include('shell.icon', ['name' => 'to-top', 'class' => 'size-4']) {{ __('projects.move.top') }}
        </button>
        <button type="button" role="menuitem" @disabled($last) @click="open = false" wire:click="moveStep({{ $task->id }}, '{{ $lane }}')" class="{{ $item }}">
            @include('shell.icon', ['name' => 'to-bottom', 'class' => 'size-4']) {{ __('projects.move.bottom') }}
        </button>
        @if ($stages !== null)
            @foreach ($stages as $i => $s)
                @continue('stage-'.$s->id === $lane)
                <button type="button" role="menuitem" @click="open = false" wire:click="moveStep({{ $task->id }}, 'stage-{{ $s->id }}')" class="{{ $item }}">
                    @include('shell.icon', ['name' => 'arrow-right', 'class' => 'size-4']) <span class="min-w-0 flex-1 truncate">{{ __('projects.move.to_stage', ['number' => $i + 1]) }}</span>
                    <span class="max-w-[8rem] truncate text-xs text-ink-faint">{{ $s->name }}</span>
                </button>
            @endforeach
            @if ($stages->isNotEmpty() && $lane !== 'none')
                <button type="button" role="menuitem" @click="open = false" wire:click="moveStep({{ $task->id }}, 'none')" class="{{ $item }}">
                    @include('shell.icon', ['name' => 'arrow-right', 'class' => 'size-4']) {{ __('projects.no_stage') }}
                </button>
            @endif
            <label class="mt-1 flex flex-col gap-1 px-2.5 pb-1 text-xs font-semibold text-ink-soft">
                {{ __('projects.waits_on') }}
                <select wire:change="setWaitsOn({{ $task->id }}, $event.target.value)" @change="open = false" class="min-h-11 wb:min-h-10 rounded-card border border-line bg-paper px-2 text-sm font-normal text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    <option value="" @selected($blocker === null)>{{ __('projects.waits_on_none') }}</option>
                    @foreach ($others as $o)
                        @continue($o->id === $task->id)
                        <option value="{{ $o->id }}" @selected($task->waits_on_id === $o->id)>{{ \Illuminate\Support\Str::limit($o->title, 50) }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        {{-- Step 4.3d: out of the project or group, back onto the board in its own list. --}}
        <hr class="my-1 border-line" role="separator">
        <button type="button" role="menuitem" @click="open = false" wire:click="releaseStep({{ $task->id }})" data-release class="{{ $item }}">
            @include('shell.icon', ['name' => 'back', 'class' => 'size-4']) {{ __('projects.release.menu') }}
        </button>
    </div>
</div>
