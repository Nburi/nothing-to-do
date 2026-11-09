{{-- Battery chip with its three levels (G4: not asked = Okay). @param $layout, $mode, $battery (Battery) --}}
<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    <x-shell.action
        key="battery" :layout="$layout" :mode="$mode"
        @click="open = !open"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
        class="inline-flex h-9 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-sm font-medium text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
    >
        @include('shell.icon', ['name' => 'battery', 'class' => 'size-4'])
        <span>{{ __('shell.battery.label') }}: {{ __('shell.battery.'.$battery->value) }}</span>
    </x-shell.action>
    <div
        x-show="open" x-cloak x-transition.opacity.duration.120ms
        @click.outside="open = false"
        class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-card border border-line bg-surface p-1 shadow-card"
        role="menu"
    >
        @foreach (\App\Planning\Battery::cases() as $level)
            <button
                type="button" role="menuitemradio"
                aria-checked="{{ $level === $battery ? 'true' : 'false' }}"
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
