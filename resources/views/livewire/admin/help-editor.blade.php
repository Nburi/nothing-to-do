<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    @if ($editingId === null)
        {{-- TREE — categories, subcategories, and the articles inside them. --}}
        <div class="mb-5 flex items-center gap-3">
            <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-xl font-medium text-ink">Hilfe-Center verwalten</h1>
        </div>
        <p class="mb-6 pl-11 text-sm text-ink-soft leading-relaxed">
            Kategorien und Unterkategorien für die Sidebar, mit den Artikeln darin. Sichtbar für alle, sobald veröffentlicht.
        </p>

        <div class="mb-6">
            @if ($creatingRootCategory)
                <form wire:submit="saveRootCategory" class="flex items-center gap-2">
                    <input type="text" wire:model="newRootCategoryName" autofocus placeholder="Name der Kategorie" maxlength="255" class="block w-full max-w-xs rounded-card border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                    <button type="submit" class="rounded-card bg-forest px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110">Anlegen</button>
                    <button type="button" wire:click="cancelCategoryForm" class="text-sm text-ink-soft transition hover:text-ink">Abbrechen</button>
                </form>
            @else
                <button type="button" wire:click="startCreatingRootCategory" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">+ Kategorie</button>
            @endif
        </div>

        <div class="space-y-3">
            @foreach ($this->tree as $category)
                <div wire:key="cat-{{ $category->id }}" class="rounded-card border border-line bg-surface p-4 shadow-map">
                    <div class="flex items-center justify-between gap-2" x-data="{ editing: false, draft: @js($category->name) }">
                        <div class="flex min-w-0 flex-1 items-center gap-1.5">
                            <span x-show="! editing" class="truncate text-sm font-semibold text-ink">{{ $category->name }}</span>
                            <input x-show="editing" x-ref="input{{ $category->id }}" x-model="draft" @keydown.enter="$wire.renameCategory({{ $category->id }}, draft); editing = false" @keydown.escape="editing = false" @blur="if (editing) { $wire.renameCategory({{ $category->id }}, draft); editing = false }" class="block w-full max-w-xs rounded-card border border-line bg-paper px-2 py-1 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                            <button type="button" x-show="! editing" @click="editing = true; draft = @js($category->name); $nextTick(() => $refs['input{{ $category->id }}'].focus())" class="grid h-6 w-6 flex-none place-items-center rounded-[0.4rem] text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Kategorie umbenennen">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.5 1.5 0 0 1 2 2L6 15l-3 1 1-3 9.5-9.5Z"/></svg>
                            </button>
                        </div>
                        <div class="flex flex-none items-center gap-1">
                            <button type="button" wire:click="startCreatingSubcategory({{ $category->id }})" class="rounded-card px-2 py-1 text-xs text-ink-soft transition hover:bg-paper hover:text-ink">+ Unterkategorie</button>
                            <button type="button" wire:click="createArticle({{ $category->id }})" class="rounded-card px-2 py-1 text-xs text-ink-soft transition hover:bg-paper hover:text-ink">+ Artikel</button>
                            <button
                                type="button"
                                x-data="{ armed: false, _t: null }"
                                @click="if (armed) { $wire.deleteCategory({{ $category->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                @click.outside="armed = false; clearTimeout(_t)"
                                @keydown.escape.window="armed = false; clearTimeout(_t)"
                                :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                class="grid h-7 w-7 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                                aria-label="Kategorie löschen"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.3A1.5 1.5 0 0 0 7.6 17h4.8a1.5 1.5 0 0 0 1.5-1.7L14.5 6"/></svg>
                            </button>
                        </div>
                    </div>

                    @if ($creatingSubcategoryFor === $category->id)
                        <form wire:submit="saveSubcategory" class="mt-2.5 flex items-center gap-2 pl-1">
                            <input type="text" wire:model="newSubcategoryName" autofocus placeholder="Name der Unterkategorie" maxlength="255" class="block w-full max-w-xs rounded-card border border-line bg-paper px-3 py-1.5 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                            <button type="submit" class="rounded-card bg-forest px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110">Anlegen</button>
                            <button type="button" wire:click="cancelCategoryForm" class="text-sm text-ink-soft transition hover:text-ink">Abbrechen</button>
                        </form>
                    @endif

                    @if ($category->articles->isNotEmpty())
                        <div class="mt-2.5 space-y-1 pl-1">
                            @foreach ($category->articles as $article)
                                @include('livewire.admin.partials.help-article-row', ['article' => $article])
                            @endforeach
                        </div>
                    @endif

                    @if ($category->children->isNotEmpty())
                        <div class="mt-3 space-y-2.5 border-t border-line pt-3 pl-3">
                            @foreach ($category->children as $sub)
                                <div wire:key="cat-{{ $sub->id }}">
                                    <div class="flex items-center justify-between gap-2" x-data="{ editing: false, draft: @js($sub->name) }">
                                        <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                            <span x-show="! editing" class="truncate text-[13.5px] font-medium text-ink-soft">{{ $sub->name }}</span>
                                            <input x-show="editing" x-ref="input{{ $sub->id }}" x-model="draft" @keydown.enter="$wire.renameCategory({{ $sub->id }}, draft); editing = false" @keydown.escape="editing = false" @blur="if (editing) { $wire.renameCategory({{ $sub->id }}, draft); editing = false }" class="block w-full max-w-xs rounded-card border border-line bg-paper px-2 py-1 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                                            <button type="button" x-show="! editing" @click="editing = true; draft = @js($sub->name); $nextTick(() => $refs['input{{ $sub->id }}'].focus())" class="grid h-6 w-6 flex-none place-items-center rounded-[0.4rem] text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Unterkategorie umbenennen">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.5 1.5 0 0 1 2 2L6 15l-3 1 1-3 9.5-9.5Z"/></svg>
                                            </button>
                                        </div>
                                        <div class="flex flex-none items-center gap-1">
                                            <button type="button" wire:click="createArticle({{ $sub->id }})" class="rounded-card px-2 py-1 text-xs text-ink-soft transition hover:bg-paper hover:text-ink">+ Artikel</button>
                                            <button
                                                type="button"
                                                x-data="{ armed: false, _t: null }"
                                                @click="if (armed) { $wire.deleteCategory({{ $sub->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                                @click.outside="armed = false; clearTimeout(_t)"
                                                @keydown.escape.window="armed = false; clearTimeout(_t)"
                                                :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                                class="grid h-7 w-7 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                                                aria-label="Unterkategorie löschen"
                                            >
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.3A1.5 1.5 0 0 0 7.6 17h4.8a1.5 1.5 0 0 0 1.5-1.7L14.5 6"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    @if ($sub->articles->isNotEmpty())
                                        <div class="mt-2 space-y-1 pl-1">
                                            @foreach ($sub->articles as $article)
                                                @include('livewire.admin.partials.help-article-row', ['article' => $article])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($this->tree->isEmpty())
                <p class="text-sm text-ink-faint">Noch keine Kategorien. Leg mit „+ Kategorie" los.</p>
            @endif

            {{-- Uncategorized — always visible so nothing an orphaned article (e.g. from a
                 deleted category) is ever silently hidden from the admin. --}}
            <div class="rounded-card border border-dashed border-line bg-paper/40 p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-ink-faint">Ohne Kategorie</span>
                    <button type="button" wire:click="createArticle(null)" class="rounded-card px-2 py-1 text-xs text-ink-soft transition hover:bg-surface hover:text-ink">+ Artikel</button>
                </div>
                @if ($this->uncategorizedArticles->isNotEmpty())
                    <div class="mt-2.5 space-y-1">
                        @foreach ($this->uncategorizedArticles as $article)
                            @include('livewire.admin.partials.help-article-row', ['article' => $article])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- EDITOR — same Schreibfluss-Modus as the rest of this app's Markdown
             editors: the chrome below fades shortly after you start typing and
             returns instantly on any click or mouse movement. --}}
        <div
            wire:key="editor-{{ $editingId }}"
            x-data="{
                chromeHidden: false,
                pendingHide: false,
                hideTimer: null,
                typed() {
                    if (this.chromeHidden || this.pendingHide) return;
                    this.pendingHide = true;
                    this.hideTimer = setTimeout(() => { this.chromeHidden = true; this.pendingHide = false; }, 900);
                },
                wake() {
                    clearTimeout(this.hideTimer);
                    this.pendingHide = false;
                    this.chromeHidden = false;
                },
                autosize(ta) { if (! ta) return; ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 320) + 'px'; },
                wrap(before, after) {
                    const ta = this.$refs.contentTa; if (! ta) return;
                    const s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e);
                    ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
                    ta.focus(); ta.setSelectionRange(s + before.length, s + before.length + sel.length);
                    ta.dispatchEvent(new Event('input')); this.autosize(ta);
                },
                prefixLines(prefix) {
                    const ta = this.$refs.contentTa; if (! ta) return;
                    const v = ta.value, ls = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
                    let le = v.indexOf('\n', ta.selectionEnd); if (le === -1) le = v.length;
                    const block = v.slice(ls, le).split('\n').map(l => prefix + l).join('\n');
                    ta.value = v.slice(0, ls) + block + v.slice(le);
                    ta.focus(); ta.setSelectionRange(ls, ls + block.length);
                    ta.dispatchEvent(new Event('input')); this.autosize(ta);
                },
                insertTable() {
                    const ta = this.$refs.contentTa; if (! ta) return;
                    const s = ta.selectionEnd, v = ta.value;
                    const needsNewlineBefore = s > 0 && v[s - 1] !== '\n';
                    const block = (needsNewlineBefore ? '\n' : '') + '\n| Spalte 1 | Spalte 2 |\n| --- | --- |\n| Zelle | Zelle |\n\n';
                    ta.value = v.slice(0, s) + block + v.slice(s);
                    ta.focus(); ta.dispatchEvent(new Event('input')); this.autosize(ta);
                },
            }"
            @mousemove.window="wake()"
            @click.window="wake()"
        >
            <div :class="chromeHidden ? 'opacity-0 pointer-events-none' : 'opacity-100'" class="mb-5 flex items-center justify-between gap-3 transition-opacity duration-500">
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="backToList" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zur Übersicht">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none',
                        'bg-forest-soft text-forest' => $formIsPublished,
                        'bg-line text-ink-faint' => ! $formIsPublished,
                    ])>{{ $formIsPublished ? 'Veröffentlicht' : 'Entwurf' }}</span>
                    @if ($formPublishedAt)
                        <span class="text-xs text-ink-faint">seit {{ $formPublishedAt }}</span>
                    @endif
                </div>
                <div class="flex flex-none items-center gap-1.5">
                    <button type="button" wire:click="togglePreview" @class(['rounded-card border border-line bg-paper px-3 py-1.5 text-xs transition hover:border-ink-faint/60', 'text-ink border-ink-faint/60' => $showPreview, 'text-ink-soft' => ! $showPreview])>Vorschau</button>
                    <button type="button" wire:click="togglePublish" class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs text-ink-soft transition hover:border-ink-faint/60 hover:text-ink">{{ $formIsPublished ? 'Zurückziehen' : 'Veröffentlichen' }}</button>
                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.deleteArticle({{ $editingId }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                        class="grid h-8 w-8 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                        aria-label="Artikel löschen"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.3A1.5 1.5 0 0 0 7.6 17h4.8a1.5 1.5 0 0 0 1.5-1.7L14.5 6"/></svg>
                    </button>
                </div>
            </div>

            <div :class="chromeHidden ? 'opacity-0 pointer-events-none h-0 overflow-hidden' : 'opacity-100'" class="mb-4 transition-opacity duration-500">
                <label class="mb-1 block text-xs font-medium text-ink-soft">Kategorie</label>
                <select wire:model.live="formCategoryId" class="block w-full max-w-xs rounded-card border border-line bg-paper px-3 py-1.5 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0">
                    @foreach ($this->categoryOptions as $id => $label)
                        <option value="{{ $id }}" @selected((string) $formCategoryId === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div :class="chromeHidden ? 'mx-auto max-w-2xl' : 'max-w-3xl'" class="transition-all duration-500">
                <input
                    type="text"
                    wire:model.live.debounce.600ms="formTitle"
                    @input="typed()"
                    maxlength="255"
                    placeholder="Titel"
                    class="block w-full border-0 bg-transparent px-0 text-2xl font-medium text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0"
                />

                @if (! $showPreview)
                    <div :class="chromeHidden ? 'opacity-0 pointer-events-none h-0 overflow-hidden' : 'opacity-100 mt-3'" class="flex flex-wrap items-center gap-0.5 rounded-card border border-line bg-paper px-1.5 py-1 transition-opacity duration-500">
                        <button type="button" @mousedown.prevent="wrap('**', '**')" title="Fett" aria-label="Fett" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><span class="text-[12px] font-bold">B</span></button>
                        <button type="button" @mousedown.prevent="wrap('*', '*')" title="Kursiv" aria-label="Kursiv" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><span class="font-serif text-[12px] italic">i</span></button>
                        <button type="button" @mousedown.prevent="wrap('++', '++')" title="Unterstrichen" aria-label="Unterstrichen" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><span class="text-[12px] underline">U</span></button>
                        <span class="mx-1 h-3.5 w-px bg-line" aria-hidden="true"></span>
                        <button type="button" @mousedown.prevent="prefixLines('## ')" title="Überschrift" aria-label="Überschrift" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><span class="text-[12px] font-bold">H</span></button>
                        <button type="button" @mousedown.prevent="prefixLines('- ')" title="Liste" aria-label="Liste" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="3" cy="4.5" r="1" fill="currentColor"/><circle cx="3" cy="11.5" r="1" fill="currentColor"/><path d="M6.5 4.5h7M6.5 11.5h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
                        <button type="button" @mousedown.prevent="prefixLines('- [ ] ')" title="Aufgabe" aria-label="Aufgabe" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 8 1.8 1.8L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @mousedown.prevent="wrap('[', '](url)')" title="Link" aria-label="Link" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 9.5 9.5 6.5M7 4.6l.9-.9a2.4 2.4 0 0 1 3.4 3.4l-.9.9M9 11.4l-.9.9a2.4 2.4 0 0 1-3.4-3.4l.9-.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @mousedown.prevent="insertTable()" title="Tabelle" aria-label="Tabelle einfügen" class="grid h-6 w-6 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-surface hover:text-ink"><svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2" y="3" width="12" height="10" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2 6.5h12M6.3 3v10" stroke="currentColor" stroke-width="1.3"/></svg></button>
                    </div>
                @endif

                @if ($showPreview)
                    <div class="prose-topo mt-3 max-h-[60vh] overflow-y-auto rounded-card border border-line bg-paper/60 px-4 py-3">
                        @if ($this->previewHtml !== '')
                            {!! $this->previewHtml !!}
                        @else
                            <p class="text-sm text-ink-faint">Noch kein Inhalt.</p>
                        @endif
                    </div>
                @else
                    <textarea
                        x-ref="contentTa"
                        x-init="autosize($refs.contentTa)"
                        wire:model.live.debounce.600ms="formContent"
                        @input="typed(); autosize($el)"
                        placeholder="Schreib los — volles Markdown, inkl. Tabellen und Checklisten."
                        class="mt-3 block min-h-[50vh] w-full resize-none border-0 bg-transparent px-0 text-[15px] leading-relaxed text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0"
                    ></textarea>
                @endif
            </div>
        </div>
    @endif
</div>
