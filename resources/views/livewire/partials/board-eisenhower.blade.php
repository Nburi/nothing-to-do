{{--
    "Eisenhower" board: a 2×2 grid — Wichtig×Dringend / Wichtig×Nicht Dringend /
    Nicht Wichtig×Dringend / Nicht Wichtig×Nicht Dringend — built from the SAME
    two flags every other concept already reads (`is_important`,
    `Task::isUrgent()`), not a new axis. See App\Services\ListConcepts,
    TaskBoard::eisenhowerQuadrants() and PLAN_LIST_CONCEPTS.md §1/§3.

    Own UX question this concept has that no other one does: how does a task
    actually MOVE between quadrants? Two complementary answers, both already
    fully safe and already fully built before this session touched anything:
      1. The card's own existing controls — tapping the title toggles
         is_important (every card, every concept, unchanged), tapping the
         date badge opens the existing deadline/due-date popover
         (ManagesTasks::quickSetDates()) which is exactly what decides
         isUrgent(). These work everywhere, including on a locked card (see
         below) and on mobile, where only one quadrant is visible at a time.
      2. Drag, as a spatial shortcut on top of #1 — desktop only, since
         mobile's 4-tab layout only ever shows one quadrant at a time and a
         drag can't reach a hidden one. Moving rows (importance) is a plain,
         always-safe write. Moving columns (urgency) only ever touches
         `due_date`, never `deadline` — a task with a hard deadline has its
         urgency permanently decided by that deadline regardless of
         due_date (Task::isUrgencyLocked()), so such a card refuses to cross
         columns by drag at all (a small lock badge says why) rather than
         accepting a drop that could never actually move it. See
         window.eisenhowerQuadrantSortable (app.js) and
         TaskBoard::reorderEisenhower().

    Deliberately NOT built this session (see TODO.md): dragging a homework-
    preview card straight onto a quadrant (desktop), and drag-to-group onto
    another card within the grid — both remain reachable through their
    normal paths (the strip's own mobile swipe-to-promote; the group's own
    page / edit sheet / QuickCapture's group target), just not as a quadrant
    gesture in this first pass.

    Companion features (Vorbereitung, Notfallmodus, Zeitplan/focus timer,
    Hausaufgaben-Vorschau) stay concept-agnostic per PLAN_LIST_CONCEPTS.md §2
    requirement 6 — the same top-of-page furniture as board-three-things.php
    renders here unchanged.

    Signature moment — "der Krisenring": the instant a task genuinely lands
    in "Wichtig & Dringend" (drag, star-tap, a date change, or a fresh
    quadrant-tap capture), that quadrant's whole card washes once in a slow,
    calm overprint-toned ring — an acknowledgment, not an alarm (this app
    reserves the danger tone, signal, for something actually gone wrong).
    See TaskBoard::trackEisenhowerCrisisEntries(), the "eisenhower-crisis"
    Livewire listener in app.js, .eisenhower-crisis-ring in app.css.
--}}
@php
    $today = auth()->user()->localToday();
    $urgentDueDate = $today->copy()->addDays(\App\Models\Task::URGENCY_DAYS)->toDateString();

    $quadrantMeta = [
        'important_urgent' => [
            'label' => 'Wichtig & Dringend',
            'important' => true,
            'urgent' => true,
            'empty' => 'Nichts brennt gerade.',
        ],
        'important_not_urgent' => [
            'label' => 'Wichtig & Nicht dringend',
            'important' => true,
            'urgent' => false,
            'empty' => 'Der wichtigste leere Platz — hier lohnt sich Vorausdenken.',
        ],
        'not_important_urgent' => [
            'label' => 'Nicht wichtig & Dringend',
            'important' => false,
            'urgent' => true,
            'empty' => 'Nichts Kurzfristiges, das nicht auch wichtig ist.',
        ],
        'not_important_not_urgent' => [
            'label' => 'Nicht wichtig & Nicht dringend',
            'important' => false,
            'urgent' => false,
            'empty' => 'Der ruhigste Ort im Haus — bleib hier, wenn du kannst.',
        ],
    ];
@endphp

    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-5xl px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-6'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-6'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-6', 'interaction' => 'drag'])

            <div class="grid grid-cols-2 gap-4">
                @foreach ($quadrantMeta as $key => $meta)
                    <div
                        @if ($key === 'important_urgent') data-eisenhower-quadrant="important_urgent" @endif
                        class="rounded-card border border-line bg-surface p-3"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-medium uppercase tracking-[0.08em] text-ink-soft">{{ $meta['label'] }}</h3>
                            <button
                                type="button"
                                x-data
                                @click="$store.quickCapture.show($event.currentTarget, 'tasks', null, { important: @js($meta['important']), dueDate: @js($meta['urgent'] ? $urgentDueDate : null) })"
                                class="grid h-6 w-6 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                aria-label="Aufgabe für {{ $meta['label'] }} erfassen"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            </button>
                        </div>

                        <div
                            class="flex min-h-[64px] flex-col gap-2"
                            data-important="{{ $meta['important'] ? 'true' : 'false' }}"
                            data-urgent="{{ $meta['urgent'] ? 'true' : 'false' }}"
                            x-data
                            x-init="window.eisenhowerQuadrantSortable($el, $wire)"
                        >
                            @forelse ($this->eisenhowerQuadrants[$key] as $task)
                                <div wire:key="eh-row-{{ $task->id }}" data-urgency-locked="{{ $task->isUrgencyLocked() ? 'true' : 'false' }}" class="flex items-start gap-1.5">
                                    <button
                                        type="button"
                                        x-data="{ pulsing: false }"
                                        @click="pulsing = true; setTimeout(() => pulsing = false, 700)"
                                        wire:click="setTodayEisenhower({{ $task->id }}, {{ $task->is_today ? 'false' : 'true' }})"
                                        @class([
                                            'today-toggle relative mt-2.5 flex-none whitespace-nowrap rounded-full border px-1.5 py-0.5 text-[10px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest',
                                            'border-forest bg-forest text-white' => $task->is_today,
                                            'border-line text-ink-faint hover:border-forest hover:text-forest' => ! $task->is_today,
                                        ])
                                        aria-pressed="{{ $task->is_today ? 'true' : 'false' }}"
                                        aria-label="{{ $task->is_today ? 'Aus Heute entfernen' : 'Für heute markieren' }}: {{ $task->title }}"
                                    >
                                        <span x-show="pulsing" x-cloak class="today-pulse-ring" aria-hidden="true"></span>
                                        Heute
                                    </button>
                                    @if ($task->isUrgencyLocked())
                                        <span
                                            class="mt-2.5 flex-none text-ink-faint"
                                            title="Ein fester Termin bestimmt die Dringlichkeit — ändere ihn über den Termin-Badge, um sie zu verschieben."
                                            aria-hidden="true"
                                        >
                                            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6" rx="1.25" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" stroke="currentColor" stroke-width="1.4"/></svg>
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        @include('livewire.partials.task-card', ['task' => $task])
                                    </div>
                                </div>
                            @empty
                                <p class="px-1 py-3 text-center text-xs leading-relaxed text-ink-faint">{{ $meta['empty'] }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->eisenhowerQuadrants['done']->isNotEmpty())
                <div class="mt-4 border-t border-line/50 pt-3">
                    <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($this->eisenhowerQuadrants['done'] as $task)
                            @include('livewire.partials.task-card', ['task' => $task])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) — 4 quadrant tabs, one visible at a time ════════════════ --}}
    <div class="md:hidden" x-data="{ activeQuadrant: 'important_urgent' }">
        <div class="px-4 pb-6 pt-4">
            <div class="mb-4">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-4'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-4'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-4', 'interaction' => 'swipe'])

            <div class="mb-3 grid grid-cols-2 gap-1.5">
                @foreach ($quadrantMeta as $key => $meta)
                    <button
                        type="button"
                        @click="activeQuadrant = '{{ $key }}'"
                        :class="activeQuadrant === '{{ $key }}' ? 'border-overprint bg-overprint-soft text-overprint' : 'border-line text-ink-faint'"
                        class="rounded-card border px-2 py-2 text-left text-[11px] font-medium leading-tight transition"
                    >
                        {{ $meta['label'] }}
                        <span class="tnum ml-1 text-ink-faint">{{ $this->eisenhowerQuadrants[$key]->count() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($quadrantMeta as $key => $meta)
                {{-- Inline style matches the Alpine default ('important_urgent' active) so the
                     first paint already shows the right panel — Alpine's own x-show reactivity
                     then takes over on tap, same as everywhere else this app relies on it. --}}
                <div x-show="activeQuadrant === '{{ $key }}'" @if ($key !== 'important_urgent') style="display: none" @endif>
                    <div class="mb-2 flex items-center justify-end">
                        <button
                            type="button"
                            x-data
                            @click="$store.quickCapture.show($event.currentTarget, 'tasks', null, { important: @js($meta['important']), dueDate: @js($meta['urgent'] ? $urgentDueDate : null) })"
                            class="inline-flex items-center gap-1 rounded-card px-2 py-1 text-xs font-medium text-ink-faint transition hover:bg-paper hover:text-ink"
                            aria-label="Aufgabe für {{ $meta['label'] }} erfassen"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            Erfassen
                        </button>
                    </div>

                    <div
                        class="flex flex-col gap-2.5"
                        data-important="{{ $meta['important'] ? 'true' : 'false' }}"
                        data-urgent="{{ $meta['urgent'] ? 'true' : 'false' }}"
                        x-data
                        x-init="window.eisenhowerQuadrantSortable($el, $wire, '[data-drag-handle]')"
                    >
                        @forelse ($this->eisenhowerQuadrants[$key] as $task)
                            <div wire:key="eh-mrow-{{ $task->id }}" data-urgency-locked="{{ $task->isUrgencyLocked() ? 'true' : 'false' }}">
                                @if ($task->isUrgencyLocked())
                                    <p class="mb-0.5 flex items-center gap-1 pl-1 text-[10px] text-ink-faint">
                                        <svg class="h-2.5 w-2.5 flex-none" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6" rx="1.25" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" stroke="currentColor" stroke-width="1.4"/></svg>
                                        Termin bestimmt die Dringlichkeit
                                    </p>
                                @endif
                                @include('livewire.partials.task-card-mobile', [
                                    'task' => $task,
                                    'rightIntent' => $task->is_today ? 'untoday' : 'today',
                                    'leftIntent' => 'edit',
                                    'wireMethod' => 'swipeIntentEisenhower',
                                ])
                            </div>
                        @empty
                            <x-board-empty>{{ $meta['empty'] }}</x-board-empty>
                        @endforelse
                    </div>
                </div>
            @endforeach

            @if ($this->eisenhowerQuadrants['done']->isNotEmpty())
                <div class="mt-3 border-t border-line/50 pt-3">
                    <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                </div>
                @foreach ($this->eisenhowerQuadrants['done'] as $task)
                    @include('livewire.partials.task-card-mobile', [
                        'task' => $task,
                        'rightIntent' => $task->is_today ? 'untoday' : 'today',
                        'leftIntent' => 'edit',
                        'wireMethod' => 'swipeIntentEisenhower',
                    ])
                @endforeach
            @endif
        </div>
    </div>

    {{-- ════════════════ EDIT SHEET ════════════════ --}}
    @include('livewire.partials.edit-sheet')

    {{-- ════════════════ PROJECT PICKER (mobile long-press) ════════════════ --}}
    @include('livewire.partials.project-picker-sheet')
