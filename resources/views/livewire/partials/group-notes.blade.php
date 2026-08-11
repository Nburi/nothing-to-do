{{-- The group's Markdown notes — the fourth "list", where the main board has
     its Projekte column. Same editing model as the project brainstorm: a read
     view that renders safe Markdown, an edit view with a small toolbar, and
     autosave on every model sync. --}}
<div
    x-data="{
        saved: false,
        _t: null,
        autosize() { const ta = $refs.ta; if (! ta) return; ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 220) + 'px'; },
        wrap(before, after) {
            const ta = $refs.ta; if (! ta) return;
            const s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e);
            ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
            ta.focus(); ta.setSelectionRange(s + before.length, s + before.length + sel.length);
            ta.dispatchEvent(new Event('input')); this.autosize();
        },
        prefixLines(prefix) {
            const ta = $refs.ta; if (! ta) return;
            const v = ta.value, ls = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
            let le = v.indexOf('\n', ta.selectionEnd); if (le === -1) le = v.length;
            const block = v.slice(ls, le).split('\n').map(l => prefix + l).join('\n');
            ta.value = v.slice(0, ls) + block + v.slice(le);
            ta.focus(); ta.setSelectionRange(ls, ls + block.length);
            ta.dispatchEvent(new Event('input')); this.autosize();
        },
        flashSaved() { this.saved = true; clearTimeout(this._t); this._t = setTimeout(() => this.saved = false, 1600); },
    }"
    @group-notes-saved.window="flashSaved()"
    @group-notes-focus.window="$nextTick(() => document.getElementById('group-notes-editor')?.focus())"
>
    @if (! $editingNotes)
        <div wire:key="group-notes-read">
            @if ($this->notesHtml !== '')
                <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
                    <div class="flex items-center justify-end border-b border-line px-2 py-1.5">
                        <button type="button" wire:click="editNotesPanel" class="inline-flex items-center gap-1 rounded-card px-2 py-1 text-xs font-medium text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                            Bearbeiten
                        </button>
                    </div>
                    <div class="prose-topo cursor-text px-4 py-3.5" @click="if (! $event.target.closest('a')) $wire.editNotesPanel()">
                        {!! $this->notesHtml !!}
                    </div>
                </div>
            @else
                <button type="button" wire:click="editNotesPanel" class="group/empty flex w-full flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line bg-paper/40 px-4 py-10 text-center transition hover:border-ink-faint/60 hover:bg-paper/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                    <svg class="h-8 w-8 text-line transition group-hover/empty:text-ink-faint" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <path d="M30 9.5 38.5 18 18 38.5l-9 1.5 1.5-9L30 9.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="m26.5 13 8.5 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="text-sm font-medium text-ink">Notiz beginnen</span>
                    <span class="max-w-[30ch] text-xs leading-relaxed text-ink-faint">Was zu dieser Gruppe gehört, aber keine Aufgabe ist. Markdown wird unterstützt.</span>
                </button>
            @endif
        </div>
    @else
        <div wire:key="group-notes-edit">
            <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map focus-within:border-ink-faint/60">
                <div class="flex flex-wrap items-center gap-0.5 border-b border-line px-1.5 py-1.5">
                    <button type="button" @mousedown.prevent="prefixLines('## ')" title="Überschrift" aria-label="Überschrift" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <span class="text-[13px] font-bold">H</span>
                    </button>
                    <button type="button" @mousedown.prevent="wrap('**', '**')" title="Fett" aria-label="Fett" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <span class="text-[13px] font-bold">B</span>
                    </button>
                    <button type="button" @mousedown.prevent="wrap('*', '*')" title="Kursiv" aria-label="Kursiv" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <span class="font-serif text-[13px] italic">i</span>
                    </button>
                    <span class="mx-1 h-4 w-px bg-line" aria-hidden="true"></span>
                    <button type="button" @mousedown.prevent="prefixLines('- ')" title="Liste" aria-label="Liste" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="3" cy="4.5" r="1" fill="currentColor"/><circle cx="3" cy="11.5" r="1" fill="currentColor"/><path d="M6.5 4.5h7M6.5 11.5h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </button>
                    <button type="button" @mousedown.prevent="prefixLines('- [ ] ')" title="Aufgabe" aria-label="Aufgabe" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 8 1.8 1.8L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" @mousedown.prevent="wrap('[', '](url)')" title="Link" aria-label="Link" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 9.5 9.5 6.5M7 4.6l.9-.9a2.4 2.4 0 0 1 3.4 3.4l-.9.9M9 11.4l-.9.9a2.4 2.4 0 0 1-3.4-3.4l.9-.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>

                <textarea
                    id="group-notes-editor"
                    x-ref="ta"
                    wire:model.live.debounce.800ms="notes"
                    @input="autosize()"
                    x-init="$nextTick(() => autosize())"
                    rows="8"
                    placeholder="Gedanken, offene Fragen, Rahmenbedingungen … einfach lostippen."
                    class="block w-full resize-none border-0 bg-transparent px-3.5 py-3 text-[14px] leading-relaxed text-ink placeholder:text-ink-faint focus:ring-0"
                ></textarea>
            </div>
            @error('notes') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror

            <div class="mt-2 flex items-center justify-between gap-3 px-0.5">
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-forest" x-show="saved" x-transition.opacity.duration.200ms style="display: none;">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Gespeichert
                </span>
                <button type="button" wire:click="stopEditingNotes" class="ml-auto rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    Fertig
                </button>
            </div>
        </div>
    @endif
</div>
