{{-- Phase 1 cleanup card: right → To-Dos, left → Tasks, down → später (defer). --}}
<div
    wire:key="cl-inbox-{{ $task->id }}"
    class="relative [grid-area:1/1]"
    x-data="cleanupSwipeCard({
        id: {{ $task->id }},
        phase: 'inbox',
        right: { kind: 'commit', wire: 'assignList', args: [{{ $task->id }}, 'todos'] },
        left:  { kind: 'commit', wire: 'assignList', args: [{{ $task->id }}, 'tasks'] },
        down:  { kind: 'defer' },
    })"
    :style="stackStyle"
>
    {{-- swipe-right reveal: → To-Dos --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-start gap-2 rounded-card bg-forest pl-6 text-sm font-medium text-white"
        x-show="dir === 'right'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        To-Dos
    </div>

    {{-- swipe-left reveal: → Tasks --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-end gap-2 rounded-card bg-contour pr-6 text-sm font-medium text-white"
        x-show="dir === 'left'" :style="{ opacity: progress }" style="display: none;"
    >
        Tasks
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

        <p class="min-h-[4.5rem] break-words pr-10 text-lg font-medium leading-snug text-ink">{{ $task->title }}</p>
    </div>

    {{-- button fallback row --}}
    <div class="mt-5 flex items-center justify-center gap-4" x-show="isTop" style="display: none">
        <button type="button" @click="trigger('left')" class="grid h-12 w-12 place-items-center rounded-full border border-line bg-surface text-contour shadow-map transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-contour" aria-label="Zu Tasks: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" @click="trigger('down')" class="grid h-11 w-11 place-items-center rounded-full border border-line bg-surface text-ink-faint shadow-map transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint" aria-label="Später entscheiden: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.75"/><path d="M10 6.5V10l2.5 1.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" @click="trigger('right')" class="grid h-12 w-12 place-items-center rounded-full border border-line bg-surface text-forest shadow-map transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest" aria-label="Zu To-Dos: {{ $task->title }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>
</div>
