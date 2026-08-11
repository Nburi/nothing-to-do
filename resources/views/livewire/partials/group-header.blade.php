{{-- Header of a task group's dashboard. Variables: $done, $total, $pct, $compact.
     $compact is the mobile variant — same content, smaller type, no wide gaps. --}}
<div>
    <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Alle Listen
    </a>

    <div class="mt-2 flex items-start justify-between gap-3">
        @if ($renaming)
            <form wire:submit="saveRename" class="flex flex-1 items-center gap-2">
                <input
                    type="text"
                    wire:model="groupName"
                    autofocus
                    aria-label="Name der Gruppe"
                    class="min-w-0 flex-1 rounded-card border-line bg-paper text-lg font-medium text-ink focus:border-overprint focus:ring-0"
                />
                <button type="submit" class="flex-none rounded-card bg-forest px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Speichern</button>
                <button type="button" wire:click="cancelRename" class="flex-none rounded-card px-2 py-1.5 text-sm text-ink-soft transition hover:bg-surface">Abbrechen</button>
            </form>
        @else
            <div class="min-w-0">
                <h1 class="flex items-center gap-2 break-words {{ $compact ? 'text-lg' : 'text-xl' }} font-medium tracking-tight text-ink">
                    <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="2" y="4.5" width="12" height="9" rx="1.6" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M4 2.5h8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                    {{ $this->group->name }}
                </h1>
                <p class="mt-1 text-[13px] text-ink-faint">
                    @if ($total === 0)
                        Noch keine Aufgaben in dieser Gruppe.
                    @else
                        <span class="tnum">{{ $done }}</span> von <span class="tnum">{{ $total }}</span> erledigt
                    @endif
                </p>
            </div>

            <div x-data="{ open: false }" class="relative flex-none">
                <button
                    type="button"
                    @click.stop="open = !open"
                    @keydown.escape.window="open = false"
                    class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    aria-label="Gruppen-Aktionen"
                >
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="8" cy="3" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="8" cy="13" r="1.4"/></svg>
                </button>
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100 origin-top-right"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    @click.outside="open = false"
                    class="absolute right-0 top-9 z-20 w-52 origin-top-right overflow-hidden rounded-card border border-line bg-surface p-1 shadow-map"
                    style="display: none;"
                >
                    <button type="button" wire:click="$set('renaming', true)" @click="open = false" class="block w-full rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                        Umbenennen
                    </button>
                    <div class="my-1 h-px bg-line/60"></div>
                    {{-- Dissolving is non-destructive: the tasks stay, they just stop
                         being bundled. The armed double-click is still used — it is
                         an irreversible structural change, even if nothing is lost. --}}
                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.dissolveGroup(); clearTimeout(_t); armed = false; open = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-signal hover:bg-signal-soft'"
                        class="block w-full rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition"
                    >
                        <span x-text="armed ? 'Wirklich auflösen?' : 'Gruppe auflösen'"></span>
                    </button>
                    <p class="px-2.5 pb-1 pt-0.5 text-[11px] leading-snug text-ink-faint">Die Aufgaben bleiben erhalten und liegen danach einzeln auf dem Board.</p>
                </div>
            </div>
        @endif
    </div>

    @error('groupName') <p class="mt-1 px-1 text-xs text-signal">{{ $message }}</p> @enderror

    @if ($total > 0)
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ $total }}" aria-label="Fortschritt der Gruppe">
            <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</div>
