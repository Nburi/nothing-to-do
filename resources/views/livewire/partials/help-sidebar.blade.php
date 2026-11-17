{{--
    The Hilfe-Center's category → subcategory → article nav tree, shared by
    the authenticated reader (livewire/help.blade.php, /app/help) and the
    public reader (livewire/public-help.blade.php, /hilfe) — same search box,
    same auto-expand-the-active-path behaviour, so the two can never drift
    apart. See CLAUDE.md, "Hilfe-Center & Support".

    Expects: $tree, $uncategorizedArticles, $selectedId, $articleHref (a
    closure fn($article): string — the two callers link to different route
    names/params, so the URL itself is the one thing left to the caller).
--}}
@php
    // Whether $category (or one of its subcategories) contains the article
    // currently open — used to auto-expand exactly the active path and
    // leave everything else collapsed, so the sidebar stays an overview
    // rather than a wall of open folders.
    $containsSelected = function ($category) use ($selectedId) {
        if ($selectedId === null) {
            return false;
        }
        if ($category->articles->contains('id', $selectedId)) {
            return true;
        }
        foreach ($category->children as $child) {
            if ($child->articles->contains('id', $selectedId)) {
                return true;
            }
        }

        return false;
    };
@endphp
<div class="rounded-card border border-line bg-surface p-3 shadow-map">
    <div class="mb-2 flex items-center gap-2 rounded-card border border-line bg-paper px-2.5 py-1.5">
        <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="m17 17-4-4"/></svg>
        <input x-model="query" type="search" placeholder="Hilfe durchsuchen…" aria-label="Hilfe durchsuchen" class="w-full border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0" />
    </div>

    <nav class="space-y-0.5">
        @foreach ($tree as $category)
            @php
                $hay = strtolower($category->name.' '.$category->articles->pluck('title')->implode(' ').' '.$category->children->flatMap(fn ($c) => $c->articles->pluck('title'))->implode(' '));
            @endphp
            <div x-data="{ open: {{ $containsSelected($category) ? 'true' : 'false' }} }" x-show="query === '' || @js($hay).includes(query.toLowerCase())">
                <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 rounded-card px-2 py-1.5 text-left text-[13px] font-semibold uppercase tracking-wide text-ink-faint transition hover:text-ink">
                    <svg class="h-3 w-3 flex-none transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                    {{ $category->name }}
                </button>
                <div x-show="open" x-transition class="ml-2 border-l border-line pl-2">
                    @foreach ($category->articles as $article)
                        <a href="{{ $articleHref($article) }}" wire:navigate @class([
                            'block rounded-card px-2.5 py-1.5 text-[13.5px] transition',
                            'bg-forest-soft font-medium text-forest' => $article->id === $selectedId,
                            'text-ink-soft hover:bg-paper hover:text-ink' => $article->id !== $selectedId,
                        ])>{{ $article->title }}</a>
                    @endforeach
                    @foreach ($category->children as $sub)
                        @php
                            $subHay = strtolower($sub->name.' '.$sub->articles->pluck('title')->implode(' '));
                        @endphp
                        <div x-data="{ open: {{ $containsSelected($sub) ? 'true' : 'false' }} }" x-show="query === '' || @js($subHay).includes(query.toLowerCase())" class="mt-0.5">
                            <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 rounded-card px-2 py-1.5 text-left text-[13px] font-medium text-ink-soft transition hover:text-ink">
                                <svg class="h-3 w-3 flex-none transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                                {{ $sub->name }}
                            </button>
                            <div x-show="open" x-transition class="ml-2 border-l border-line pl-2">
                                @foreach ($sub->articles as $article)
                                    <a href="{{ $articleHref($article) }}" wire:navigate @class([
                                        'block rounded-card px-2.5 py-1.5 text-[13.5px] transition',
                                        'bg-forest-soft font-medium text-forest' => $article->id === $selectedId,
                                        'text-ink-soft hover:bg-paper hover:text-ink' => $article->id !== $selectedId,
                                    ])>{{ $article->title }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($uncategorizedArticles->isNotEmpty())
            <div x-data="{ open: {{ $uncategorizedArticles->contains('id', $selectedId) ? 'true' : 'false' }} }">
                <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 rounded-card px-2 py-1.5 text-left text-[13px] font-semibold uppercase tracking-wide text-ink-faint transition hover:text-ink">
                    <svg class="h-3 w-3 flex-none transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                    Weiteres
                </button>
                <div x-show="open" x-transition class="ml-2 border-l border-line pl-2">
                    @foreach ($uncategorizedArticles as $article)
                        <a href="{{ $articleHref($article) }}" wire:navigate @class([
                            'block rounded-card px-2.5 py-1.5 text-[13.5px] transition',
                            'bg-forest-soft font-medium text-forest' => $article->id === $selectedId,
                            'text-ink-soft hover:bg-paper hover:text-ink' => $article->id !== $selectedId,
                        ])>{{ $article->title }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($tree->isEmpty() && $uncategorizedArticles->isEmpty())
            <p class="px-2 py-1.5 text-[13px] text-ink-faint">Noch keine Hilfe-Artikel.</p>
        @endif
    </nav>
</div>
