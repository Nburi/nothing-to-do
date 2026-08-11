{{-- App-wide capture panel. Always in the DOM (it's cheap — no queries); the
     Alpine `quickCapture` store decides whether it's visible, so opening it
     costs no round trip. See app/Livewire/QuickCapture.php. --}}
@php
    $targets = App\Livewire\QuickCapture::TARGETS;
@endphp
<div
    x-data="{
        /* Mirrors the title input locally. wire:model is deferred, so $wire.title
           does not change per keystroke — this does, without a round trip. */
        typed: '',
        /* null = follow typing; true/false = the user overrode it with the button. */
        forced: null,
        targets: @js($targets),
        /* Optional fields appear as soon as there is something to attach them to.
           Agenda is always open: its fields are required, not optional. */
        get showExtra() {
            if ($wire.target === 'agenda') return true;
            return this.forced !== null ? this.forced : this.typed.trim().length > 0;
        },
        cycle(dir) {
            const i = this.targets.indexOf($wire.target);
            $wire.setTarget(this.targets[(i + dir + this.targets.length) % this.targets.length]);
        },
    }"
    @captured.window="typed = ''; forced = null"
    @quick-capture-opened.window="typed = ''; forced = null"
    x-show="$store.quickCapture.open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[10vh] sm:pt-[14vh]"
    role="dialog"
    aria-modal="true"
    aria-label="Schnellerfassung"
