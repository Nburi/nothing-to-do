{{-- One article row inside the Hilfe-Center tree view. $article: HelpArticle. --}}
<div wire:key="article-row-{{ $article->id }}" class="flex items-center justify-between gap-2 rounded-card px-2 py-1.5 transition hover:bg-paper">
    <button type="button" wire:click="startEdit({{ $article->id }})" class="flex min-w-0 flex-1 items-center gap-2 text-left">
        <span @class([
            'h-1.5 w-1.5 flex-none rounded-full',
            'bg-forest' => $article->is_published,
            'bg-line' => ! $article->is_published,
        ])></span>
        <span class="truncate text-[13.5px] text-ink-soft">{{ $article->title !== '' ? $article->title : 'Ohne Titel' }}</span>
    </button>
    <button
        type="button"
        x-data="{ armed: false, _t: null }"
        @click="if (armed) { $wire.deleteArticle({{ $article->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
        @click.outside="armed = false; clearTimeout(_t)"
        @keydown.escape.window="armed = false; clearTimeout(_t)"
        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
        class="grid h-6 w-6 flex-none place-items-center rounded-[0.4rem] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
        aria-label="Artikel löschen"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.3A1.5 1.5 0 0 0 7.6 17h4.8a1.5 1.5 0 0 0 1.5-1.7L14.5 6"/></svg>
    </button>
</div>
