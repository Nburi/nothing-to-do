<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-5 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Ankündigungen</h1>
    </div>
    <p class="mb-8 text-sm text-ink-soft leading-relaxed">
        Kurze „das ist neu"-Hinweise für alle Nutzer. Eine veröffentlichte Ankündigung erscheint einmal pro
        Nutzer als kleiner Hinweis — bis sie ihn wegklicken, danach nie wieder.
    </p>

    {{-- Form — one form doubles as create and edit, same pattern as the Agenda page. --}}
    <div class="mb-8 rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
        <h2 class="mb-5 text-base font-medium text-ink">{{ $editingId ? 'Ankündigung bearbeiten' : 'Neue Ankündigung' }}</h2>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="formTitle" class="mb-1.5 block text-sm font-medium text-ink">Titel</label>
                <input
                    id="formTitle"
                    type="text"
                    wire:model="formTitle"
                    maxlength="255"
                    class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    placeholder="z. B. Neu: Wochenplan"
                />
                @error('formTitle')
                    <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink">Art</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($this->typeOptions as $key => $meta)
                        @php
                            $active = $formType === $key;
                            $activeClasses = match ($key) {
                                'maintenance' => 'bg-contour text-white shadow-sm',
                                'warning' => 'bg-signal text-white shadow-sm',
                                'release' => 'bg-forest text-white shadow-sm',
                                default => 'bg-ink text-white shadow-sm',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('formType', '{{ $key }}')"
                            @class([
                                'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                $activeClasses => $active,
                                'bg-paper text-ink-soft hover:text-ink' => ! $active,
                            ])
                        >{{ $meta['label'] }}</button>
                    @endforeach
                </div>
                @error('formType')
                    <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="formDescription" class="mb-1.5 block text-sm font-medium text-ink">Kurzbeschreibung</label>
                <textarea
                    id="formDescription"
                    wire:model="formDescription"
                    maxlength="500"
                    rows="3"
                    class="block w-full resize-y rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                    placeholder="Ein bis zwei Sätze — wird als kleiner Hinweis angezeigt, kein Fliesstext."
                ></textarea>
                @error('formDescription')
                    <p class="mt-1.5 text-xs text-signal">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="formRelatedModule" class="mb-1.5 block text-sm font-medium text-ink">Betrifft (optional)</label>
                <select
                    id="formRelatedModule"
                    wire:model="formRelatedModule"
                    class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"
                >
                    <option value="">Kein bestimmter Bereich</option>
                    @foreach ($this->moduleOptions as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-ink-soft">Wenn gesetzt, bekommt der Hinweis einen Link direkt dorthin.</p>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button
                    type="submit"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >{{ $editingId ? 'Speichern' : 'Anlegen' }}</button>
                @if ($editingId)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="rounded-card px-4 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink"
                    >Abbrechen</button>
                @endif
            </div>
        </form>

        {{-- Live preview — the exact card a user would see, built from the
             current (unsaved) form fields. wire:model above is deferred, so
             this needs an explicit refresh rather than updating per keystroke
             — same "Vorschau aktualisieren" pattern as the Notizen editor's
             preview (see CLAUDE.md's Known Issues on wire:model.blur). --}}
        <div class="mt-6 border-t border-line pt-5" x-data="{ open: false }">
            <button
                type="button"
                @click="open = ! open"
                class="flex items-center gap-1.5 text-sm font-medium text-ink-soft transition hover:text-ink"
            >
                <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                Vorschau
            </button>
            <div x-show="open" x-transition x-cloak class="mt-3 space-y-3">
                <p class="text-xs text-ink-soft">So sieht dieser Hinweis für Nutzer aus.</p>
                @include('livewire.partials.announcement-toast-card', [
                    'announcement' => $this->previewAnnouncement,
                    'remaining' => 0,
                    'interactive' => false,
                ])
                <button
                    type="button"
                    wire:click="$refresh"
                    class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                >Vorschau aktualisieren</button>
            </div>
        </div>
    </div>

    {{-- List — every announcement, draft and published, newest first. --}}
    <div class="space-y-2">
        @forelse ($this->announcements as $announcement)
            <div wire:key="announcement-{{ $announcement->id }}" x-data="{ previewOpen: false }" class="rounded-card border border-line bg-surface p-4 shadow-map sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-ink">{{ $announcement->title }}</p>
                            @php
                                $typeClasses = match ($announcement->type) {
                                    'maintenance' => 'bg-contour-soft text-contour',
                                    'warning' => 'bg-signal-soft text-signal',
                                    'release' => 'bg-forest-soft text-forest',
                                    default => 'bg-line text-ink-soft',
                                };
                            @endphp
                            <span @class(['rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none', $typeClasses])>{{ $announcement->typeLabel() }}</span>
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                'bg-forest-soft text-forest' => $announcement->is_published,
                                'bg-line text-ink-faint' => ! $announcement->is_published,
                            ])>{{ $announcement->is_published ? 'Veröffentlicht' : 'Entwurf' }}</span>
                            @if ($announcement->relatedModuleLabel())
                                <span class="rounded-full bg-paper px-1.5 py-0.5 text-[10px] font-medium leading-none text-ink-soft">{{ $announcement->relatedModuleLabel() }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $announcement->description }}</p>
                        <p class="mt-1.5 text-xs text-ink-faint">
                            {{ $announcement->author?->name ? 'von '.$announcement->author->name.' · ' : '' }}
                            {{ $announcement->is_published && $announcement->published_at ? 'veröffentlicht am '.$announcement->published_at->isoFormat('D.M.YYYY') : 'noch nicht veröffentlicht' }}
                        </p>
                    </div>

                    <div class="flex flex-none items-center gap-1">
                        <button
                            type="button"
                            @click="previewOpen = ! previewOpen"
                            :class="previewOpen ? 'border-ink-faint/60 text-ink' : ''"
                            class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                        >Vorschau</button>
                        <button
                            type="button"
                            wire:click="togglePublish({{ $announcement->id }})"
                            class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                        >{{ $announcement->is_published ? 'Zurückziehen' : 'Veröffentlichen' }}</button>
                        <button
                            type="button"
                            wire:click="startEdit({{ $announcement->id }})"
                            class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                            aria-label="Ankündigung bearbeiten"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.5 1.5 0 0 1 2 2L6 15l-3 1 1-3 9.5-9.5Z"/></svg>
                        </button>
                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click="if (armed) { $wire.deleteAnnouncement({{ $announcement->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                            class="grid h-8 w-8 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                            aria-label="Ankündigung löschen"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.3A1.5 1.5 0 0 0 7.6 17h4.8a1.5 1.5 0 0 0 1.5-1.7L14.5 6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Rendered from this row's own already-loaded data, toggled
                     purely client-side — no round trip needed, unlike the
                     form's own preview above (which has no persisted model
                     to read from until saved). --}}
                <div x-show="previewOpen" x-transition x-cloak class="mt-3 border-t border-line pt-3">
                    <p class="mb-2 text-xs text-ink-soft">So sieht dieser Hinweis für Nutzer aus{{ $announcement->is_published ? '' : ' (Entwurf, noch nicht sichtbar)' }}.</p>
                    @include('livewire.partials.announcement-toast-card', [
                        'announcement' => $announcement,
                        'remaining' => 0,
                        'interactive' => false,
                    ])
                </div>
            </div>
        @empty
            <p class="text-sm text-ink-faint">Noch keine Ankündigungen.</p>
        @endforelse
    </div>
</div>
