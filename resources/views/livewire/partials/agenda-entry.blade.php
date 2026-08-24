{{-- One agenda row: checkbox, type badge, subject, title, date badge, edit + delete. --}}
@php
    // "Done" is per person since entries became shareable — read it once here
    // rather than re-asking for every class this row sets. The flag itself is
    // preloaded by AgendaEntry::scopeWithCompletionState(), so this is free.
    $done = $entry->isDoneFor(auth()->user());

    // The space badge is redundant in the grouped view, where the section
    // heading already names the class.
    $showSpaceBadge = ($showSpaceBadge ?? true) && $entry->isShared();
    $byOthers = $entry->isShared() && $entry->user_id !== auth()->id();
    $memberCount = $entry->space?->members_count ?? 0;
@endphp
<div
    wire:key="agenda-{{ $entry->id }}"
    @class([
        'group/entry flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-map transition-colors',
        'border-l-[2.5px] border-l-signal' => $entry->isOverdue(),
        'opacity-60' => $done,
    ])
>
    <button
        type="button"
        wire:click="toggleDone({{ $entry->id }})"
        @class([
            'grid h-[18px] w-[18px] flex-none place-items-center rounded-full border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
            'border-forest bg-forest text-white' => $done,
            'border-line text-transparent hover:border-forest hover:text-forest' => !$done,
        ])
        aria-label="{{ $done ? 'Als offen markieren' : 'Erledigt markieren' }}: {{ $entry->title }}"
    >
        <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5">
            <span @class([
                'flex-none rounded-full px-2 py-0.5 text-[10.5px] font-medium',
                'bg-forest-soft text-forest' => $entry->type === 'homework',
                'bg-overprint-soft text-overprint' => $entry->type === 'exam',
            ])>{{ $entry->typeLabel() }}</span>

            @if ($showSpaceBadge)
                <span class="flex max-w-[9rem] flex-none items-center gap-1 rounded-full bg-contour-soft px-2 py-0.5 text-[10.5px] font-medium text-contour" title="{{ $entry->space->name }}">
                    <svg class="h-2.5 w-2.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 19v-1.4a3.4 3.4 0 0 0-3.4-3.4H7.4A3.4 3.4 0 0 0 4 17.6V19"/><circle cx="10" cy="8.2" r="3.1"/><path d="M20 19v-1.4a3.4 3.4 0 0 0-2.6-3.3"/>
                    </svg>
                    <span class="truncate">{{ $entry->space->shortName() }}</span>
                </span>
            @endif

            <span class="truncate text-[12.5px] text-ink-faint">
                {{ $entry->subject }}@if ($byOthers) · von {{ $entry->user?->name }}@endif
            </span>
        </div>
        <p @class([
            'mt-0.5 truncate text-[14.5px]',
            'font-medium text-ink' => !$done,
            'text-ink-faint line-through' => $done,
        ])>{{ $entry->title }}</p>

        @if ($preview = $entry->notesPreview())
            <div x-data="{ notesOpen: false }">
                <button
                    type="button"
                    @click.stop="notesOpen = !notesOpen"
                    class="mt-0.5 block max-w-full truncate rounded text-left text-[11.5px] text-ink-faint transition hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    :aria-expanded="notesOpen.toString()"
                    aria-label="Notiz ein-/ausklappen: {{ $entry->title }}"
                >
                    <span x-show="!notesOpen">{{ $preview }}</span>
                    <span x-show="notesOpen" style="display: none;">Notiz ausblenden</span>
                </button>
                <p
                    x-show="notesOpen"
                    style="display: none;"
                    class="mt-0.5 whitespace-pre-line break-words text-[12.5px] text-ink-soft"
                >{{ $entry->notes }}</p>
            </div>
        @endif

        {{-- Only you ever see this — never rendered from $entry->notes, always
             from your own private-note lookup, so a classmate reading this
             partial for the same entry gets nothing here at all. --}}
        @if ($entry->isShared() && ($privateNote = $entry->privateNoteFor(auth()->user())))
            <div x-data="{ notesOpen: false }" class="mt-0.5">
                <button
                    type="button"
                    @click.stop="notesOpen = !notesOpen"
                    class="flex max-w-full items-center gap-1 truncate rounded text-left text-[11.5px] text-ink-faint transition hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    :aria-expanded="notesOpen.toString()"
                    aria-label="Eigene Notiz ein-/ausklappen: {{ $entry->title }}"
                >
                    <span class="h-1.5 w-1.5 flex-none rounded-full border border-ink-faint" aria-hidden="true"></span>
                    <span class="truncate" x-show="!notesOpen">{{ $entry->privateNotePreview(auth()->user()) }}</span>
                    <span x-show="notesOpen" style="display: none;">Eigene Notiz ausblenden</span>
                </button>
                <p
                    x-show="notesOpen"
                    style="display: none;"
                    class="mt-0.5 whitespace-pre-line break-words text-[12.5px] text-ink-soft"
                >{{ $privateNote }}</p>
            </div>
        @endif
    </div>

    @if ($entry->isShared() && $memberCount > 0)
        @php
            $classDone = $entry->completedCount();
            $classPct = $memberCount > 0 ? round(($classDone / $memberCount) * 100) : 0;
        @endphp
        {{-- How much of the class is through it. Same visual language as the project
             card's progress bar (h-1 line track, forest fill), but stacked over its
             count instead of beside it: this row is a single dense line, and a
             side-by-side bar would take ~38px off the title column where the stack
             costs ~10. Deliberately quiet — it's context, not a leaderboard, and it
             must never outweigh the due date — but shown at every width, since
             hiding it on mobile would hide it on the device this gets used on. --}}
        <div
            class="flex flex-none flex-col items-end gap-1"
            title="{{ $classDone }} von {{ $memberCount }} haben das erledigt"
        >
            <div
                class="h-1 w-9 overflow-hidden rounded-full bg-line"
                role="progressbar"
                aria-valuenow="{{ $classDone }}"
                aria-valuemin="0"
                aria-valuemax="{{ $memberCount }}"
                aria-label="Fortschritt der Klasse: {{ $classDone }} von {{ $memberCount }} erledigt"
            >
                <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $classPct }}%"></div>
            </div>
            <span class="tnum text-[11px] leading-none text-ink-faint" aria-hidden="true">{{ $classDone }}/{{ $memberCount }}</span>
        </div>
    @endif

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
