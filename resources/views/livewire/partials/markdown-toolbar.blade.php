{{-- Formatting toolbar for the small Markdown editors (project brainstorm, group note cards).
     Expects to sit inside an Alpine scope spread from markdownEditor() (see app.js) with the
     textarea carrying x-ref="ta". Buttons prevent the mousedown default so focus stays in the textarea. --}}
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
