{{-- One agenda row: checkbox, type badge, subject, title, date badge, edit + delete. --}}
<div
    wire:key="agenda-{{ $entry->id }}"
    @class([
        'group/entry flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-map transition-colors',
        'border-l-[2.5px] border-l-signal' => $entry->isOverdue(),
        'opacity-60' => $entry->is_done,
    ])
>
    <button
        type="button"
        wire:click="toggleDone({{ $entry->id }})"
        @class([
            'grid h-[18px] w-[18px] flex-none place-items-center rounded-full border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
            'border-forest bg-forest text-white' => $entry->is_done,
            'border-line text-transparent hover:border-forest hover:text-forest' => !$entry->is_done,
        ])
        aria-label="{{ $entry->is_done ? 'Als offen markieren' : 'Erledigt markieren' }}: {{ $entry->title }}"
    >
        <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5">
            <span @class([
                'rounded-full px-2 py-0.5 text-[10.5px] font-medium',
                'bg-forest-soft text-forest' => $entry->type === 'homework',
                'bg-overprint-soft text-overprint' => $entry->type === 'exam',
            ])>{{ $entry->typeLabel() }}</span>
            <span class="truncate text-[12.5px] text-ink-faint">{{ $entry->subject }}</span>
        </div>
        <p @class([
            'mt-0.5 truncate text-[14.5px]',
            'font-medium text-ink' => !$entry->is_done,
            'text-ink-faint line-through' => $entry->is_done,
        ])>{{ $entry->title }}</p>
    </div>

    <span @class([
        'tnum flex-none rounded-card px-2 py-1 text-[12px] font-medium',
        'bg-signal-soft text-signal' => $entry->isOverdue(),
        'bg-contour-soft text-contour' => !$entry->isOverdue(),
    ])>{{ $entry->dateLabel() }}</span>

    <div class="flex flex-none items-center gap-0.5">
        <button
            type="button"
            wire:click="startEdit({{ $entry->id }})"
            class="grid h-7 w-7 place-items-center rounded-card text-ink-faint opacity-60 transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-overprint group-hover/entry:opacity-100"
            aria-label="Bearbeiten: {{ $entry->title }}"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
        </button>

        <button
            type="button"
            x-data="{ armed: false, _t: null }"
            @click="if (armed) { $wire.deleteEntry({{ $entry->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
            @click.outside="armed = false; clearTimeout(_t)"
            @keydown.escape.window="armed = false; clearTimeout(_t)"
            :class="armed ? 'opacity-100 bg-signal text-white' : 'opacity-60 group-hover/entry:opacity-100 text-ink-faint hover:bg-signal-soft hover:text-signal'"
            class="grid h-7 w-7 place-items-center rounded-card transition focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-signal"
            aria-label="Löschen: {{ $entry->title }}"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</div>
