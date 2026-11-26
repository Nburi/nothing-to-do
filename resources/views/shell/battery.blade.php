{{-- Battery chip with its three levels (G4: not asked = Okay). @param $layout, $mode, $battery (Battery) --}}
{{-- Step 5.2: a small disclosure with three toggle buttons. It is not role="menu" (that promises arrow keys):
     focus moves to the chosen level on open, Escape and Tab-out close it, Escape puts the focus back.
     Placed by resources/js/popover.js: it opens upward when there is no room below the chip. --}}
<div class="relative" x-data="popover({ phone: false })" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @focusout="if (open && ! $el.contains($event.relatedTarget)) open = false">
    <x-shell.action
        key="battery" :layout="$layout" :mode="$mode"
        @click="toggle()"
        x-ref="trigger" x-bind:aria-expanded="open"
        class="inline-flex h-11 wb:h-9 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
    >
        @include('shell.icon', ['name' => 'battery', 'class' => 'size-4'])
        <span>{{ __('shell.battery.label') }}: {{ __('shell.battery.'.$battery->value) }}</span>
    </x-shell.action>
    <div
        x-show="open" x-cloak x-transition.opacity.duration.120ms
        x-effect="if (open) $nextTick(() => ($el.querySelector('[aria-pressed=true]') ?? $el.querySelector('button'))?.focus())"
        @click.outside="open = false"
        x-ref="panel" :style="style"
        class="fixed z-40 w-56 overflow-hidden rounded-card border border-line bg-surface p-1 shadow-card"
        role="group" aria-label="{{ __('shell.battery.label') }}"
    >
        @foreach (\App\Planning\Battery::cases() as $level)
            <button
                type="button"
                aria-pressed="{{ $level === $battery ? 'true' : 'false' }}"
                wire:click="setBattery('{{ $level->value }}')"
                @click="open = false"
                @class([
                    'flex w-full flex-col items-start rounded-card px-3 py-2 text-left transition hover:bg-paper focus:outline-hidden focus-visible:bg-paper',
                    'bg-accent-soft' => $level === $battery,
                ])
            >
                <span class="text-sm font-semibold text-ink">{{ __('shell.battery.'.$level->value) }}</span>
                <span class="text-xs text-ink-faint">{{ __('shell.battery.hint.'.$level->value) }}</span>
            </button>
        @endforeach
    </div>
</div>
