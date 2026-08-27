<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-1.5 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Bibliothek</h1>
    </div>
    <p class="mb-6 pl-11 text-sm text-ink-soft leading-relaxed">Blog, Docs und Leitfäden.</p>

    @if ($this->hasAnyPublished)
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1.5">
                @php
                    $chipToneClasses = fn (?string $key) => match ($key) {
                        'blog' => 'bg-forest text-white shadow-sm',
                        'doc' => 'bg-contour text-white shadow-sm',
                        'guideline' => 'bg-ink text-white shadow-sm',
                        default => 'bg-ink text-white shadow-sm',
                    };
                @endphp
                <button
                    type="button"
                    wire:click="setTypeFilter(null)"
                    @class([
                        'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                        $chipToneClasses(null) => $typeFilter === null,
                        'bg-surface text-ink-soft hover:text-ink' => $typeFilter !== null,
                    ])
                >Alle</button>
                @foreach (\App\Models\Article::TYPES as $key => $meta)
                    <button
                        type="button"
                        wire:click="setTypeFilter('{{ $key }}')"
                        @class([
                            'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                            $chipToneClasses($key) => $typeFilter === $key,
                            'bg-surface text-ink-soft hover:text-ink' => $typeFilter !== $key,
                        ])
                    >{{ $meta['label'] }}</button>
                @endforeach
            </div>

            <div class="relative ml-auto w-full sm:w-56">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="m17 17-4-4"/></svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suchen…"
                    aria-label="Bibliothek durchsuchen"
                    class="block w-full rounded-card border border-line bg-surface py-1.5 pl-8 pr-3 text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:outline-none focus:ring-0"
                />
            </div>
        </div>
    @endif

    <div class="space-y-2">
        @forelse ($this->articles as $article)
            @php
                $toneClasses = match ($article->type) {
                    'blog' => 'bg-forest-soft text-forest',
                    'doc' => 'bg-contour-soft text-contour',
                    default => 'bg-line text-ink-soft',
                };
            @endphp
            <a
                wire:key="article-{{ $article->id }}"
                href="{{ route('library.show', $article) }}"
                wire:navigate
                class="block rounded-card border border-line bg-surface p-4 shadow-map transition hover:border-ink-faint/60 sm:p-5"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span @class(['rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none', $toneClasses])>{{ $article->typeLabel() }}</span>
                    <span class="text-xs text-ink-faint">{{ $article->published_at?->isoFormat('D.M.YYYY') }}</span>
                </div>
                <p class="mt-1.5 text-[15px] font-medium text-ink">{{ $article->title !== '' ? $article->title : 'Ohne Titel' }}</p>
                @if ($article->preview())
                    <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $article->preview() }}</p>
                @endif
            </a>
        @empty
            @if ($this->hasAnyPublished)
                <div class="rounded-card border border-dashed border-line bg-paper/40 px-4 py-10 text-center">
                    <p class="text-sm text-ink-soft">Keine Treffer{{ $search !== '' ? ' für „'.$search.'"' : '' }}.</p>
                    <button type="button" wire:click="resetFilters" class="mt-2 text-sm text-overprint transition hover:underline">Filter zurücksetzen</button>
                </div>
            @else
                <div class="rounded-card border border-dashed border-line bg-paper/40 px-4 py-14 text-center">
                    <p class="text-sm text-ink-soft">Hier stehen bald Blogposts, Docs und Leitfäden.</p>
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin.library') }}" wire:navigate class="mt-2 inline-block text-sm text-overprint transition hover:underline">Jetzt schreiben →</a>
                    @endif
                </div>
            @endif
        @endforelse
    </div>
</div>
