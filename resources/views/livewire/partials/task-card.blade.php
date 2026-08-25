{{-- Desktop task card. A SortableJS item (data-id) when active. Tap body = important, circle = done. --}}
<div
    wire:key="task-{{ $task->id }}"
    @unless($task->is_completed) data-id="{{ $task->id }}" data-title="{{ $task->title }}" @endunless
    @if ($task->agenda_entry_id) data-homework="true" @endif
    x-data="{
        dateOpen: false,
        durationOpen: false,
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

    @isset($orderNumber)
        <span class="tnum mt-0.5 grid h-5 w-5 flex-none place-items-center rounded-full border border-line text-[10px] text-ink-soft" aria-hidden="true">{{ $orderNumber }}</span>
    @endisset

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
        <div x-data="{ lastTap: 0 }" class="contents">
            <button
                type="button"
                wire:click="toggleImportant({{ $task->id }})"
                @click="if (Date.now() - lastTap < 320) { $wire.startEdit({{ $task->id }}); lastTap = 0; } else { lastTap = Date.now(); }"
                class="block w-full cursor-pointer rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                title="Tippen markiert als wichtig, doppelt tippen bearbeitet"
            >
                <span @class([
                    'block break-words text-sm leading-snug',
                    'line-through text-ink-faint' => $task->is_completed,
                    'font-medium text-ink' => !$task->is_completed && $task->is_important,
                    'text-ink' => !$task->is_completed && !$task->is_important,
                ])>
                    @if ($task->agenda_entry_id)
                        {{-- From the Agenda's Hausaufgaben preview — same icon as the strip's own header badge. --}}
                        <svg class="-mt-0.5 mr-1 inline h-3 w-3 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H13l3 3v9a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 3 16V7"/><path d="M13 4v3h3"/></svg>
                    @endif
                    {{ $task->title }}
                </span>
            </button>
        </div>

        @if (!$task->is_completed)
            @if ($label = $task->effectiveDateLabel())
                <button
                    type="button"
                    @click.stop="dateOpen = !dateOpen"
                    class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint
                    {{ $task->isOverdue()
                        ? 'bg-signal-soft text-signal'
                        : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}"
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
                    @click.stop="dateOpen = !dateOpen"
                    class="mt-0 flex max-h-0 min-h-0 items-center gap-1 overflow-hidden rounded px-1.5 py-0 text-[11px] font-medium text-ink-faint transition-all duration-150 hover:text-ink-soft focus:outline-none focus-visible:mt-1 focus-visible:max-h-5 focus-visible:py-0.5 focus-visible:ring-2 focus-visible:ring-overprint group-hover/card:mt-1 group-hover/card:max-h-5 group-hover/card:py-0.5"
                    aria-label="Termin setzen: {{ $task->title }}"
                >
                    <svg class="h-2.5 w-2.5 flex-none" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                    Termin
                </button>
            @endif

            {{-- Duration estimate — same ghost-reveal convention as the date badge above.
                 Never shown for Inbox: an untriaged task is out of scope for the Planer. --}}
            @if ($task->list !== 'inbox')
                @if ($task->duration_minutes)
                    <button
                        type="button"
                        @click.stop="dateOpen = false; durationOpen = !durationOpen"
                        class="tnum ml-1 mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-ink-faint transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                        aria-label="Dauer ändern: {{ $task->title }}"
                    >
                        {{ $task->duration_minutes }} min
                    </button>
                @else
                    <button
                        type="button"
                        @click.stop="dateOpen = false; durationOpen = !durationOpen"
                        class="ml-1 mt-0 flex max-h-0 min-h-0 items-center gap-1 overflow-hidden rounded px-1.5 py-0 text-[11px] font-medium text-ink-faint transition-all duration-150 hover:text-ink-soft focus:outline-none focus-visible:mt-1 focus-visible:max-h-5 focus-visible:py-0.5 focus-visible:ring-2 focus-visible:ring-overprint group-hover/card:mt-1 group-hover/card:max-h-5 group-hover/card:py-0.5"
                        aria-label="Dauer schätzen: {{ $task->title }}"
                    >
                        <svg class="h-2.5 w-2.5 flex-none" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5v3.8l2.5 1.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Dauer
                    </button>
                @endif
            @endif

            @if ($preview = $task->notesPreview())
                <button
                    type="button"
                    wire:click="startEdit({{ $task->id }})"
                    @click.stop
                    class="mt-1 block max-w-full truncate rounded text-left text-[11px] text-ink-faint transition hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    title="{{ $preview }}"
                    aria-label="Notizen anzeigen: {{ $task->title }}"
                >{{ $preview }}</button>
            @endif
        @endif
    </div>

    {{-- Quick deadline/due-date popover, opened by the date badge (or its ghost placeholder) above. --}}
    <div
        x-show="dateOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.outside="dateOpen = false"
        @keydown.escape.window="dateOpen = false"
        class="absolute left-9 top-9 z-20 w-56 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
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

    {{-- Quick duration popover, opened by the duration badge (or its ghost placeholder). Doubles as
         the app's only "you're missing an estimate" hint — the ghost itself is the warning. --}}
    <div
        x-show="durationOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.outside="durationOpen = false"
        @keydown.escape.window="durationOpen = false"
        class="absolute left-9 top-9 z-20 w-56 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
        style="display: none"
    >
        <label class="mb-1 block text-[11px] font-medium text-ink-faint">Geschätzte Dauer</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach ([10, 15, 25, 45, 60, 90] as $mins)
                <button type="button" wire:click="quickSetDuration({{ $task->id }}, {{ $mins }})" @click="durationOpen = false" class="tnum rounded-card border border-line bg-paper px-2 py-1 text-xs text-ink-soft transition hover:border-overprint hover:text-ink">{{ $mins }} min</button>
            @endforeach
        </div>
        @if ($task->duration_minutes)
            <button type="button" wire:click="quickSetDuration({{ $task->id }}, null)" @click="durationOpen = false" class="text-[11px] text-ink-faint underline decoration-dotted hover:text-signal">Schätzung entfernen</button>
        @endif
    </div>

    {{-- Inline edit + delete actions (appear on hover) --}}
    <div class="flex flex-none items-center gap-0.5">
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
