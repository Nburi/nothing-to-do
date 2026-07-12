{{-- Phase 2 cleanup card: right → Heute, left → weiter (skip), down → später (defer), up → Termin popover. --}}
<div
    wire:key="cl-review-{{ $task->id }}"
    class="relative [grid-area:1/1]"
    x-data="cleanupSwipeCard({
        id: {{ $task->id }},
        phase: 'review',
        right: { kind: 'commit', wire: 'markToday', args: [{{ $task->id }}] },
        left:  { kind: 'commit' },
        down:  { kind: 'defer' },
        up:    { kind: 'popover' },
        deadline: '{{ $task->deadline?->toDateString() }}',
        dueDate: '{{ $task->due_date?->toDateString() }}',
    })"
    :style="stackStyle"
>
    {{-- swipe-right reveal: Heute --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-start gap-2 rounded-card bg-forest pl-6 text-sm font-medium text-white"
        x-show="dir === 'right'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        Heute
    </div>

    {{-- swipe-left reveal: weiter (no change) --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-end gap-2 rounded-card bg-ink pr-6 text-sm font-medium text-paper"
        x-show="dir === 'left'" :style="{ opacity: progress }" style="display: none;"
    >
        Weiter
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>

    {{-- swipe-down reveal: später --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-start justify-center gap-2 rounded-card bg-ink pt-6 text-sm font-medium text-paper"
        x-show="dir === 'down'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.75"/><path d="M10 6.5V10l2.5 1.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        Später
    </div>

    {{-- swipe-up reveal: Termin popover --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-end justify-center gap-2 rounded-card bg-contour pb-6 text-sm font-medium text-white"
        x-show="dir === 'up'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </span>
        Termin
    </div>

    {{-- face card --}}
    <div
        @class([
            'relative touch-none select-none rounded-card border p-6 shadow-map',
            'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft' => $task->is_important,
            'border-line bg-surface' => !$task->is_important,
        ])
        :class="{ 'transition-transform duration-200 ease-tactile': !dragging }"
        :style="'transform: translate(' + dx + 'px, ' + dy + 'px)'"
        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
    >
        <button
            type="button"
            wire:click.stop="toggleImportant({{ $task->id }})"
            @class([
                'absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full transition',
                'text-overprint' => $task->is_important,
                'text-ink-faint hover:text-overprint' => !$task->is_important,
            ])
            aria-label="{{ $task->is_important ? 'Wichtig entfernen' : 'Als wichtig markieren' }}: {{ $task->title }}"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="{{ $task->is_important ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linejoin="round" d="m10 2.5 2.35 4.76 5.25.77-3.8 3.7.9 5.24L10 14.5l-4.7 2.47.9-5.24-3.8-3.7 5.25-.77L10 2.5Z"/>
            </svg>
        </button>

        @if ($task->is_today)
            <span class="mb-2 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-forest">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-forest" aria-hidden="true"></span>
                Heute
            </span>
        @endif

        <p class="min-h-[4.5rem] break-words pr-10 text-lg font-medium leading-snug text-ink">{{ $task->title }}</p>

        @if ($label = $task->effectiveDateLabel())
            <button
                type="button"
                @click.stop="dateOpen = true"
                class="tnum mt-2 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium
                {{ $task->isOverdue() ? 'bg-signal-soft text-signal' : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}"
                aria-label="Termin ändern: {{ $task->title }}"
            >
                @unless ($task->isOverdue())
                    <span class="inline-block h-1 w-1 rounded-full {{ $task->effectiveIsHard() ? 'bg-contour' : 'bg-ink-faint' }}" aria-hidden="true"></span>
                @endunless
                {{ $label }}
            </button>
        @else
            <button
                type="button"
                @click.stop="dateOpen = true"
                class="mt-2 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-ink-faint transition hover:text-ink-soft"
                aria-label="Termin setzen: {{ $task->title }}"
            >
                <svg class="h-2.5 w-2.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                Termin
            </button>
        @endif

        {{-- Quick deadline/due-date popover — opened by the date badge above or an up-swipe past threshold. --}}
        <div
            x-show="dateOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.outside="dateOpen = false"
            @keydown.escape.window="dateOpen = false"
            @pointerdown.stop
            class="absolute inset-x-6 bottom-6 z-20 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
            style="display: none"
        >
            <div>
                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline · hart</label>
                <input type="date" x-model="deadline" @change="quickSetDate()" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Wunschtermin · weich</label>
                <input type="date" x-model="dueDate" @change="quickSetDate()" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
            </div>
            <button type="button" @click="dateOpen = false" class="w-full rounded-card bg-paper py-1.5 text-xs font-medium text-ink-soft transition hover:text-ink">Fertig</button>
        </div>
    </div>

    {{-- button fallback row --}}
    <div class="mt-5 grid grid-cols-4 items-center justify-items-center gap-3" x-show="isTop" style="display: none">
        <button type="button" @click="trigger('left')" class="grid h-11 w-11 place-items-center rounded-full border border-line bg-surface text-ink-faint shadow-map transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint" aria-label="Weiter, keine Änderung: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" @click="trigger('down')" class="grid h-11 w-11 place-items-center rounded-full border border-line bg-surface text-ink-faint shadow-map transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint" aria-label="Später entscheiden: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.75"/><path d="M10 6.5V10l2.5 1.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" @click="trigger('up')" class="grid h-11 w-11 place-items-center rounded-full border border-line bg-surface text-contour shadow-map transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-contour" aria-label="Termin setzen: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </button>
        <button type="button" @click="trigger('right')" class="grid h-12 w-12 place-items-center rounded-full border border-line bg-surface text-forest shadow-map transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest" aria-label="Heute markieren: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>
</div>
