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
                        <label for="editProject" class="mb-1 block text-xs font-medium text-ink-soft">Projekt</label>
                        <select id="editProject" wire:model="editProjectId" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0">
                            <option value="">Kein Projekt</option>
                            @foreach ($this->editableProjects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
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
