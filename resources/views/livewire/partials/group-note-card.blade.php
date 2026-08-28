{{-- One note card. Variable: $note. Same editing model as the project
     brainstorm field used to be — a read view rendering safe Markdown, an
     edit view with a small toolbar, autosave on every model sync — just
     scoped to one card among several instead of the group's only document.
     Only one card is ever in edit mode at once ($editingNoteId), so the
     @group-notes-focus listener below is never ambiguous about which
     textarea to focus. --}}
@php $editing = $editingNoteId === $note->id; @endphp

<div
    wire:key="group-note-{{ $note->id }}"
    x-data="{
        saved: false,
        _t: null,
        autosize() { const ta = $refs.ta; if (! ta) return; ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 96) + 'px'; },
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
    @group-notes-focus.window="$nextTick(() => document.getElementById('group-note-editor')?.focus())"
>
    @if (! $editing)
        <div class="group/note overflow-hidden rounded-card border border-line bg-surface shadow-map">
            <div class="flex items-center justify-end gap-0.5 border-b border-line px-1.5 py-1">
                <button type="button" wire:click="editNote({{ $note->id }})" class="inline-flex items-center gap-1 rounded-card px-2 py-1 text-xs font-medium text-ink-faint opacity-0 transition hover:bg-paper hover:text-ink focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint group-hover/note:opacity-100">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    Bearbeiten
                </button>
                <button
                    type="button"
                    x-data="{ armed: false, _t: null }"
                    @click="if (armed) { $wire.deleteNote({{ $note->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                    @click.outside="armed = false; clearTimeout(_t)"
                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                    :class="armed ? 'opacity-100 bg-signal text-white' : 'opacity-0 group-hover/note:opacity-100 text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="grid h-7 w-7 place-items-center rounded-card transition focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                    aria-label="Notiz löschen"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <div class="prose-topo cursor-text px-4 py-3.5" @click="if (! $event.target.closest('a')) $wire.editNote({{ $note->id }})">
                {!! $note->contentHtml() !!}
            </div>
        </div>
    @else
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
                <button type="button" @mousedown.prevent="wrap('++', '++')" title="Unterstrichen" aria-label="Unterstrichen" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                    <span class="text-[13px] underline">U</span>
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
                id="group-note-editor"
                x-ref="ta"
                wire:model.live.debounce.800ms="noteDraft"
                @input="autosize()"
                x-init="$nextTick(() => autosize())"
                rows="4"
                placeholder="Kurzer Gedanke, ein Zitat, eine Randbedingung …"
                class="block w-full resize-none border-0 bg-transparent px-3.5 py-3 text-[14px] leading-relaxed text-ink placeholder:text-ink-faint focus:ring-0"
            ></textarea>
            @error('noteDraft') <p class="px-3.5 pb-1.5 text-xs text-signal">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between gap-3 border-t border-line px-3 py-1.5">
                <button
                    type="button"
                    x-data="{ armed: false, _t: null }"
                    @click="if (armed) { $wire.deleteNote({{ $note->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                    @click.outside="armed = false; clearTimeout(_t)"
                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="rounded-card px-2 py-1 text-xs transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                >
                    <span x-text="armed ? 'Wirklich löschen?' : 'Löschen'">Löschen</span>
                </button>
                <div class="flex flex-none items-center gap-2.5">
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-forest" x-show="saved" x-transition.opacity.duration.200ms style="display: none;">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Gespeichert
                    </span>
                    <button type="button" wire:click="stopEditingNote" class="rounded-card bg-forest px-3 py-1 text-xs font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                        Fertig
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
