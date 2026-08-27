@php
    $toneClasses = match ($this->article->type) {
        'blog' => 'bg-forest-soft text-forest',
        'doc' => 'bg-contour-soft text-contour',
        default => 'bg-line text-ink-soft',
    };
@endphp
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-6">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('library') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zur Bibliothek" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <span @class(['rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none', $toneClasses])>{{ $this->article->typeLabel() }}</span>
        @if (! $this->article->is_published)
            <span class="rounded-full bg-line px-1.5 py-0.5 text-[10px] font-medium leading-none text-ink-faint">Entwurf — nur für dich sichtbar</span>
        @endif
    </div>

    <h1 class="text-2xl font-medium leading-snug text-ink">{{ $this->article->title !== '' ? $this->article->title : 'Ohne Titel' }}</h1>
    @if ($this->article->published_at)
        <p class="mt-1.5 text-xs text-ink-faint">
            {{ $this->article->published_at->isoFormat('D. MMMM YYYY') }}
            @if ($this->article->author?->name) · {{ $this->article->author->name }} @endif
        </p>
    @endif

    @if (str_contains($this->contentHtml, 'type="checkbox"'))
        <p class="mt-4 rounded-card border border-line bg-paper/60 px-3 py-2 text-xs text-ink-soft">
            Häkchen hier sind nur für dich, für diesen Besuch — sie werden nicht gespeichert und sind nach dem Neuladen wieder leer.
        </p>
    @endif

    @if ($this->contentHtml !== '')
        <div class="prose-topo mt-6">
            {!! $this->contentHtml !!}
        </div>
    @else
        <p class="mt-6 text-sm text-ink-faint">Dieser Artikel hat noch keinen Inhalt.</p>
    @endif
</div>
