{{-- Shared notes editor: a small toolbar inserts markdown syntax into a textarea,
     plus a rendered preview. ++text++ is a custom marker (App\Support\Markdown\
     UnderlineExtension) — standard/GFM Markdown has no underline syntax of its own.
     Used by the task edit sheet and the quick-capture panel; parameterize:
     - $fieldName    the component property bound via wire:model (e.g. 'editNotes')
     - $htmlProperty the Computed property rendering it to safe HTML (e.g. 'editNotesHtml')
     - $idPrefix     unique id prefix so both can exist in the DOM at once (e.g. 'edit', 'qc') --}}
<div
    x-data="{
        autosize() { const ta = this.$refs.notesTa; if (! ta) return; ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 76) + 'px'; },
        wrap(before, after) {
            const ta = this.$refs.notesTa; if (! ta) return;
            const s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e);
            ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
            ta.focus(); ta.setSelectionRange(s + before.length, s + before.length + sel.length);
            ta.dispatchEvent(new Event('input')); this.autosize();
        },
        prefixLines(prefix) {
            const ta = this.$refs.notesTa; if (! ta) return;
            const v = ta.value, ls = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
            let le = v.indexOf('\n', ta.selectionEnd); if (le === -1) le = v.length;
            const block = v.slice(ls, le).split('\n').map(l => prefix + l).join('\n');
            ta.value = v.slice(0, ls) + block + v.slice(le);
            ta.focus(); ta.setSelectionRange(ls, ls + block.length);
            ta.dispatchEvent(new Event('input')); this.autosize();
        },
    }"
    x-init="$nextTick(() => autosize())"
>
    <div class="mb-1 flex items-center justify-between">
        <label for="{{ $idPrefix }}-notes" class="block text-xs font-medium text-ink-soft">Notizen</label>
        <button type="button" wire:click="$refresh" class="rounded px-1 text-[11px] text-ink-faint transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
            Vorschau aktualisieren
        </button>
    </div>
    <div class="overflow-hidden rounded-card border border-line bg-paper focus-within:border-ink-faint/60">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-line px-1.5 py-1">
            <button type="button" @mousedown.prevent="wrap('**', '**')" title="Fett" aria-label="Fett" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <span class="text-[12px] font-bold">B</span>
            </button>
            <button type="button" @mousedown.prevent="wrap('*', '*')" title="Kursiv" aria-label="Kursiv" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <span class="font-serif text-[12px] italic">i</span>
            </button>
            <button type="button" @mousedown.prevent="wrap('++', '++')" title="Unterstrichen" aria-label="Unterstrichen" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <span class="text-[12px] underline">U</span>
            </button>
            <span class="mx-1 h-3.5 w-px bg-line" aria-hidden="true"></span>
            <button type="button" @mousedown.prevent="prefixLines('- ')" title="Liste" aria-label="Liste" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="3" cy="4.5" r="1" fill="currentColor"/><circle cx="3" cy="11.5" r="1" fill="currentColor"/><path d="M6.5 4.5h7M6.5 11.5h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
            <button type="button" @mousedown.prevent="prefixLines('- [ ] ')" title="Aufgabe" aria-label="Aufgabe" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 8 1.8 1.8L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <textarea
            id="{{ $idPrefix }}-notes"
            x-ref="notesTa"
            wire:model="{{ $fieldName }}"
            @input="autosize()"
            rows="3"
            placeholder="Notizen, Kommentare …"
            class="block max-h-64 w-full resize-none border-0 bg-transparent px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
        ></textarea>
    </div>
    @error($fieldName) <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror

    @if ($this->{$htmlProperty} !== '')
        <div class="prose-topo mt-2 rounded-card border border-line bg-paper/60 px-3 py-2 text-[13px]">
            {!! $this->{$htmlProperty} !!}
        </div>
    @endif
</div>
