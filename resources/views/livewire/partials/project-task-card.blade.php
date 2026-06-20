{{-- A task inside a project. Swipe left = open edit form (mobile). Inline edit + delete buttons (desktop: on hover). --}}
<div
    wire:key="ptask-{{ $task->id }}"
    class="group/card relative select-none"
    x-data="swipeCard({ id: {{ $task->id }}, left: 'edit' })"
>
    {{-- swipe-left action, anchored right --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-end gap-2 rounded-card bg-ink pr-5 text-sm font-medium text-paper"
        x-show="dir === 'left'" :style="{ opacity: progress }" style="display: none;"
    >
        Bearbeiten
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M14 2l4 4-10 10H4v-4L14 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>

    {{-- card that tracks the finger --}}
    {{-- touch-action lives in a class, not inline style: Alpine's :style transform
         binding replaces the whole style attribute each frame and would wipe an
         inline touch-action, letting the browser steal the horizontal swipe. --}}
    <div
        @class([
            'relative flex touch-pan-y items-start gap-2.5 rounded-card border py-2.5 pl-3 pr-2 shadow-map transition-colors duration-200',
            'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft' => $task->is_important && !$task->is_completed,
            'border-line bg-surface hover:border-ink-faint/50' => !$task->is_important && !$task->is_completed,
            'border-line bg-surface opacity-50' => $task->is_completed,
        ])
        :class="{ 'transition-transform duration-200 ease-tactile': !dragging }"
        :style="'transform: translateX(' + dx + 'px)'"
        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
    >
        <button
            type="button"
            wire:click="toggleComplete({{ $task->id }})"
            @class([
                'mt-px grid h-5 w-5 flex-none place-items-center rounded-full border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                'border-forest bg-forest text-white' => $task->is_completed,
                'border-line text-transparent hover:border-forest hover:text-forest' => !$task->is_completed,
            ])
            aria-label="{{ $task->is_completed ? 'Als offen markieren' : 'Erledigt markieren' }}: {{ $task->title }}"
        >
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <button
            type="button"
            wire:click="toggleImportant({{ $task->id }})"
            class="min-w-0 flex-1 cursor-pointer rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
            title="Tippen markiert als wichtig"
        >
            <span @class([
                'block break-words text-sm leading-snug',
                'line-through text-ink-faint' => $task->is_completed,
                'font-medium text-ink' => !$task->is_completed && $task->is_important,
                'text-ink' => !$task->is_completed && !$task->is_important,
            ])>{{ $task->title }}</span>

            @if (!$task->is_completed && ($label = $task->effectiveDateLabel()))
                <span class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium
                    {{ $task->isOverdue()
                        ? 'bg-signal-soft text-signal'
                        : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}">
                    @unless ($task->isOverdue())
                        <span class="inline-block h-1 w-1 rounded-full {{ $task->effectiveIsHard() ? 'bg-contour' : 'bg-ink-faint' }}" aria-hidden="true"></span>
                    @endunless
                    {{ $label }}
                </span>
            @endif
        </button>

        {{-- Inline edit + delete actions (mobile: always visible; desktop: on hover) --}}
        <div class="flex flex-none items-center gap-0.5">
            <button
                type="button"
                wire:click="startEdit({{ $task->id }})"
                @click.stop
                class="grid h-7 w-7 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint md:opacity-0 md:focus-visible:opacity-100 md:group-hover/card:opacity-100"
                aria-label="Bearbeiten: {{ $task->title }}"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
            </button>

            <button
                    type="button"
                    x-data="{ armed: false, _t: null }"
                    @click.stop="if (armed) { $wire.deleteTask({{ $task->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                    @click.outside="armed = false; clearTimeout(_t)"
                    @keydown.escape.window="armed = false; clearTimeout(_t)"
                    :class="armed ? '!opacity-100 bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="grid h-7 w-7 place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal md:opacity-0 md:focus-visible:opacity-100 md:group-hover/card:opacity-100"
                    aria-label="Löschen: {{ $task->title }}"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
        </div>
    </div>
</div>
