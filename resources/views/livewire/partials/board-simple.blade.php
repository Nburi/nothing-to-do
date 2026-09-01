{{--
    "Simple" board: one flat, undivided list. No Inbox/To-Do/Task distinction,
    no triage step — every active on-board task (any `list` value) renders
    together, sorted the same way boardOrdered() already sorts a 3-Things
    column (important first, then due-soon, then manual order). Capture
    always lands directly as a real task (ListConcepts::defaultCaptureList()
    returns 'tasks' under this concept) — there is no Inbox holding pen to
    pass through first. See App\Services\ListConcepts, TaskBoard::simpleTasks()
    and PLAN_LIST_CONCEPTS.md §1/§3 for the full shape.

    `is_today` gets its own spatial "Heute" zone, mirroring a 3-Things
    column's own Heute area exactly — a task enters/leaves Today by being
    dragged into or out of it (desktop: window.simpleListSortable; mobile:
    the same, via the card's drag handle), which writes through
    TaskBoard::reorderSimple($today, $ids) rather than a per-card toggle.
    Mobile additionally keeps the existing right-swipe today/untoday
    gesture (TaskBoard::setTodaySimple(), via swipeIntentSimple()) as a
    second way to move a card in/out of the zone, same as 3-Things' own
    Heute area doesn't stop a card from also being swiped.

    Companion features (Vorbereitung, Notfallmodus, Zeitplan/focus timer,
    Hausaufgaben-Vorschau) stay concept-agnostic per PLAN_LIST_CONCEPTS.md §2
    requirement 6 — the same top-of-page furniture as board-three-things.php
    renders here unchanged. What does NOT carry over is the *per-list*
    pinning those features do inside a 3-Things column (emergency tasks
    numbered per list, task-group boxes per list) — Simple has no per-list
    buckets for them to pin into. Emergency mode's own banner + arrange
    screen and a group's own dashboard remain fully reachable and functional;
    they just don't get a second, column-shaped rendering here.
--}}
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-2xl px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-6'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-6'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-6', 'interaction' => 'drag'])

            @php
                $simpleActive = $this->simpleTasks->where('is_completed', false)->values();
                $simpleToday = $simpleActive->where('is_today', true)->values();
                $simpleRest = $simpleActive->where('is_today', false)->values();
                $simpleDone = $this->simpleTasks->where('is_completed', true)->values();
                $simpleTrulyEmpty = $simpleToday->isEmpty() && $simpleRest->isEmpty();
            @endphp

            <div class="mb-4 rounded-card border border-forest/30 bg-forest-soft/60 p-2">
                <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">
                    <x-logo class="h-3 w-3" />
                    Heute
                </p>
                <div
                    class="flex min-h-[40px] flex-col gap-2"
                    data-list="tasks" data-today="true"
                    x-data
                    x-init="window.simpleListSortable($el, $wire)"
                >
                    @foreach ($simpleToday as $task)
                        @include('livewire.partials.task-card', ['task' => $task])
                    @endforeach
                    @if ($simpleToday->isEmpty())
                        <p class="px-1 py-1.5 text-xs text-ink-faint">Hierher ziehen für den Tagesfokus.</p>
                    @endif
                </div>
            </div>

            <div
                class="flex flex-col gap-2"
                data-list="tasks" data-today="false"
                x-data
                x-init="window.simpleListSortable($el, $wire)"
            >
                @foreach ($simpleRest as $task)
                    @include('livewire.partials.task-card', ['task' => $task])
                @endforeach

                @if ($simpleTrulyEmpty)
                    <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-10 text-center">
                        <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                            <path d="M24 8c8 0 14 5 14 11s-7 10-14 10S11 25 11 19 16 8 24 8Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M24 13.5c5 0 8.5 2.6 8.5 6S29 25 24 25s-8-2-8-5.5 3-6 8-6Z" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="24" cy="19.5" r="1.8" fill="currentColor"/>
                        </svg>
                        <p class="max-w-[26ch] text-xs leading-relaxed text-ink-faint">Nichts offen. Erfasse etwas Neues — es landet direkt hier, ohne Umweg.</p>
                    </div>
                @elseif ($simpleRest->isEmpty())
                    <p class="px-1 py-2 text-xs text-ink-faint">Hierher ziehen, um Aufgaben hinzuzufügen.</p>
                @endif
            </div>

            @if ($simpleDone->isNotEmpty())
                <div class="mt-3 border-t border-line/50 pt-3">
                    <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($simpleDone as $task)
                            @include('livewire.partials.task-card', ['task' => $task])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    <div class="md:hidden">
        <div class="px-4 pb-6 pt-4">
            <div class="mb-4">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-4'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-4'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-4', 'interaction' => 'swipe'])

            @php
                $mobileSimpleActive = $this->simpleTasks->where('is_completed', false)->values();
                $mobileSimpleToday = $mobileSimpleActive->where('is_today', true)->values();
                $mobileSimpleRest = $mobileSimpleActive->where('is_today', false)->values();
                $mobileSimpleDone = $this->simpleTasks->where('is_completed', true)->values();
            @endphp

            @if ($mobileSimpleToday->isNotEmpty())
                <p class="px-1 pt-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">Heute</p>
                <div
                    class="flex flex-col gap-2.5"
                    data-list="tasks" data-today="true"
                    x-data x-init="window.simpleListSortable($el, $wire, '[data-drag-handle]')"
                >
                    @foreach ($mobileSimpleToday as $task)
                        @include('livewire.partials.task-card-mobile', [
                            'task' => $task,
                            'rightIntent' => 'untoday',
                            'leftIntent' => 'edit',
                            'wireMethod' => 'swipeIntentSimple',
                        ])
                    @endforeach
                </div>
                <div class="my-1 border-t border-line"></div>
            @endif

            <div
                class="flex flex-col gap-2.5"
                data-list="tasks" data-today="false"
                x-data x-init="window.simpleListSortable($el, $wire, '[data-drag-handle]')"
            >
                @forelse ($mobileSimpleRest as $task)
                    @include('livewire.partials.task-card-mobile', [
                        'task' => $task,
                        'rightIntent' => 'today',
                        'leftIntent' => 'edit',
                        'wireMethod' => 'swipeIntentSimple',
                    ])
                @empty
                    @if ($mobileSimpleToday->isEmpty())
                        <x-board-empty>Nichts offen. Erfasse etwas Neues — es landet direkt hier, ohne Umweg.</x-board-empty>
                    @endif
                @endforelse
            </div>

            @if ($mobileSimpleDone->isNotEmpty())
                <div class="mt-0.5 border-t border-line/50 pt-1">
                    <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                </div>
                @foreach ($mobileSimpleDone as $task)
                    @include('livewire.partials.task-card-mobile', [
                        'task' => $task,
                        'rightIntent' => $task->is_today ? 'untoday' : 'today',
                        'leftIntent' => 'edit',
                        'wireMethod' => 'swipeIntentSimple',
                    ])
                @endforeach
            @endif
        </div>
    </div>

    {{-- ════════════════ EDIT SHEET ════════════════ --}}
    @include('livewire.partials.edit-sheet')

    {{-- ════════════════ PROJECT PICKER (mobile long-press) ════════════════ --}}
    @include('livewire.partials.project-picker-sheet')
