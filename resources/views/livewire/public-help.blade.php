@php
    $selectedId = $this->selectedArticle?->id;
@endphp
<div class="mx-auto max-w-6xl px-5 py-8 sm:px-6">
    <div class="mb-5">
        <h1 class="text-xl font-medium text-ink">Hilfe-Center</h1>
        @if ($selectedId === null)
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-ink-soft">
                Anleitungen und Antworten rund um nothing-to-do — von der Inbox bis zum Fokus-Timer. Wähle links ein Thema.
            </p>
        @endif
    </div>

    <div
        x-data="{ query: '' }"
        class="flex flex-col gap-5 lg:flex-row lg:items-start"
    >
        {{-- Sidebar — hidden on mobile once an article is open, to give the article the full screen. --}}
        <div @class(['w-full flex-none lg:w-64', 'hidden lg:block' => $selectedId !== null])>
            @include('livewire.partials.help-sidebar', [
                'tree' => $this->tree,
                'uncategorizedArticles' => $this->uncategorizedArticles,
                'selectedId' => $selectedId,
                'articleHref' => fn ($article) => url('/hilfe/'.$article->slug),
            ])
        </div>

        {{-- Article --}}
        <div class="min-w-0 flex-1">
            @if ($this->selectedArticle)
                <article class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8" wire:key="article-{{ $this->selectedArticle->id }}">
                    <a href="{{ url('/hilfe') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-xs text-ink-faint transition hover:text-ink lg:hidden">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        Übersicht
                    </a>
                    <h2 class="text-2xl font-medium leading-snug text-ink">{{ $this->selectedArticle->title }}</h2>

                    @if (str_contains($this->contentHtml, 'type="checkbox"'))
                        <p class="mt-3 rounded-card border border-line bg-paper/60 px-3 py-2 text-xs text-ink-soft">
                            Häkchen hier sind nur für dich, für diesen Besuch — sie werden nicht gespeichert.
                        </p>
                    @endif

                    <div class="prose-topo mt-5">
                        {!! $this->contentHtml !!}
                    </div>

                    {{-- Guests have no account to file a SupportRequest against (see
                         App\Livewire\Help::sendFollowupFeedback for the authenticated,
                         interactive version) — "Nein" here is a plain CTA to register,
                         not a backend call. --}}
                    <div class="mt-10 border-t border-line pt-5" x-data="{ state: 'idle' }" wire:key="helpful-{{ $this->selectedArticle->id }}">
                        <div class="flex flex-wrap items-center gap-3" x-show="state === 'idle'">
                            <span class="text-[13.5px] text-ink-soft">War dieser Artikel hilfreich?</span>
                            <button type="button" @click="state = 'yes'" class="rounded-card border border-line bg-paper px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">Ja</button>
                            <button type="button" @click="state = 'no'" class="rounded-card border border-line bg-paper px-3.5 py-1.5 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">Nein</button>
                        </div>
                        <p x-show="state === 'yes'" x-cloak class="text-sm font-medium text-forest">Danke für die Rückmeldung.</p>
                        <div x-show="state === 'no'" x-cloak class="mt-1 rounded-card bg-overprint-soft p-3.5">
                            <p class="text-sm text-ink">
                                Schade. <a href="{{ route('register') }}" wire:navigate class="font-medium text-forest underline underline-offset-2">Leg ein kostenloses Konto an</a> — dort kannst du uns direkt Feedback schicken.
                            </p>
                        </div>
                    </div>
                </article>
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
