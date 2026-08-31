{{--
    "Kanban" board: three columns — Backlog / In Arbeit / Erledigt — built
    from the SAME two flags every other concept already reads: an active,
    not-today task is Backlog; an active, today-flagged task is In Arbeit;
    a completed task is Erledigt. No new axis data at all. See
    App\Services\ListConcepts, TaskBoard::kanbanColumns() and
    PLAN_LIST_CONCEPTS.md §1/§3.

    Own UX question this concept has that no other one does: how does a
    task actually MOVE between columns? Two complementary answers:
      1. The Backlog ⇄ In Arbeit axis (is_today) has no existing per-card
         control anywhere else in the app, so this concept adds exactly
         one: a small pill on each Backlog/In Arbeit card
         (TaskBoard::setKanbanColumn()) — its label always names the
         DESTINATION, not the current state. The Erledigt axis
         (is_completed) already has one: the checkbox every card already
         carries everywhere in this app (ManagesTasks::toggleComplete()) —
         reused completely unchanged, not duplicated.
      2. Drag, as a spatial shortcut on top of #1 — one shared Sortable
         group across all three columns (desktop grid + mobile tab panels),
         see window.kanbanColumnSortable in app.js. Erledigt is drop-only
         there (see that function's own docblock for why: a completed card
         carries no data-id anywhere in this app, so dragging one back OUT
         would silently fail to persist — the checkbox is the one, already-
         established way back out, on every breakpoint).

    Companion features (Vorbereitung, Notfallmodus, Zeitplan/focus timer,
    Hausaufgaben-Vorschau) stay concept-agnostic per PLAN_LIST_CONCEPTS.md §2
    requirement 6 — the same top-of-page furniture as board-three-things.php
    renders here unchanged.

    Signature moment — "Zielfarbe voraus": tapping a move pill washes a ring
    in the color of the column the card is heading TOWARD (contour for
    advancing into In Arbeit, a neutral ink for retreating to Backlog)
    rather than one fixed color — see .kanban-move-pulse in app.css.
--}}
@php
    $columnMeta = [
        'backlog' => [
            'label' => 'Backlog',
            'empty' => 'Nichts wartet. Erfasse etwas Neues — es landet hier.',
        ],
        'in_progress' => [
            'label' => 'In Arbeit',
            'empty' => 'Nichts gerade in Arbeit — zieh eine Karte her oder schieb sie mit „→ In Arbeit" weiter.',
        ],
        'done' => [
            'label' => 'Erledigt',
            'empty' => 'Noch nichts erledigt.',
        ],
    ];
