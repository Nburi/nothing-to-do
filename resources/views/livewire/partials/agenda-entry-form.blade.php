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
        {{-- This user's own draft is kept fresh by the ambient banner's poll
             (agenda.blade.php calls heartbeatDraft() from there, not here) —
             one poll per page rather than two independent timers on the same
             component, which is what caused them to occasionally collide. --}}
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

                {{-- Who the entry is for. Only rendered once the user is actually in a
                     class — otherwise every entry is private and the choice is noise. --}}
                @if ($this->spaces->isNotEmpty())
                    <div>
                        <label class="mb-1.5 block text-[12px] font-medium text-ink-faint">Für</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                wire:click="$set('formSpaceId', null)"
                                @class([
                                    'rounded-card border px-3 py-1.5 text-[13px] transition',
                                    'border-forest bg-forest text-white' => $formSpaceId === null,
                                    'border-line text-ink-soft hover:bg-paper hover:text-ink' => $formSpaceId !== null,
                                ])
                            >Nur ich</button>

                            @foreach ($this->spaces as $space)
                                <button
                                    type="button"
                                    wire:key="form-space-{{ $space->id }}"
                                    wire:click="$set('formSpaceId', {{ $space->id }})"
                                    @class([
                                        'rounded-card border px-3 py-1.5 text-[13px] transition',
                                        'border-contour bg-contour text-white' => $formSpaceId === $space->id,
                                        'border-line text-ink-soft hover:bg-paper hover:text-ink' => $formSpaceId !== $space->id,
                                    ])
                                >{{ $space->shortName() }}</button>
                            @endforeach
                        </div>
                        {{-- Always named, private included — so an accidentally-private
                             choice is never the quiet option next to an explained one. --}}
                        <p class="mt-1.5 text-[11.5px] text-ink-faint">
                            @if ($formSpaceId !== null)
                                Alle in dieser Klasse sehen den Eintrag. Abhaken tut jede:r für sich.
                            @else
                                Nur du siehst diesen Eintrag.
                            @endif
                        </p>
                        @error('formSpaceId') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endif

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
                        wire:model.live.debounce.800ms="formSubject"
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

                @if ($formType === 'homework')
                    <div>
                        <label class="mb-1 block text-[12px] font-medium text-ink-faint">Geschätzte Dauer (Minuten, optional)</label>
                        <input
                            type="number" min="1" max="600" step="5"
                            wire:model="formDuration"
                            placeholder="z. B. 25"
                            class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                        />
                        @error('formDuration') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-[12px] font-medium text-ink-faint">
                        Notiz (optional)
                        {{-- A shared entry's Notiz goes to the whole class — said here,
                             right where you're about to type, not just once further up
                             next to the "Für" pills. Typing something private into the
                             wrong box is a worse outcome than not finding the right one. --}}
                        @if ($formSpaceId !== null)
                            <span class="font-normal text-ink-faint">· sichtbar für die ganze Klasse</span>
                        @endif
                    </label>
                    <textarea
                        wire:model="formNotes"
                        rows="2"
                        placeholder="Details, Seitenzahlen, Material…"
                        class="w-full resize-y rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    ></textarea>
                    @error('formNotes') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                {{-- Only meaningful once the entry is actually shared — a "Nur ich"
                     entry already has exactly one viewer, so a second private field
                     on it would just duplicate the note above. No empty slot either
                     way: it doesn't exist until you tap "+ Eigene Notiz", the same
                     ghost-reveal the board uses for a task's own date. The collapsed
                     label names "nur du" up front, not only after opening it — the
                     whole point is telling the two notes apart before you write in
                     either one, not after. --}}
                @if ($formSpaceId !== null)
                    <div x-data="{ open: {{ $formPrivateNotes !== '' ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            x-show="!open"
                            @click="open = true; $nextTick(() => $refs.agendaPrivateNotesField?.focus())"
                            class="inline-flex items-center gap-1 rounded px-0.5 py-0.5 text-[12px] font-medium text-ink-faint transition hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                        >
                            <svg class="h-2.5 w-2.5 flex-none" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            Eigene Notiz <span class="text-ink-faint">· nur du</span>
                        </button>
                        <div x-show="open">
                            <label class="mb-1 block text-[12px] font-medium text-ink-faint">
                                Eigene Notiz <span class="font-normal text-ink-faint">· nur du siehst sie</span>
                            </label>
                            <textarea
                                x-ref="agendaPrivateNotesField"
                                wire:model="formPrivateNotes"
                                rows="2"
                                placeholder="Nur für dich…"
                                class="w-full resize-y rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                            ></textarea>
                            @error('formPrivateNotes') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-full rounded-card bg-forest py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    {{ $editingId ? 'Speichern' : 'Hinzufügen' }}
                </button>
            </form>
        </div>
    </div>
</div>
