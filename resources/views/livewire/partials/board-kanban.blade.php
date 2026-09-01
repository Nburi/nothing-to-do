{{--
    "Kanban" board: three columns — Backlog / In Arbeit / Erledigt — built
    from the SAME two flags every other concept already reads: an active,
    not-today task is Backlog; an active, today-flagged task is In Arbeit;
    a completed task is Erledigt. No new axis data at all. See
    App\Services\ListConcepts, TaskBoard::kanbanColumns() and
    PLAN_LIST_CONCEPTS.md §1/§3.

    How does a task actually MOVE between columns? The Backlog ⇄ In Arbeit
    axis (is_today) moves purely by drag — one shared Sortable group across
    all three columns (desktop grid + mobile tab panels), see
    window.kanbanColumnSortable in app.js. Erledigt is drop-only there (see
    that function's own docblock for why: a completed card carries no
    data-id anywhere in this app, so dragging one back OUT would silently
    fail to persist — the checkbox already on every card
    (ManagesTasks::toggleComplete()) is the one, already-established way
    back out, on every breakpoint). Mobile additionally keeps the existing
    right-swipe "advance" gesture (TaskBoard::swipeIntentKanban()) as a
    second way to move a card forward a column, same as every other
    drag-capable zone in this app stays swipeable too.

    Earlier iteration, replaced: the first shipped version also had a small
    per-card pill (TaskBoard::setKanbanColumn(), still the shared primitive
    both the drag zone and the swipe gesture write through) on every
    Backlog/In Arbeit card, labelled with the destination column — "der
    Zielfarbe voraus" signature moment, a pulse coloured toward wherever the
    card was heading. Removed as pure redundancy once drag already moves a
    card between the exact same two columns — a control duplicating a
    gesture that already exists needs a real reason to stay, and there
    wasn't one once actually compared side by side.

    Companion features (Vorbereitung, Notfallmodus, Zeitplan/focus timer,
    Hausaufgaben-Vorschau) stay concept-agnostic per PLAN_LIST_CONCEPTS.md §2
    requirement 6 — the same top-of-page furniture as board-three-things.php
    renders here unchanged.
--}}
@php
    $columnMeta = [
        'backlog' => [
            'label' => 'Backlog',
            'empty' => 'Nichts wartet. Erfasse etwas Neues — es landet hier.',
        ],
        'in_progress' => [
            'label' => 'In Arbeit',
            'empty' => 'Nichts gerade in Arbeit — zieh eine Karte her.',
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
                                @include('livewire.partials.task-card', ['task' => $task])
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
                            @include('livewire.partials.task-card-mobile', [
                                'task' => $task,
                                'rightIntent' => $key === 'done' ? '' : 'advance',
                                'leftIntent' => 'edit',
                                'wireMethod' => 'swipeIntentKanban',
                            ])
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