>
    <div
        class="absolute inset-0 bg-ink/40"
        @click="$store.quickCapture.hide()"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    ></div>

    <div
        class="relative w-full max-w-lg overflow-hidden rounded-card border border-line bg-surface shadow-map"
        x-trap.noscroll="$store.quickCapture.open"
        @keydown.escape.window="$store.quickCapture.hide()"
        x-transition:enter="transition ease-tactile duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    >
        <form wire:submit="save">
            {{-- Title row --}}
            <div class="flex items-center gap-2.5 px-4 py-3.5">
                <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                </svg>
                <input
                    id="quick-capture-title"
                    type="text"
                    wire:model="title"
                    placeholder="Was steht an?"
                    :placeholder="{
                        project: 'Wie heisst das Projekt?',
                        craft: 'Was möchtest du basteln?',
                        agenda: 'Was ist aufgegeben?',
                    }[$wire.target] ?? 'Was steht an?'"
                    autocomplete="off"
                    @input="typed = $event.target.value"
                    @keydown.down.prevent="cycle(1)"
                    @keydown.up.prevent="cycle(-1)"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                />
                <button
                    type="submit"
                    class="flex-none rounded-card bg-forest px-3.5 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >
                    Erfassen
                </button>
            </div>

            {{-- Target chips --}}
            <div class="flex flex-wrap items-center gap-1.5 border-t border-line/60 px-4 py-2.5">
                @foreach ($targets as $t)
                    <button
                        type="button"
                        wire:click="setTarget('{{ $t }}')"
                        @class([
                            'rounded-full border px-2.5 py-1 text-xs transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint',
                            'border-forest bg-forest text-white' => $target === $t,
                            'border-line bg-paper text-ink-soft hover:border-ink-faint/60 hover:text-ink' => $target !== $t,
                        ])
                        aria-pressed="{{ $target === $t ? 'true' : 'false' }}"
                    >
                        {{ App\Livewire\QuickCapture::labelFor($t) }}
                    </button>
                @endforeach

                {{-- Hidden for Agenda: its fields are required, so there is nothing to fold away. --}}
                <button
                    type="button"
                    x-show="$wire.target !== 'agenda'"
                    @click="forced = !showExtra"
                    class="ml-auto flex items-center gap-1 rounded-card px-1.5 py-1 text-xs text-ink-faint transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    :aria-expanded="showExtra"
                >
                    <span x-text="showExtra ? 'Weniger' : 'Mehr'">Mehr</span>
                    <svg class="h-3 w-3 transition-transform duration-150" :class="showExtra && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            {{-- Extra fields — which ones exist depends on the target, resolved
                 client-side so switching a chip doesn't need a second round trip. --}}
            <div
                x-show="showExtra"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="border-t border-line/60 px-4 py-3"
                style="display: none;"
            >
                <div x-show="['inbox','todos','tasks'].includes($wire.target)" style="display: none;">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="qc-deadline" class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline · hart</label>
                            <input id="qc-deadline" type="date" wire:model="deadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                        <div>
                            <label for="qc-due" class="mb-1 block text-[11px] font-medium text-ink-faint">Wunschtermin · weich</label>
                            <input id="qc-due" type="date" wire:model="dueDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                    </div>

                    <div class="mt-3">
                        @include('livewire.partials.notes-editor', ['fieldName' => 'notes', 'htmlProperty' => 'notesHtml', 'idPrefix' => 'qc'])
                    </div>
                </div>

                <div x-show="$wire.target === 'project'" style="display: none;">
                    <label for="qc-project-deadline" class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline</label>
                    <input id="qc-project-deadline" type="date" wire:model="deadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                </div>

                <div x-show="$wire.target === 'craft'" style="display: none;">
                    <label for="qc-where" class="mb-1 block text-[11px] font-medium text-ink-faint">Wo anfangen (optional)</label>
                    <input
                        id="qc-where"
                        type="text"
                        wire:model="whereToBegin"
                        placeholder="z. B. Material besorgen, Anleitung suchen …"
                        autocomplete="off"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                </div>

                {{-- Agenda: type, Fach and date are all required (mirrors Agenda::save()). --}}
                <div x-show="$wire.target === 'agenda'" style="display: none;" class="flex flex-col gap-3">
                    <div class="flex items-center gap-1.5">
                        @foreach (App\Models\AgendaEntry::TYPES as $value => $label)
                            <button
                                type="button"
                                wire:click="$set('agendaType', '{{ $value }}')"
                                @class([
                                    'rounded-full border px-3 py-1 text-xs transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint',
                                    'border-forest bg-forest text-white' => $agendaType === $value,
                                    'border-line bg-paper text-ink-soft hover:border-ink-faint/60 hover:text-ink' => $agendaType !== $value,
                                ])
                                aria-pressed="{{ $agendaType === $value ? 'true' : 'false' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="qc-subject" class="mb-1 block text-[11px] font-medium text-ink-faint">Fach</label>
                            {{-- A native datalist rather than the Agenda page's full Alpine
                                 combobox: it autocompletes from the same source with no extra
                                 markup, and stays invisible until the field is used. --}}
                            <input
                                id="qc-subject"
                                type="text"
                                wire:model="subject"
                                list="qc-subject-options"
                                placeholder="z. B. Mathematik"
                                autocomplete="off"
                                class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                            />
                            <datalist id="qc-subject-options">
                                @foreach ($this->existingSubjects as $s)
                                    <option value="{{ $s }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="qc-date" class="mb-1 block text-[11px] font-medium text-ink-faint">Datum</label>
                            <input id="qc-date" type="date" wire:model="date" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer: errors, the "just captured" confirmation, or the key hints. --}}
            <div class="flex min-h-[34px] items-center gap-2 border-t border-line/60 bg-paper/60 px-4 py-2 text-[11px]">
                @error('title')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('deadline')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('dueDate')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('notes')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('whereToBegin')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('subject')
                    <span class="text-signal">{{ $message }}</span>
                @enderror
                @error('date')
                    <span class="text-signal">{{ $message }}</span>
                @enderror

                @if ($captured && ! $errors->any())
                    <span wire:key="captured-{{ $captured['title'] }}-{{ $captured['label'] }}" class="animate-rise flex min-w-0 items-center gap-1.5 text-forest">
                        <svg class="h-3 w-3 flex-none" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5 6.5 12 13 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="truncate">„{{ $captured['title'] }}" → {{ $captured['label'] }}</span>
                    </span>
                @endif

                {{-- Keyboard hints only where there is a keyboard; on touch the same
                     row explains why the panel stays open instead of sitting empty. --}}
                <span class="ml-auto hidden flex-none items-center gap-2 text-ink-faint sm:flex">
                    <span>↑↓ Ziel</span>
                    <span>↵ Erfassen</span>
                    <span>Esc schliessen</span>
                </span>
                <span class="ml-auto flex-none text-ink-faint sm:hidden">Bleibt offen für den nächsten</span>
            </div>
        </form>
    </div>
</div>
