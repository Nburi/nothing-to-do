@php
    $isInbox = $task->isInbox();
    // right = swipe-right action (anchored left), left = swipe-left action (anchored right)
    $rightIntent = $isInbox ? 'todos' : ($task->is_today ? 'untoday' : 'today');
    $leftIntent = $isInbox ? 'tasks' : 'edit';
    $meta = [
        'todos'   => ['label' => 'To-Dos',      'bg' => 'bg-forest',  'fg' => 'text-white'],
        'tasks'   => ['label' => 'Tasks',        'bg' => 'bg-contour', 'fg' => 'text-white'],
        'today'   => ['label' => 'Heute',        'bg' => 'bg-forest',  'fg' => 'text-white'],
        'untoday' => ['label' => 'Kein Heute',   'bg' => 'bg-ink',     'fg' => 'text-paper'],
        'edit'    => ['label' => 'Bearbeiten',   'bg' => 'bg-ink',     'fg' => 'text-paper'],
    ];
    $rm = $meta[$rightIntent];
    $lm = $meta[$leftIntent];
@endphp

<div
    wire:key="m-task-{{ $task->id }}"
    @unless($task->is_completed || isset($orderNumber)) data-id="{{ $task->id }}" data-title="{{ $task->title }}" @endunless
    class="relative select-none"
    x-data="swipeCard({ id: {{ $task->id }}, right: '{{ $rightIntent }}', left: '{{ $leftIntent }}' })"
