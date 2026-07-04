{{-- Desktop task card. A SortableJS item (data-id) when active. Tap body = important, circle = done. --}}
<div
    wire:key="task-{{ $task->id }}"
    @unless($task->is_completed) data-id="{{ $task->id }}" @endunless
    x-data="{
        dateOpen: false,
        deadline: '{{ $task->deadline?->toDateString() }}',
        dueDate: '{{ $task->due_date?->toDateString() }}',
    }"
    @class([
        'group/card relative flex items-start gap-2.5 rounded-card border py-2.5 pl-3 pr-2 shadow-map transition-colors duration-200',
        'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft' => $task->is_important && !$task->is_completed,
        'border-line bg-surface hover:border-ink-faint/50' => !$task->is_important && !$task->is_completed,
        'border-line bg-surface opacity-50' => $task->is_completed,
    ])
>

    <button
        type="button"
        wire:click.stop="toggleComplete({{ $task->id }})"
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

    <div class="min-w-0 flex-1">
        <button
            type="button"
            wire:click="toggleImportant({{ $task->id }})"
            class="block w-full cursor-pointer rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
            title="Tippen markiert als wichtig"
        >
            <span @class([
                'block break-words text-sm leading-snug',
                'line-through text-ink-faint' => $task->is_completed,
                'font-medium text-ink' => !$task->is_completed && $task->is_important,
                'text-ink' => !$task->is_completed && !$task->is_important,
            ])>{{ $task->title }}</span>
        </button>

        @if (!$task->is_completed && ($label = $task->effectiveDateLabel()))
            <button
                type="button"
                @click.stop="dateOpen = !dateOpen"
                class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint
                {{ $task->isOverdue()
                    ? 'bg-signal-soft text-signal'
                    : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}"
            >
                @unless ($task->isOverdue())
                    <span class="inline-block h-1 w-1 rounded-full {{ $task->effectiveIsHard() ? 'bg-contour' : 'bg-ink-faint' }}" aria-hidden="true"></span>
                @endunless
                {{ $label }}
            </button>
        @endif
    </div>

    {{-- Quick deadline/due-date popover, opened by the calendar icon or the date badge above. --}}
    <div
        x-show="dateOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.outside="dateOpen = false"
        @keydown.escape.window="dateOpen = false"
        class="absolute right-2 top-9 z-20 w-56 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
        style="display: none"
    >
        <div>
            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline · hart</label>
            <input type="date" x-model="deadline" @change="$wire.quickSetDates({{ $task->id }}, deadline, dueDate)" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Wunschtermin · weich</label>
            <input type="date" x-model="dueDate" @change="$wire.quickSetDates({{ $task->id }}, deadline, dueDate)" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
        </div>
    </div>

    {{-- Inline date + edit + delete actions (appear on hover) --}}
    <div class="flex flex-none items-center gap-0.5">
        <button
            type="button"
            @click.stop="dateOpen = !dateOpen"
            :class="dateOpen && 'opacity-100 bg-paper text-ink'"
            class="grid h-7 w-7 place-items-center rounded-card text-ink-faint opacity-0 transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-overprint group-hover/card:opacity-100"
            aria-label="Termin setzen: {{ $task->title }}"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 5.5h10M5.5 3v2M10.5 3v2M3.5 5.5v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <button
            type="button"
            wire:click="startEdit({{ $task->id }})"
            @click.stop
            class="grid h-7 w-7 place-items-center rounded-card text-ink-faint opacity-0 transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-overprint group-hover/card:opacity-100"
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
            :class="armed ? 'opacity-100 bg-signal text-white' : 'opacity-0 group-hover/card:opacity-100 text-ink-faint hover:bg-signal-soft hover:text-signal'"
            class="grid h-7 w-7 place-items-center rounded-card transition focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-signal"
            aria-label="Löschen: {{ $task->title }}"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</div>
