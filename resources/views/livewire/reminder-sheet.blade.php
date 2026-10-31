{{--
    "Alle Erinnerungen" sheet — see App\Livewire\ReminderSheet. The permanent
    outer div is required (Livewire rejects a root view that can compile to
    nothing); everything inside only exists while the sheet is open.
--}}
<div>
@if ($open)
    @php $today = auth()->user()->localToday(); @endphp
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Erinnerungen">
        <div class="absolute inset-0 bg-ink/40" wire:click="close"></div>
        <div class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.close()">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">Nicht vergessen</h2>
                <button type="button" wire:click="close" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm leading-relaxed text-ink-soft">
                Kleine Dinge für einen bestimmten Tag — keine Aufgaben. Zwei Tage vorher erscheinen sie über dem Zeitstrahl.
            </p>

            {{-- Add / edit --}}
            <form wire:submit="save" class="mb-5 space-y-3 rounded-card border border-line bg-paper p-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">{{ $editingId ? 'Erinnerung ändern' : 'Neue Erinnerung' }}</p>
                <div>
                    <label for="reminder-title" class="sr-only">Woran darfst du nicht vergessen?</label>
                    <input
                        id="reminder-title"
                        type="text"
                        wire:model="formTitle"
                        maxlength="{{ \App\Services\Reminders::TITLE_MAX }}"
                        placeholder="Woran darfst du nicht vergessen?"
                        autocomplete="off"
                        class="w-full rounded-card border-line bg-surface text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                    @error('title') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    <div class="w-40">
                        <label for="reminder-date" class="mb-1 block text-[11px] font-medium text-ink-faint">Tag</label>
                        <input id="reminder-date" type="date" wire:model="formDate" min="{{ $today->toDateString() }}" class="w-full rounded-card border-line bg-surface text-sm text-ink focus:border-overprint focus:ring-0" />
                        @error('remind_on') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-ink-soft">
                            <input type="checkbox" wire:model="formEvening" class="h-4 w-4 rounded border-line text-forest focus:ring-forest" />
                            Am Vorabend erinnern
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-ink-soft">
                            <input type="checkbox" wire:model="formOnDay" class="h-4 w-4 rounded border-line text-forest focus:ring-forest" />
                            Am Tag selbst erinnern
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Speichern' : 'Hinzufügen' }}</span>
                        <span wire:loading wire:target="save">Speichere …</span>
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="cancelEdit" class="rounded-card px-3 py-2 text-sm text-ink-soft transition hover:text-ink">Abbrechen</button>
                    @endif
                </div>
            </form>

            {{-- The list --}}
            @if ($this->reminders->isEmpty())
                <p class="rounded-card border border-dashed border-line px-4 py-6 text-center text-sm text-ink-faint">
                    Nichts zu vergessen. Schön ruhig.
                </p>
            @else
                <ul class="divide-y divide-line/70">
                    @foreach ($this->reminders as $reminder)
                        <li wire:key="reminder-row-{{ $reminder->id }}" class="flex items-center gap-3 py-2">
                            <button
                                type="button"
                                wire:click="toggleAcknowledged({{ $reminder->id }})"
                                class="grid h-7 w-7 flex-none place-items-center rounded-full border transition active:scale-95 {{ $reminder->isAcknowledged() ? 'border-forest bg-forest text-white' : 'border-line text-transparent hover:border-forest hover:text-forest' }}"
                                aria-label="{{ $reminder->title }}: {{ $reminder->isAcknowledged() ? 'doch noch nicht erledigt' : 'hab ich' }}"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                            </button>
                            <button type="button" wire:click="startEdit({{ $reminder->id }})" class="min-w-0 flex-1 text-left" aria-label="{{ $reminder->title }} bearbeiten">
                                <span class="block truncate text-sm {{ $reminder->isAcknowledged() ? 'text-ink-faint line-through' : 'text-ink' }}">{{ $reminder->title }}</span>
                                <span class="flex items-center gap-1.5 text-[11px] text-ink-faint">
                                    {{ $reminder->dateLabel($today) }}
                                    @if (! $reminder->notify_evening_before && ! $reminder->notify_on_day)
                                        · ohne Push
                                    @elseif (! $reminder->notify_evening_before)
                                        · nur am Tag
                                    @elseif (! $reminder->notify_on_day)
                                        · nur am Vorabend
                                    @endif
                                </span>
                            </button>
                            <button
                                type="button"
                                x-data="{ armed: false, _t: null }"
                                @click="if (armed) { $wire.delete({{ $reminder->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                                @click.outside="armed = false; clearTimeout(_t)"
                                @keydown.escape.window="armed = false; clearTimeout(_t)"
                                :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                                class="grid h-8 w-8 flex-none place-items-center rounded-card transition"
                                aria-label="{{ $reminder->title }} löschen"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M5 4.5l.5 8h5l.5-8"/></svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
</div>
