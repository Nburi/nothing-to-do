{{-- Shared inline edit sheet (TaskBoard via the ManagesTasks trait). --}}
@if ($editingId)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Aufgabe bearbeiten">
        <div class="absolute inset-0 bg-ink/40" wire:click="cancelEdit"></div>
        <div class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.cancelEdit()">
            <form wire:submit="saveEdit" class="space-y-4">
                <h2 class="text-base font-medium text-ink">Aufgabe bearbeiten</h2>

                <div>
                    <label for="editTitle" class="mb-1 block text-xs font-medium text-ink-soft">Titel</label>
                    <input id="editTitle" type="text" wire:model="editTitle" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    @error('editTitle') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                {{-- List selector: Inbox / To-Dos / Tasks --}}
                <div
                    x-data="{
                        open: false,
                        list: $wire.entangle('editList'),
                        options: [
                            { value: 'inbox',    label: 'Inbox' },
                            { value: 'todos',    label: 'To-Dos' },
                            { value: 'tasks',    label: 'Tasks' },
                        ],
                        get label() { return this.options.find(o => o.value === this.list)?.label ?? 'Inbox'; },
                    }"
                    class="relative"
                >
                    <label class="mb-1 block text-xs font-medium text-ink-soft">Liste</label>
                    <button
                        type="button"
                        @click.stop="open = !open"
                        @click.outside="open = false"
                        @keydown.escape.window="open = false"
                        :aria-expanded="open"
                        aria-haspopup="listbox"
                        class="flex w-full items-center gap-2 rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink transition hover:border-ink-faint/60 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-overprint"
                    >
                        <span x-text="label" class="min-w-0 flex-1 text-left"></span>
                        <svg class="h-3.5 w-3.5 flex-none text-ink-faint transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute inset-x-0 top-full z-20 mt-1 origin-top overflow-hidden rounded-card border border-line bg-surface p-1 shadow-map"
                        role="listbox"
                        style="display: none;"
                    >
                        <template x-for="opt in options" :key="opt.value">
                            <button
                                type="button"
                                @click="list = opt.value; open = false"
                                role="option"
                                :aria-selected="list === opt.value"
                                class="flex w-full items-center gap-2 rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition"
                                :class="list === opt.value ? 'bg-paper font-medium text-ink' : 'text-ink-soft hover:bg-paper hover:text-ink'"
                            >
                                <span x-text="opt.label"></span>
                                <svg x-show="list === opt.value" class="ml-auto h-3 w-3 flex-none text-forest" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                    <path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                    @error('editList') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="editDeadline" class="mb-1 block text-xs font-medium text-ink-soft">Deadline · hart</label>
                        <input id="editDeadline" type="date" wire:model="editDeadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    </div>
                    <div>
                        <label for="editDueDate" class="mb-1 block text-xs font-medium text-ink-soft">Wunschtermin · weich</label>
                        <input id="editDueDate" type="date" wire:model="editDueDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    </div>
                </div>
                @error('editDeadline') <p class="text-xs text-signal">{{ $message }}</p> @enderror
                @error('editDueDate') <p class="text-xs text-signal">{{ $message }}</p> @enderror

                <div x-show="$wire.editList !== 'inbox'" style="display: none;">
                    <label for="editDuration" class="mb-1 block text-xs font-medium text-ink-soft">Geschätzte Dauer (Minuten)</label>
                    <input id="editDuration" type="number" min="1" max="600" step="1" wire:model="editDuration" placeholder="z. B. 25" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    @error('editDuration') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                @include('livewire.partials.notes-editor', ['fieldName' => 'editNotes', 'htmlProperty' => 'editNotesHtml', 'idPrefix' => 'edit'])

                <div class="flex items-center justify-between pt-1">
                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.deleteTask({{ $editingId }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-signal hover:bg-signal-soft'"
                        class="rounded-card px-2 py-1.5 text-sm transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-signal"
                        aria-label="Aufgabe löschen"
                    >
                        <span x-text="armed ? 'Wirklich löschen?' : 'Löschen'">Löschen</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="cancelEdit" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper">
                            Abbrechen
                        </button>
                        <button type="submit" class="rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                            Speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
