@php
    $selectedId = $this->selectedArticle?->id;

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
<div class="mx-auto max-w-6xl px-5 py-8 sm:px-6">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-xl font-medium text-ink">Hilfe</h1>
        </div>
        <a href="{{ route('support') }}" wire:navigate class="rounded-card border border-line bg-surface px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">
            Feedback &amp; Support
        </a>
    </div>

    <div
        x-data="{ query: '' }"
        class="flex flex-col gap-5 lg:flex-row lg:items-start"
    >
        {{-- Sidebar — hidden on mobile once an article is open, to give the article the full screen. --}}
        <div @class(['w-full flex-none lg:w-64', 'hidden lg:block' => $selectedId !== null])>
            <div class="rounded-card border border-line bg-surface p-3 shadow-map">
                <div class="mb-2 flex items-center gap-2 rounded-card border border-line bg-paper px-2.5 py-1.5">
                    <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="m17 17-4-4"/></svg>
                    <input x-model="query" type="search" placeholder="Hilfe durchsuchen…" aria-label="Hilfe durchsuchen" class="w-full border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0" />
                </div>

                <nav class="space-y-0.5">
                    @foreach ($this->tree as $category)
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
                                    <a href="{{ route('help', $article) }}" wire:navigate @class([
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
                                                <a href="{{ route('help', $article) }}" wire:navigate @class([
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

                    @if ($this->uncategorizedArticles->isNotEmpty())
                        <div x-data="{ open: {{ $this->uncategorizedArticles->contains('id', $selectedId) ? 'true' : 'false' }} }">
                            <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 rounded-card px-2 py-1.5 text-left text-[13px] font-semibold uppercase tracking-wide text-ink-faint transition hover:text-ink">
                                <svg class="h-3 w-3 flex-none transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                                Weiteres
                            </button>
                            <div x-show="open" x-transition class="ml-2 border-l border-line pl-2">
                                @foreach ($this->uncategorizedArticles as $article)
                                    <a href="{{ route('help', $article) }}" wire:navigate @class([
                                        'block rounded-card px-2.5 py-1.5 text-[13.5px] transition',
                                        'bg-forest-soft font-medium text-forest' => $article->id === $selectedId,
                                        'text-ink-soft hover:bg-paper hover:text-ink' => $article->id !== $selectedId,
                                    ])>{{ $article->title }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($this->tree->isEmpty() && $this->uncategorizedArticles->isEmpty())
                        <p class="px-2 py-1.5 text-[13px] text-ink-faint">Noch keine Hilfe-Artikel.</p>
                    @endif
                </nav>
            </div>
        </div>

        {{-- Article --}}
        <div class="min-w-0 flex-1">
            @if ($this->selectedArticle)
                <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8" wire:key="article-{{ $this->selectedArticle->id }}">
                    <a href="{{ route('help') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-xs text-ink-faint transition hover:text-ink lg:hidden">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        Übersicht
                    </a>
                    <h1 class="text-2xl font-medium leading-snug text-ink">{{ $this->selectedArticle->title }}</h1>

                    @if (str_contains($this->contentHtml, 'type="checkbox"'))
                        <p class="mt-3 rounded-card border border-line bg-paper/60 px-3 py-2 text-xs text-ink-soft">
                            Häkchen hier sind nur für dich, für diesen Besuch — sie werden nicht gespeichert.
                        </p>
                    @endif

                    <div class="prose-topo mt-5">
                        {!! $this->contentHtml !!}
                    </div>

                    {{-- Signature moment: "War das hilfreich?" — a "Nein" opens straight
                         into a note that becomes a feedback request, no page change. --}}
                    <div class="mt-10 border-t border-line pt-5" x-data="{ state: 'idle' }" wire:key="helpful-{{ $this->selectedArticle->id }}">
                        <div class="flex flex-wrap items-center gap-3" x-show="state === 'idle'">
                            <span class="text-[13.5px] text-ink-soft">War dieser Artikel hilfreich?</span>
                            <button type="button" @click="state = 'yes'" class="rounded-card border border-line bg-paper px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">Ja</button>
                            <button type="button" @click="state = 'no'; $wire.openFollowup()" class="rounded-card border border-line bg-paper px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">Nein</button>
                        </div>
                        <p x-show="state === 'yes'" x-cloak class="text-sm font-medium text-forest">Danke für die Rückmeldung.</p>

                        @if ($showFollowup)
                            <div class="mt-1 rounded-card bg-overprint-soft p-3.5" x-show="state === 'no'">
                                @if (! $justSentFeedback)
                                    <p class="mb-2 text-sm font-medium text-overprint">Was hat gefehlt?</p>
                                    <textarea wire:model="followupNote" rows="3" placeholder="Kurz beschreiben, was du gesucht hast (optional)…" class="block w-full resize-none rounded-card border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:outline-none focus:ring-0"></textarea>
                                    <button type="button" wire:click="sendFollowupFeedback" class="mt-2 rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Als Feedback senden</button>
                                @else
                                    <p class="text-sm font-medium text-forest">Danke — als Feedback gespeichert. Du findest es unter „Meine Anfragen".</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-card border border-dashed border-line bg-paper/40 px-6 py-16 text-center">
                    <p class="text-sm text-ink-soft">
                        @if ($this->tree->isEmpty() && $this->uncategorizedArticles->isEmpty())
                            Es gibt noch keine Hilfe-Artikel.
                        @else
                            Wähle links einen Artikel aus.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
