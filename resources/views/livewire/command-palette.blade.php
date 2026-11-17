{{-- App-wide command palette. Always in the DOM (the empty state costs no
     query until the first keystroke — see CommandPalette::groups()); the
     Alpine `commandPalette` store decides whether it is visible, so opening it
     costs no round trip. Keyboard cursor = which [data-palette-item] carries
     aria-selected, driven entirely client-side by the store. --}}
<div
    x-data="{ typed: '' }"
    x-show="$store.commandPalette.open"
    x-cloak
    @command-palette-opened.window="typed = ''"
    @command-palette-results.window="$nextTick(() => $store.commandPalette.reset($root))"
    class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[10vh] sm:pt-[14vh]"
    role="dialog"
    aria-modal="true"
    aria-label="Suche und Befehle"
>
    <div
        class="absolute inset-0 bg-ink/40"
        @click="$store.commandPalette.hide()"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    ></div>

    <div
        class="relative w-full max-w-xl overflow-hidden rounded-card border border-line bg-surface shadow-map"
        x-trap.noscroll="$store.commandPalette.open"
        @keydown.escape.window="$store.commandPalette.hide()"
        x-transition:enter="transition ease-tactile duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    >
        {{-- Search row --}}
        <div class="flex items-center gap-2.5 border-b border-line px-4 py-3.5">
            <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5"/><path d="m13.5 13.5 3.5 3.5"/>
            </svg>
            <input
                id="command-palette-input"
                type="text"
                wire:model.live.debounce.120ms="query"
                placeholder="Suchen oder springen …"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                role="combobox"
                aria-controls="command-palette-list"
                aria-expanded="true"
                @input="typed = $event.target.value"
                @keydown.down.prevent="$store.commandPalette.move($root, 1)"
                @keydown.up.prevent="$store.commandPalette.move($root, -1)"
                @keydown.enter.prevent="$store.commandPalette.activate($root)"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 py-2 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0 sm:py-0"
            />
            <kbd class="hidden flex-none rounded border border-line px-1.5 py-0.5 text-[10px] font-medium text-ink-faint sm:block">Esc</kbd>
        </div>

        {{-- Results. The list root is keyed on the query so a fresh result set
             replaces the old nodes outright instead of morphing rows in place
             (which would leave a stale aria-selected on a recycled row). --}}
        <div id="command-palette-list" role="listbox" class="max-h-[min(24rem,55dvh)] overflow-y-auto overscroll-contain py-1.5">
            <div wire:key="palette-results-{{ md5($query) }}">
                @forelse ($this->groups as $group)
                    <p class="px-4 pb-1 pt-2.5 text-[11px] font-medium uppercase tracking-wide text-ink-faint">{{ $group['label'] }}</p>

                    @foreach ($group['items'] as $item)
                        <a
                            href="{{ $item['href'] }}"
                            wire:navigate
                            role="option"
                            data-palette-item
                            aria-selected="false"
                            @mousemove="$store.commandPalette.point($root, $el)"
                            class="mx-1.5 flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink transition-colors aria-selected:bg-forest-soft"
                        >
                            <span class="grid h-6 w-6 flex-none place-items-center rounded-md border border-line text-ink-faint">
                                @switch($item['icon'])
                                    @case('task')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m7 10.25 2 2 4-4.5"/></svg>
                                        @break
                                    @case('project')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5a1 1 0 0 1 1-1h4l1.5 2h7.5a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-8Z"/></svg>
                                        @break
                                    @case('group')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/></svg>
                                        @break
                                    @case('agenda')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
                                        @break
                                    @case('craft')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3a4.5 4.5 0 0 0-2.5 8.25c.4.28.6.7.6 1.15V13h3.8v-.6c0-.45.2-.87.6-1.15A4.5 4.5 0 0 0 10 3Z"/><path d="M8.25 15.5h3.5"/></svg>
                                        @break
                                    @case('help')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M8 8a2 2 0 1 1 3 1.7c-.7.4-1 .8-1 1.6M10 13.6v.1"/></svg>
                                        @break
                                    @default
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                                @endswitch
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                            @if ($item['subtitle'] !== '')
                                <span class="flex-none truncate text-xs text-ink-faint">{{ $item['subtitle'] }}</span>
                            @endif
                        </a>
                    @endforeach
                @empty
                    @if (trim($query) !== '')
                        <p class="px-4 py-6 text-center text-sm text-ink-faint">
                            Nichts gefunden für „{{ $query }}“.
                        </p>
                    @endif
                @endforelse
            </div>

            {{-- The one action: whatever was typed is also a thing to capture.
                 Hidden while the box is empty. Shown as a normal row so ↑/↓/↵
                 treat it like any result — and it is the answer to "I searched
                 for it and it isn't there yet". --}}
            <template x-if="typed.trim().length > 0">
                <div>
                    <p class="px-4 pb-1 pt-2.5 text-[11px] font-medium uppercase tracking-wide text-ink-faint">Aktion</p>
                    <button
                        type="button"
                        role="option"
                        data-palette-item
                        aria-selected="false"
                        @click="$store.commandPalette.capture(typed)"
                        @mousemove="$store.commandPalette.point($root, $el)"
                        class="mx-1.5 flex w-[calc(100%-0.75rem)] items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-ink transition-colors aria-selected:bg-forest-soft"
                    >
                        <span class="grid h-6 w-6 flex-none place-items-center rounded-md border border-line text-ink-faint">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate">„<span x-text="typed.trim()"></span>“ erfassen</span>
                    </button>
                </div>
            </template>
        </div>

        {{-- Key hints: desktop only — a phone has no keyboard to hint at. --}}
        <div class="hidden items-center gap-4 border-t border-line px-4 py-2 text-[11px] text-ink-faint sm:flex">
            <span><kbd class="font-medium">↑↓</kbd> wählen</span>
            <span><kbd class="font-medium">↵</kbd> öffnen</span>
            <span><kbd class="font-medium">Esc</kbd> schliessen</span>
        </div>
    </div>
</div>
