{{--
    Mobile day-assign sheet — opened by a tap on a chip (touch only; desktop
    already has drag-and-drop, see plannerTap in app.js). A tap, not a
    hold — the chip's own drag handle and "×" are separate tap targets, so
    this needs no delay to tell itself apart from those. A 14-day
    horizontal board is a poor drag target on a phone, the same
    "move into a container you can't see" problem this app already solves
    with a sheet for groups/projects (see project-picker-sheet.blade.php) —
    this is that same answer for days. Its row list is computed entirely
    client-side from the same day-column data attributes the desktop wave
    reads, so opening it needs no round trip.
--}}
<div x-data x-cloak>
    <div
        x-show="$store.plannerDayPicker.open"
        x-transition.opacity.duration.150ms
        @click="$store.plannerDayPicker.hide()"
        class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"
        style="display: none;"
    ></div>

    <div
        x-show="$store.plannerDayPicker.open"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="opacity-0 translate-y-6"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @keydown.escape.window="$store.plannerDayPicker.hide()"
        class="fixed inset-x-0 bottom-0 z-50"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-label="Tag wählen"
    >
        <div class="mx-auto max-h-[70dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map">
            <div class="mb-1 flex items-center justify-between gap-3">
                <h2 class="min-w-0 truncate text-base font-medium text-ink" x-text="$store.plannerDayPicker.chip?.title"></h2>
                <button
                    type="button"
                    @click="$store.plannerDayPicker.hide()"
                    class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                    aria-label="Schließen"
                >
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-xs text-ink-faint">Auf welchen Tag?</p>

            <div class="space-y-1">
                <template x-for="day in $store.plannerDayPicker.days" :key="day.date">
                    <button
                        type="button"
                        @click="$wire.moveToDay($store.plannerDayPicker.chip.type + ':' + $store.plannerDayPicker.chip.id, day.date); $store.plannerDayPicker.hide()"
                        class="flex w-full items-center justify-between gap-3 rounded-card px-3 py-2.5 text-left text-sm transition"
                        :class="{
                            'bg-forest-soft/60 text-ink hover:bg-forest-soft': day.tier === 'free',
                            'bg-contour-soft/60 text-ink hover:bg-contour-soft': day.tier === 'tight',
                            'text-ink-faint hover:bg-paper': day.tier === 'past',
                        }"
                    >
                        <span x-text="day.label"></span>
                        <span class="tnum text-xs" x-text="day.hint"></span>
                    </button>
                </template>
            </div>

            <p x-show="$store.plannerDayPicker.open && $store.plannerDayPicker.days.length === 0" class="rounded-card border border-line bg-paper/60 p-3 text-sm leading-relaxed text-ink-soft" style="display: none;">
                Keine weiteren Tage im Zeitraum.
            </p>
        </div>
    </div>
</div>