@endphp

    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-6xl px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-6'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-6'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-6', 'interaction' => 'drag'])

            <div class="grid grid-cols-3 gap-4">
                @foreach ($columnMeta as $key => $meta)
                    <div class="rounded-card border border-line bg-surface p-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-medium uppercase tracking-[0.08em] text-ink-soft">{{ $meta['label'] }}</h3>
                            <span class="tnum text-[11px] text-ink-faint">{{ $this->kanbanColumns[$key]->count() }}</span>
                        </div>

                        <div
                            class="flex min-h-[64px] flex-col gap-2"
                            data-column="{{ $key }}"
                            x-data
                            x-init="window.kanbanColumnSortable($el, $wire)"
                        >
                            @forelse ($this->kanbanColumns[$key] as $task)
                                <div wire:key="kb-row-{{ $task->id }}">
                                    @if ($key === 'backlog')
                                        <div class="flex items-start gap-1.5">
                                            <button
                                                type="button"
                                                x-data="{ pulsing: false }"
                                                @click="pulsing = true; setTimeout(() => pulsing = false, 700)"
                                                wire:click="setKanbanColumn({{ $task->id }}, 'in_progress')"
                                                style="--kanban-pulse-color: rgb(var(--contour))"
                                                class="kanban-move-pill mt-2.5 flex-none whitespace-nowrap rounded-full border border-line px-1.5 py-0.5 text-[10px] font-medium text-ink-faint transition hover:border-contour hover:text-contour focus:outline-none focus-visible:ring-2 focus-visible:ring-contour"
                                                aria-label="In Arbeit nehmen: {{ $task->title }}"
                                            >
                                                <span x-show="pulsing" x-cloak class="kanban-move-pulse" aria-hidden="true"></span>
                                                → In Arbeit
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                @include('livewire.partials.task-card', ['task' => $task])
                                            </div>
                                        </div>
                                    @elseif ($key === 'in_progress')
                                        <div class="flex items-start gap-1.5">
                                            <button
                                                type="button"
                                                x-data="{ pulsing: false }"
                                                @click="pulsing = true; setTimeout(() => pulsing = false, 700)"
                                                wire:click="setKanbanColumn({{ $task->id }}, 'backlog')"
                                                style="--kanban-pulse-color: rgb(var(--ink))"
                                                class="kanban-move-pill mt-2.5 flex-none whitespace-nowrap rounded-full border border-line px-1.5 py-0.5 text-[10px] font-medium text-ink-faint transition hover:border-ink hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-ink"
                                                aria-label="Zurück in den Backlog: {{ $task->title }}"
                                            >
                                                <span x-show="pulsing" x-cloak class="kanban-move-pulse" aria-hidden="true"></span>
                                                ← Backlog
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                @include('livewire.partials.task-card', ['task' => $task])
                                            </div>
                                        </div>
                                    @else
                                        @include('livewire.partials.task-card', ['task' => $task])
                                    @endif
                                </div>
                            @empty
                                <p class="px-1 py-3 text-center text-xs leading-relaxed text-ink-faint">{{ $meta['empty'] }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) — 3 column tabs, one visible at a time ════════════════ --}}
    <div class="md:hidden" x-data="{ activeColumn: 'backlog' }">
        <div class="px-4 pb-6 pt-4">
            <div class="mb-4">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-4'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-4'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-4', 'interaction' => 'swipe'])

            <div class="mb-3 grid grid-cols-3 gap-1.5">
                @foreach ($columnMeta as $key => $meta)
                    <button
                        type="button"
                        @click="activeColumn = '{{ $key }}'"
                        :class="activeColumn === '{{ $key }}' ? 'border-contour bg-contour-soft text-contour' : 'border-line text-ink-faint'"
                        class="rounded-card border px-2 py-2 text-center text-[11px] font-medium leading-tight transition"
                    >
                        {{ $meta['label'] }}
                        <span class="tnum ml-1 text-ink-faint">{{ $this->kanbanColumns[$key]->count() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($columnMeta as $key => $meta)
                {{-- Inline style matches the Alpine default ('backlog' active) so the
                     first paint already shows the right panel — Alpine's own x-show
                     reactivity then takes over on tap, same as everywhere else this
                     app relies on it. --}}
                <div x-show="activeColumn === '{{ $key }}'" @if ($key !== 'backlog') style="display: none" @endif>
                    <div
                        class="flex flex-col gap-2.5"
                        data-column="{{ $key }}"
                        x-data
                        x-init="window.kanbanColumnSortable($el, $wire, '[data-drag-handle]')"
                    >
                        @forelse ($this->kanbanColumns[$key] as $task)
                            <div wire:key="kb-mrow-{{ $task->id }}">
                                @if ($key !== 'done')
                                    <button
                                        type="button"
                                        x-data="{ pulsing: false }"
                                        @click="pulsing = true; setTimeout(() => pulsing = false, 700)"
                                        wire:click="setKanbanColumn({{ $task->id }}, '{{ $key === 'backlog' ? 'in_progress' : 'backlog' }}')"
                                        style="--kanban-pulse-color: {{ $key === 'backlog' ? 'rgb(var(--contour))' : 'rgb(var(--ink))' }}"
                                        class="kanban-move-pill mb-1 ml-1 inline-flex items-center gap-1 rounded-full border border-line px-2 py-0.5 text-[11px] font-medium text-ink-faint transition {{ $key === 'backlog' ? 'hover:border-contour hover:text-contour' : 'hover:border-ink hover:text-ink' }}"
                                        aria-label="{{ $key === 'backlog' ? 'In Arbeit nehmen' : 'Zurück in den Backlog' }}: {{ $task->title }}"
                                    >
                                        <span x-show="pulsing" x-cloak class="kanban-move-pulse" aria-hidden="true"></span>
                                        {{ $key === 'backlog' ? '→ In Arbeit' : '← Backlog' }}
                                    </button>
                                @endif
                                @include('livewire.partials.task-card-mobile', [
                                    'task' => $task,
                                    'rightIntent' => $key === 'done' ? '' : 'advance',
                                    'leftIntent' => 'edit',
                                    'wireMethod' => 'swipeIntentKanban',
                                ])
                            </div>
                        @empty
                            <x-board-empty>{{ $meta['empty'] }}</x-board-empty>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ════════════════ EDIT SHEET ════════════════ --}}
    @include('livewire.partials.edit-sheet')

    {{-- ════════════════ PROJECT PICKER (mobile long-press) ════════════════ --}}
    @include('livewire.partials.project-picker-sheet')
