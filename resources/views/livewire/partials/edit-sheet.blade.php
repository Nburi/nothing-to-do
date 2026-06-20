{{-- Shared inline edit sheet (TaskBoard + ProjectPage via the ManagesTasks trait). --}}
@if ($editingId)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Aufgabe bearbeiten">
        <div class="absolute inset-0 bg-ink/40" wire:click="cancelEdit"></div>
        <div class="animate-rise relative w-full max-w-md rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.cancelEdit()">
            <form wire:submit="saveEdit" class="space-y-4">
                <h2 class="text-base font-medium text-ink">Aufgabe bearbeiten</h2>

                <div>
                    <label for="editTitle" class="mb-1 block text-xs font-medium text-ink-soft">Titel</label>
                    <input id="editTitle" type="text" wire:model="editTitle" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                    @error('editTitle') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                @if ($this->editableProjects->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-soft">Projekt</label>
                        <div
                            x-data="{
                                open: false,
                                projectId: $wire.entangle('editProjectId'),
                                projects: @js($this->editableProjects->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()),
                                get selectedLabel() {
                                    if (!this.projectId) return 'Kein Projekt';
                                    const p = this.projects.find(p => p.id == this.projectId);
                                    return p ? p.name : 'Kein Projekt';
                                },
                            }"
                            class="relative"
                        >
                            <button
                                type="button"
                                @click.stop="open = !open"
                                @click.outside="open = false"
                                @keydown.escape.window="open = false"
                                :aria-expanded="open"
                                aria-haspopup="listbox"
                                class="flex w-full items-center gap-2 rounded-card border border-line bg-paper px-3 py-2 text-sm transition hover:border-ink-faint/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                :class="projectId ? 'text-ink' : 'text-ink-soft'"
                            >
                                <span x-text="selectedLabel" class="min-w-0 flex-1 text-left"></span>
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
                                class="absolute inset-x-0 top-full z-20 mt-1 max-h-52 origin-top overflow-y-auto rounded-card border border-line bg-surface p-1 shadow-map"
                                role="listbox"
                                style="display: none;"
                            >
                                <button
                                    type="button"
                                    @click="projectId = ''; open = false"
                                    role="option"
                                    :aria-selected="!projectId"
                                    class="flex w-full items-center gap-2 rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition"
                                    :class="!projectId ? 'bg-paper font-medium text-ink' : 'text-ink-soft hover:bg-paper hover:text-ink'"
                                >
                                    <span>Kein Projekt</span>
                                    <svg x-show="!projectId" class="ml-auto h-3 w-3 flex-none text-forest" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                        <path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <template x-for="p in projects" :key="p.id">
                                    <button
                                        type="button"
                                        @click="projectId = p.id; open = false"
                                        role="option"
                                        :aria-selected="projectId == p.id"
                                        class="flex w-full items-center gap-2 rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition"
                                        :class="projectId == p.id ? 'bg-paper font-medium text-ink' : 'text-ink-soft hover:bg-paper hover:text-ink'"
                                    >
                                        <span x-text="p.name" class="min-w-0 flex-1 truncate"></span>
                                        <svg x-show="projectId == p.id" class="ml-auto h-3 w-3 flex-none text-forest" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                            <path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                        @error('editProjectId') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endif

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

                <div class="flex items-center justify-between pt-1">
                    <button type="button" wire:click="deleteTask({{ $editingId }})" class="rounded-card px-2 py-1.5 text-sm text-signal transition hover:bg-signal-soft">
                        Löschen
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
