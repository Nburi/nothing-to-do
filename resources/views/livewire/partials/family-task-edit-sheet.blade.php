{{-- The deliberate "assign to someone else" path — separate from the quick
     tap-to-claim gesture on the card itself, so anyone can hand a task to
     anyone (e.g. a parent assigning a chore to a kid), not just claim their
     own. Mirrors edit-sheet.blade.php's bottom-sheet shell. --}}
<div
    x-show="$wire.editingTaskId !== null"
    x-cloak
    class="fixed inset-0 z-40 flex items-end justify-center sm:items-center"
    @keydown.escape.window="$wire.closeEditTask()"
>
    <div class="absolute inset-0 bg-ink/30" @click="$wire.closeEditTask()"></div>

    <div class="animate-rise relative w-full max-w-md rounded-t-card border border-line bg-surface p-5 shadow-map sm:rounded-card">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-medium text-ink">Aufgabe bearbeiten</h2>
            <button type="button" wire:click="closeEditTask" class="grid h-7 w-7 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schliessen">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>

        @if ($this->editingTask)
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-ink-faint">Titel</label>
                    <input
                        type="text"
                        wire:model="editTitle"
                        maxlength="255"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0"
                    />
                    @error('editTitle') <p class="mt-1 text-[11px] text-signal">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-ink-faint">Notiz</label>
                    <textarea
                        wire:model="editNotes"
                        rows="2"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0"
                        placeholder="z. B. Marke, Menge, wo …"
                    ></textarea>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-ink-faint">Wer soll das übernehmen?</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            wire:click="assignTask({{ $this->editingTask->id }}, null)"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition',
                                'border-ink-faint bg-paper font-medium text-ink' => $this->editingTask->assigned_to === null,
                                'border-line text-ink-soft hover:border-ink-faint/60' => $this->editingTask->assigned_to !== null,
                            ])
                        >
                            <span class="h-2.5 w-2.5 rounded-full border border-ink-faint" aria-hidden="true"></span>
                            Niemand
                        </button>
                        @foreach ($members as $member)
                            @php $memberColor = $memberColors[$member->id] ?? null; @endphp
                            <button
                                type="button"
                                wire:click="assignTask({{ $this->editingTask->id }}, {{ $member->id }})"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition',
                                    'border-ink-faint bg-paper font-medium text-ink' => $this->editingTask->assigned_to === $member->id,
                                    'border-line text-ink-soft hover:border-ink-faint/60' => $this->editingTask->assigned_to !== $member->id,
                                ])
                            >
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $memberColor ? \App\Livewire\Support\FamilyColors::rgb($memberColor) : 'transparent' }}" aria-hidden="true"></span>
                                {{ $member->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between gap-2">
                <button
                    type="button"
                    x-data="{ armed: false, _t: null }"
                    @click="if (armed) { $wire.deleteTask({{ $this->editingTask->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                    @click.outside="armed = false; clearTimeout(_t)"
                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="rounded-card px-3 py-2 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                >
                    <span x-text="armed ? 'Wirklich löschen?' : 'Löschen'"></span>
                </button>

                <button
                    type="button"
                    wire:click="saveTaskEdit"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                >
                    Speichern
                </button>
            </div>
        @endif
    </div>
</div>
