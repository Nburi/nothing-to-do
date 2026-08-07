{{-- Add/edit sheet for a homework or exam entry — same shell as the schedule event form. --}}
<div x-data="{ open: $wire.entangle('showForm') }" x-cloak>
    <div
        x-show="open"
        x-transition.opacity.duration.150ms
        @click="$wire.cancelForm()"
        class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"
        style="display: none;"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="opacity-0 translate-y-6 md:translate-y-2 md:scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed inset-x-0 bottom-0 z-50 md:inset-0 md:m-auto md:h-fit md:max-w-md"
        style="display: none;"
    >
        <div class="mx-auto max-h-[88dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map md:rounded-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Eintrag bearbeiten' : 'Neuer Eintrag' }}</h2>
                <button wire:click="cancelForm" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="saveEntry" class="space-y-4">
                <div class="inline-flex rounded-card border border-line bg-paper p-0.5">
                    @foreach (\App\Models\AgendaEntry::TYPES as $val => $lbl)
                        <button
                            type="button"
                            wire:click="$set('formType', '{{ $val }}')"
                            @class([
                                'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                'bg-forest text-white shadow-sm' => $formType === $val,
                                'text-ink-soft hover:text-ink' => $formType !== $val,
                            ])
                        >{{ $lbl }}</button>
                    @endforeach
                </div>

                <div
                    x-data="{
                        open: false,
                        subjects: @js($this->existingSubjects),
                        get filtered() {
                            const q = ($wire.formSubject || '').trim().toLowerCase();
                            return q === '' ? this.subjects : this.subjects.filter(s => s.toLowerCase().includes(q));
                        },
                        handleTab(event) {
                            if (!this.open || event.shiftKey) return;
                            event.preventDefault();
                            const query = ($wire.formSubject || '').trim();
                            if (query !== '' && this.filtered.length > 0) {
                                $wire.set('formSubject', this.filtered[0]);
                            }
                            this.open = false;
                            this.$nextTick(() => document.getElementById('agenda-form-title')?.focus());
                        },
                    }"
                    wire:key="agenda-subject-field-{{ $this->existingSubjects->count() }}"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="relative"
                >
                    <label class="mb-1 block text-[12px] font-medium text-ink-faint">Fach</label>
                    <input
                        type="text"
                        wire:model="formSubject"
                        @focus="open = true"
                        @input="open = true"
                        @keydown.tab="handleTab($event)"
                        autocomplete="off"
                        placeholder="z. B. Mathematik"
                        autofocus
                        class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                    @error('formSubject') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror

                    <div
                        x-show="open && subjects.length > 0"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute inset-x-0 z-10 mt-1 max-h-40 overflow-y-auto rounded-card border border-line bg-surface p-1 shadow-map"
                        style="display: none;"
                    >
                        <template x-if="filtered.length === 0">
                            <p class="px-2.5 py-1.5 text-sm text-ink-faint">Neues Fach — einfach weitertippen</p>
                        </template>
                        <template x-for="(s, i) in filtered" :key="s">
                            <button
                                type="button"
                                @click="$wire.set('formSubject', s); open = false"
                                class="flex w-full items-center justify-between gap-2 rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink"
                            >
                                <span class="truncate" x-text="s"></span>
                                <span
                                    x-show="i === 0 && ($wire.formSubject || '').trim() !== ''"
                                    class="flex-none rounded border border-line px-1 py-0.5 text-[10px] font-medium text-ink-faint"
                                    style="display: none;"
                                >Tab</span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[12px] font-medium text-ink-faint">Titel</label>
                    <input
                        type="text"
                        id="agenda-form-title"
                        wire:model="formTitle"
                        placeholder="z. B. Kapitel 5, Aufgaben 1–10"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                    @error('formTitle') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[12px] font-medium text-ink-faint">Datum</label>
                    <input
                        type="date"
                        wire:model="formDate"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0"
                    />
                    @error('formDate') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[12px] font-medium text-ink-faint">Notiz (optional)</label>
                    <textarea
                        wire:model="formNotes"
                        rows="2"
                        placeholder="Details, Seitenzahlen, Material…"
                        class="w-full resize-y rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    ></textarea>
                    @error('formNotes') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-card bg-forest py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    {{ $editingId ? 'Speichern' : 'Hinzufügen' }}
                </button>
            </form>
        </div>
    </div>
</div>
