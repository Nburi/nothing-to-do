{{-- Bastelideen quick-capture: title + optional "wo anfangen". Ideas themselves live on
     their own page (route('crafts')) — this bar only captures, never lists. --}}
<form
    wire:submit="addCraftIdea"
    x-data="{ exp: false }"
    @idea-added.window="exp = false"
    @click.outside="exp = false"
    class="{{ $spacing ?? 'mb-4' }}"
>
    <div
        class="rounded-card border border-line bg-surface shadow-map transition-[border-color]"
        :class="exp ? 'border-ink-faint/60' : 'focus-within:border-ink-faint/60'"
    >
        <div class="flex items-center gap-2 px-3 py-2">
            <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 3a7 7 0 1 0 0 14h1a1.5 1.5 0 0 0 1.06-2.56 1.5 1.5 0 0 1 1.06-2.56H14a3 3 0 0 0 3-3 7 7 0 0 0-7-6Z"/>
                <circle cx="7" cy="9" r=".8" fill="currentColor" stroke="none"/>
                <circle cx="10" cy="7" r=".8" fill="currentColor" stroke="none"/>
                <circle cx="13" cy="9" r=".8" fill="currentColor" stroke="none"/>
            </svg>
            <input
                type="text"
                wire:model="newIdeaTitle"
                placeholder="Neue Bastelidee …"
                autocomplete="off"
                @focus="exp = true"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:ring-0 sm:text-sm"
            />
            <button type="submit" class="flex-none rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Hinzufügen
            </button>
        </div>

        <div
            x-show="exp"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="border-t border-line/60 px-3 py-2.5"
            style="display: none;"
        >
            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Wo anfangen (optional)</label>
            <input
                type="text"
                wire:model="newIdeaWhereToBegin"
                placeholder="z. B. Material besorgen, Anleitung suchen …"
                autocomplete="off"
                class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
            />
        </div>
    </div>
    @error('newIdeaTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
    @error('newIdeaWhereToBegin') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
</form>