>
    {{-- swipe-right action, anchored left --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-start gap-2 rounded-card pl-5 text-sm font-medium {{ $rm['bg'] }} {{ $rm['fg'] }}"
        x-show="dir === 'right'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        {{ $rm['label'] }}
    </div>

    {{-- swipe-left action, anchored right --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-end gap-2 rounded-card pr-5 text-sm font-medium {{ $lm['bg'] }} {{ $lm['fg'] }}"
        x-show="dir === 'left'" :style="{ opacity: progress }" style="display: none;"
    >
        {{ $lm['label'] }}
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            @if ($leftIntent === 'edit')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M14 2l4 4-10 10H4v-4L14 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @else
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endif
        </span>
    </div>

    {{-- the card that tracks the finger --}}
    {{-- touch-action lives in a class, not inline style: Alpine's :style transform
         binding replaces the whole style attribute each frame and would wipe an
         inline touch-action, letting the browser steal the horizontal swipe. --}}
    <div
        @class([
            'relative flex touch-pan-y items-start gap-3 rounded-card border py-3 pl-3 pr-2 shadow-map',
            'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft' => $task->is_important && !$task->is_completed,
            'border-line bg-surface' => !$task->is_important && !$task->is_completed,
            'border-line bg-surface opacity-50' => $task->is_completed,
        ])
        :class="{ 'transition-transform duration-200 ease-tactile': !dragging }"
        :style="'transform: translateX(' + dx + 'px)'"
        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
    >
        @isset($orderNumber)
            <span class="tnum mt-0.5 grid h-5 w-5 flex-none place-items-center rounded-full border border-line text-[10px] text-ink-soft" aria-hidden="true">{{ $orderNumber }}</span>
        @endisset

        <button
            type="button"
            wire:click.stop="toggleComplete({{ $task->id }})"
            @class([
                'mt-px grid h-[22px] w-[22px] flex-none place-items-center rounded-full border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest',
                'border-forest bg-forest text-white' => $task->is_completed,
                'border-line text-transparent hover:border-forest hover:text-forest' => !$task->is_completed,
            ])
            aria-label="{{ $task->is_completed ? 'Als offen markieren' : 'Erledigt markieren' }}: {{ $task->title }}"
        >
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <div
            x-data="{
                dateOpen: false,
                durationOpen: false,
                deadline: '{{ $task->deadline?->toDateString() }}',
                dueDate: '{{ $task->due_date?->toDateString() }}',
            }"
            class="contents"
        >
            <div class="min-w-0 flex-1">
                <div x-data="{ lastTap: 0 }" class="contents">
                    <button
                        type="button"
                        wire:click="toggleImportant({{ $task->id }})"
                        @click="if (Date.now() - lastTap < 320) { $wire.startEdit({{ $task->id }}); lastTap = 0; } else { lastTap = Date.now(); }"
                        class="block w-full text-left"
                    >
                        <span @class([
                            'block break-words text-[15px] leading-snug',
                            'line-through text-ink-faint' => $task->is_completed,
                            'font-medium text-ink' => !$task->is_completed && $task->is_important,
                            'text-ink' => !$task->is_completed && !$task->is_important,
                        ])>
                            @if ($task->agenda_entry_id)
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
                            class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium
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
                            @click.stop="dateOpen = !dateOpen"
                            class="mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-ink-faint transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                            aria-label="Termin setzen: {{ $task->title }}"
                        >
                            <svg class="h-2.5 w-2.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            Termin
                        </button>
                    @endif

                    @if ($task->list !== 'inbox')
                        @if ($task->duration_minutes)
                            <button
                                type="button"
                                @click.stop="dateOpen = false; durationOpen = !durationOpen"
                                class="tnum ml-1 mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-ink-faint transition"
                                aria-label="Dauer ändern: {{ $task->title }}"
                            >
                                {{ $task->duration_minutes }} min
                            </button>
                        @else
                            <button
                                type="button"
                                @click.stop="dateOpen = false; durationOpen = !durationOpen"
                                class="ml-1 mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium text-ink-faint transition"
                                aria-label="Dauer schätzen: {{ $task->title }}"
                            >
                                <svg class="h-2.5 w-2.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5v3.8l2.5 1.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Dauer
                            </button>
                        @endif
                    @endif

                    @if ($preview = $task->notesPreview())
                        <button
                            type="button"
                            wire:click="startEdit({{ $task->id }})"
                            @click.stop
                            class="mt-1 block max-w-full truncate rounded text-left text-[11px] text-ink-faint transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                            aria-label="Notizen anzeigen: {{ $task->title }}"
                        >{{ $preview }}</button>
                    @endif
                @endif
            </div>

            {{-- Inline drag handle + edit + delete actions (always visible on mobile) --}}
            <div class="flex flex-none items-center gap-0.5">
                @unless($task->is_completed || isset($orderNumber))
                    {{-- Reorder handle: dragging is scoped to this icon so it never fights
                         swipeCard's horizontal-swipe/long-press gestures on the rest of the card. --}}
                    <button
                        type="button"
                        data-drag-handle
                        @click.stop
                        class="grid h-7 w-7 flex-none touch-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink active:cursor-grabbing"
                        aria-label="Ziehen zum Sortieren: {{ $task->title }}"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" />
                            <circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" />
                            <circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" />
                        </svg>
                    </button>
                @endunless
                <button
                    type="button"
                    wire:click="startEdit({{ $task->id }})"
                    @click.stop
                    class="grid h-7 w-7 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
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
                    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                    class="grid h-7 w-7 place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
                    aria-label="Löschen: {{ $task->title }}"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <div
                x-show="dateOpen"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @click.outside="dateOpen = false"
                @keydown.escape.window="dateOpen = false"
                class="absolute left-9 top-14 z-20 w-56 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
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

            <div
                x-show="durationOpen"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @click.outside="durationOpen = false"
                @keydown.escape.window="durationOpen = false"
                class="absolute left-9 top-14 z-20 w-56 space-y-2 rounded-card border border-line bg-surface p-3 shadow-map"
                style="display: none"
            >
                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Geschätzte Dauer</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([10, 15, 25, 45, 60] as $mins)
                        <button type="button" wire:click="quickSetDuration({{ $task->id }}, {{ $mins }})" @click="durationOpen = false" class="tnum rounded-card border border-line bg-paper px-2 py-1 text-xs text-ink-soft transition hover:border-overprint hover:text-ink">{{ $mins }} min</button>
                    @endforeach
                    <input
                        type="number" min="1" max="600" step="1"
                        placeholder="eigene"
                        @change="$wire.quickSetDuration({{ $task->id }}, $event.target.valueAsNumber || null); durationOpen = false"
                        class="tnum w-16 rounded-card border border-line bg-paper px-2 py-1 text-center text-xs text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        aria-label="Eigene Dauer in Minuten"
                    />
                </div>
                @if ($task->duration_minutes)
                    <button type="button" wire:click="quickSetDuration({{ $task->id }}, null)" @click="durationOpen = false" class="text-[11px] text-ink-faint underline decoration-dotted">Schätzung entfernen</button>
                @endif
            </div>
        </div>
    </div>
</div>
