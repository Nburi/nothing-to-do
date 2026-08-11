{{-- Manage shared class agendas: invite codes, joining, leaving. Same sheet shell as agenda-entry-form. --}}
<div x-data="{ open: $wire.entangle('showSpaces') }" x-cloak>
    <div
        x-show="open"
        x-transition.opacity.duration.150ms
        @click="$wire.closeSpaces()"
        class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"
        style="display: none;"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="opacity-0 translate-y-6 md:translate-y-2 md:scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed inset-x-0 bottom-0 z-50 md:inset-0 md:m-auto md:h-fit md:max-w-md"
        style="display: none;"
    >
        <div class="mx-auto max-h-[88dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map md:rounded-card">
            <div class="mb-4 flex items-center justify-between gap-2">
                @if ($this->managedSpace)
                    <button
                        wire:click="closeMembers"
                        class="-ml-1 flex min-w-0 items-center gap-1.5 rounded-card px-1 py-0.5 text-base font-medium text-ink transition hover:text-forest"
                    >
                        <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        <span class="truncate">{{ $this->managedSpace->name }}</span>
                    </button>
                @else
                    <h2 class="text-base font-medium text-ink">Klassen und Gruppen</h2>
                @endif
                <button wire:click="closeSpaces" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            @if ($this->managedSpace)
                @include('livewire.partials.agenda-space-members')
            @else

            @forelse ($this->spaces as $space)
                @php $isOwner = $space->owner_id === auth()->id(); @endphp
                <div wire:key="space-{{ $space->id }}" class="mb-2 rounded-card border border-line p-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 flex-1 truncate text-[14px] font-medium text-ink">{{ $space->name }}</span>
                        <span class="flex-none text-[11.5px] text-ink-faint">
                            {{ $space->members_count }} {{ $space->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}{{ $isOwner ? ' · du bist Besitzer' : '' }}
                        </span>
                    </div>

                    @php $online = $this->onlineCountFor($space); @endphp
                    <button
                        type="button"
                        wire:click="openMembers({{ $space->id }})"
                        class="mt-1.5 flex items-center gap-1.5 rounded-card py-0.5 text-[12px] text-ink-soft transition hover:text-forest"
                    >
                        @if ($online > 0)
                            <span class="h-1.5 w-1.5 flex-none rounded-full bg-forest" aria-hidden="true"></span>
                            <span>{{ $online }} online</span>
                            <span class="text-ink-faint">· Mitglieder verwalten</span>
                        @else
                            <span>Mitglieder verwalten</span>
                        @endif
                        <svg class="h-3 w-3 flex-none text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>

                    {{-- The code is the invite; the link is the same thing, pre-typed. Copying is
                         client-only (navigator.clipboard) so it costs no round trip, with a
                         select-the-text fallback for browsers/contexts that block the API. --}}
                    <div
                        x-data="{
                            copied: null,
                            async copy(what, text) {
                                let ok = false;
                                try {
                                    await navigator.clipboard.writeText(text);
                                    ok = true;
                                } catch (e) {
                                    // Clipboard API needs a secure context and can be blocked
                                    // outright; fall back to the old selection trick rather than
                                    // a native prompt() dialog (CLAUDE.md: no browser dialogs).
                                    const ta = document.createElement('textarea');
                                    ta.value = text;
                                    ta.setAttribute('readonly', '');
                                    ta.style.cssText = 'position:fixed;top:-9999px;opacity:0';
                                    document.body.appendChild(ta);
                                    ta.select();
                                    try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
                                    ta.remove();
                                }
                                this.copied = ok ? what : 'failed';
                                setTimeout(() => { this.copied = null; }, ok ? 1800 : 4000);
                            },
                        }"
                        class="mt-2.5 flex flex-wrap items-center gap-1.5"
                    >
                        <button
                            type="button"
                            @click="copy('code', '{{ $space->invite_code }}')"
                            class="tnum rounded-card border border-line bg-paper px-2.5 py-1.5 text-[14px] font-medium tracking-[0.16em] text-ink transition hover:border-forest hover:text-forest"
                            aria-label="Code {{ $space->invite_code }} kopieren"
                        >{{ $space->invite_code }}</button>

                        <button
                            type="button"
                            @click="copy('link', '{{ $space->inviteUrl() }}')"
                            class="rounded-card border border-line px-2.5 py-1.5 text-[12px] text-ink-soft transition hover:bg-paper hover:text-ink"
                        >Link kopieren</button>

                        <span
                            x-show="copied"
                            x-transition
                            :class="copied === 'failed' ? 'text-signal' : 'text-forest'"
                            class="text-[11.5px]"
                            style="display: none;"
                        >
                            <span x-text="copied === 'failed' ? 'Kopieren ging nicht — Code von Hand abtippen' : (copied === 'link' ? 'Link kopiert' : 'Code kopiert')"></span>
                        </span>

                        <div class="ml-auto flex items-center gap-0.5">
                            <button
                                type="button"
                                wire:click="regenerateInviteCode({{ $space->id }})"
                                class="rounded-card px-2 py-1.5 text-[11.5px] text-ink-faint transition hover:bg-paper hover:text-ink"
                                title="Neuen Code erzeugen — alte Links gelten dann nicht mehr"
                            >Neuer Code</button>

                            <button
                                type="button"
                                x-data="{ armed: false, _t: null }"
                                @click="if (armed) { $wire.leaveSpace({{ $space->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                @click.outside="armed = false; clearTimeout(_t)"
                                @keydown.escape.window="armed = false; clearTimeout(_t)"
                                :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                class="rounded-card px-2 py-1.5 text-[11.5px] transition"
                            ><span x-text="armed ? 'Wirklich?' : 'Verlassen'"></span></button>

                            @if ($isOwner)
                                <button
                                    type="button"
                                    x-data="{ armed: false, _t: null }"
                                    @click="if (armed) { $wire.deleteSpace({{ $space->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                    @click.outside="armed = false; clearTimeout(_t)"
                                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                                    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                    class="grid h-7 w-7 place-items-center rounded-card transition"
                                    aria-label="Klasse {{ $space->name }} für alle löschen"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="mb-3 rounded-card border border-dashed border-line p-6 text-center">
                    <p class="text-[13px] leading-relaxed text-ink-faint">
                        Noch keine Klasse. Erstell eine und teile den Code — oder tritt mit dem Code
                        eines Mitschülers bei.
                    </p>
                </div>
            @endforelse

            <div class="mt-4 space-y-3 border-t border-line pt-4">
                <form wire:submit="joinSpace" class="space-y-1.5">
                    <label for="agenda-join-code" class="block text-[12px] font-medium text-ink-faint">Einer Klasse beitreten</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            id="agenda-join-code"
                            wire:model="joinCode"
                            placeholder="Code, z. B. K7M4XQ"
                            autocomplete="off"
                            autocapitalize="characters"
                            class="tnum w-full rounded-card border-line bg-paper text-sm uppercase tracking-[0.12em] text-ink placeholder:normal-case placeholder:tracking-normal placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                        />
                        <button type="submit" class="flex-none rounded-card border border-line px-3.5 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                            Beitreten
                        </button>
                    </div>
                    @error('joinCode') <p class="text-xs text-signal">{{ $message }}</p> @enderror
                </form>

                <form wire:submit="createSpace" class="space-y-1.5">
                    <label for="agenda-new-space" class="block text-[12px] font-medium text-ink-faint">Neue Klasse erstellen</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            id="agenda-new-space"
                            wire:model="newSpaceName"
                            placeholder="z. B. Klasse 4b"
                            class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                        />
                        <button type="submit" class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                            Erstellen
                        </button>
                    </div>
                    @error('newSpaceName') <p class="text-xs text-signal">{{ $message }}</p> @enderror
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
