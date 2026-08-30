{{-- Category custom-attributes sheet — opened from a category's row in Settings
     (manageAttributes). Lets the user define fully custom fields on a category
     (e.g. "Trainingstyp", "Dauer"); the actual values are filled in per event
     occurrence in the schedule event form. Mirrors category-link-sheet.blade.php's
     modal shape (animate-rise, no leave-transition). --}}
@php
    $swatches = [
        'contour' => 'bg-contour',
        'overprint' => 'bg-overprint',
        'forest' => 'bg-forest',
        'signal' => 'bg-signal',
        'ink' => 'bg-ink-faint',
    ];
@endphp
@if ($this->managingAttributesCategory)
    @php($managing = $this->managingAttributesCategory)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Attribute">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeAttributes"></div>
        <div class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.closeAttributes()">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">Attribute für „{{ $managing->name }}“</h2>
                <button type="button" wire:click="closeAttributes" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm leading-relaxed text-ink-soft">
                Eigene Felder für Termine dieser Kategorie — z. B. Trainingstyp oder Dauer. Werte trägst du beim
                einzelnen Termin ein, nie hier.
            </p>

            @if ($managing->customAttributes->isNotEmpty())
                <div class="mb-4 space-y-1.5">
                    @foreach ($managing->customAttributes as $attr)
                        <div wire:key="attr-{{ $attr->id }}" class="flex items-center gap-2 rounded-card border border-line bg-paper/60 px-3 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-ink">{{ $attr->name }}</p>
                                <p class="truncate text-xs text-ink-faint">
                                    {{ $attr->typeLabel() }}
                                    @if ($attr->type === 'number' && $attr->unit) · {{ $attr->unit }} @endif
                                    @if ($attr->type === 'select') · {{ collect($attr->optionsList())->pluck('label')->implode(', ') }} @endif
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="startEditAttribute({{ $attr->id }})"
                                class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                                aria-label="„{{ $attr->name }}“ bearbeiten"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                            <button
                                type="button"
                                x-data="{ armed: false, _t: null }"
                                @click="if (armed) { $wire.deleteAttribute({{ $attr->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                @click.outside="armed = false; clearTimeout(_t)"
                                @keydown.escape.window="armed = false; clearTimeout(_t)"
                                :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                class="grid h-8 w-8 flex-none place-items-center rounded-card transition"
                                aria-label="„{{ $attr->name }}“ löschen"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="saveAttribute" class="space-y-3 border-t border-line pt-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
                    {{ $editingAttributeId ? 'Attribut bearbeiten' : 'Neues Attribut' }}
                </p>

                <div>
                    <input
                        type="text"
                        wire:model="attrName"
                        placeholder="Name — z. B. Trainingstyp"
                        autocomplete="off"
                        class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                    @error('attrName') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div class="inline-flex flex-wrap gap-1.5 rounded-card border border-line bg-paper p-0.5">
                    @foreach (\App\Models\CategoryAttribute::TYPES as $type => $label)
                        <button
                            type="button"
                            wire:click="$set('attrType', '{{ $type }}')"
                            @class([
                                'rounded-[0.45rem] px-3 py-1.5 text-sm transition',
                                'bg-forest text-white shadow-sm' => $attrType === $type,
                                'text-ink-soft hover:text-ink' => $attrType !== $type,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>

                @if ($attrType === 'number')
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-ink-faint">Einheit (optional)</label>
                        <input
                            type="text"
                            wire:model="attrUnit"
                            placeholder="z. B. Min"
                            autocomplete="off"
                            class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                        />
                    </div>
                @endif

                @if ($attrType === 'select')
                    <div>
                        <label class="mb-1.5 block text-[11px] font-medium text-ink-faint">Optionen, mit eigener Farbe</label>
                        <div class="space-y-1.5">
                            @foreach ($attrOptions as $i => $option)
                                <div wire:key="opt-{{ $i }}" class="flex items-center gap-1.5">
                                    <div class="flex flex-none gap-1">
                                        @foreach ($swatches as $token => $bg)
                                            <button
                                                type="button"
                                                wire:click="setAttrOptionColor({{ $i }}, '{{ $token }}')"
                                                @class([
                                                    'h-5 w-5 flex-none rounded-full transition', $bg,
                                                    'ring-2 ring-offset-2 ring-offset-surface ring-ink/60' => ($option['color'] ?? null) === $token,
                                                    'hover:scale-110' => ($option['color'] ?? null) !== $token,
                                                ])
                                                aria-label="Farbe {{ $token }}"
                                            ></button>
                                        @endforeach
                                    </div>
                                    <input
                                        type="text"
                                        wire:model="attrOptions.{{ $i }}.label"
                                        placeholder="z. B. Lauf"
                                        autocomplete="off"
                                        class="min-w-0 flex-1 rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                                    />
                                    <button
                                        type="button"
                                        wire:click="removeAttrOptionRow({{ $i }})"
                                        aria-label="Option entfernen"
                                        class="grid h-7 w-7 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-signal-soft hover:text-signal"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button
                            type="button"
                            wire:click="addAttrOptionRow"
                            class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-line bg-paper/70 px-2.5 py-1 text-xs text-ink-soft transition hover:border-overprint/50 hover:bg-paper hover:text-ink"
                        >
                            <svg class="h-3 w-3 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <span>Option hinzufügen</span>
                        </button>
                        @error('attrOptions') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-card bg-forest px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                        {{ $editingAttributeId ? 'Speichern' : 'Hinzufügen' }}
                    </button>
                    @if ($editingAttributeId)
                        <button type="button" wire:click="startAddAttribute" class="flex-none rounded-card border border-line px-4 py-2.5 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                            Abbrechen
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endif
