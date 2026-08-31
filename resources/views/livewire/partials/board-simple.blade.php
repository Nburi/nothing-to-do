{{--
    "Simple" board: one flat, undivided list. No Inbox/To-Do/Task distinction,
    no triage step — every active on-board task (any `list` value) renders
    together, sorted the same way boardOrdered() already sorts a 3-Things
    column (important first, then due-soon, then manual order). Capture
    always lands directly as a real task (ListConcepts::defaultCaptureList()
    returns 'tasks' under this concept) — there is no Inbox holding pen to
    pass through first. See App\Services\ListConcepts, TaskBoard::simpleTasks()
    and PLAN_LIST_CONCEPTS.md §1/§3 for the full shape.

    `is_today` has no drop zone to live in here (unlike a 3-Things column's
    "Heute" area) — it's a plain per-card toggle badge instead (see the
    "Heute" button below, TaskBoard::setTodaySimple()). That badge is this
    concept's signature moment: tapping it fires a short radiating pulse
    (.today-pulse-ring, app.css) as the stand-in for the spatial "it moved
    into place" feedback a flat list can't give.

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
                $simpleDone = $this->simpleTasks->where('is_completed', true)->values();
            @endphp

            <div
                class="flex flex-col gap-2"
                data-list="tasks" data-today="true"
                x-data
                x-init="window.simpleListSortable($el, $wire)"
            >
                @forelse ($simpleActive as $task)
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            x-data="{ pulsing: false }"
                            @click="pulsing = true; setTimeout(() => pulsing = false, 700)"
                            wire:click="setTodaySimple({{ $task->id }}, {{ $task->is_today ? 'false' : 'true' }})"
                            @class([
                                'today-toggle relative mt-2.5 flex-none whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest',
                                'border-forest bg-forest text-white' => $task->is_today,
                                'border-line text-ink-faint hover:border-forest hover:text-forest' => ! $task->is_today,
                            ])
                            aria-pressed="{{ $task->is_today ? 'true' : 'false' }}"
                            aria-label="{{ $task->is_today ? 'Aus Heute entfernen' : 'Für heute markieren' }}: {{ $task->title }}"
                        >
                            <span x-show="pulsing" x-cloak class="today-pulse-ring" aria-hidden="true"></span>
                            Heute
                        </button>
                        <div class="min-w-0 flex-1">
                            @include('livewire.partials.task-card', ['task' => $task])
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-10 text-center">
                        <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                            <path d="M24 8c8 0 14 5 14 11s-7 10-14 10S11 25 11 19 16 8 24 8Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M24 13.5c5 0 8.5 2.6 8.5 6S29 25 24 25s-8-2-8-5.5 3-6 8-6Z" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="24" cy="19.5" r="1.8" fill="currentColor"/>
                        </svg>
                        <p class="max-w-[26ch] text-xs leading-relaxed text-ink-faint">Nichts offen. Erfasse etwas Neues — es landet direkt hier, ohne Umweg.</p>
                    </div>
                @endforelse
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
                $mobileSimpleDone = $this->simpleTasks->where('is_completed', true)->values();
            @endphp

            <div
                class="flex flex-col gap-2.5"
                data-list="tasks" data-today="true"
                x-data x-init="window.simpleListSortable($el, $wire, '[data-drag-handle]')"
            >
                @forelse ($mobileSimpleActive as $task)
                    @include('livewire.partials.task-card-mobile', [
                        'task' => $task,
                        'rightIntent' => $task->is_today ? 'untoday' : 'today',
                        'leftIntent' => 'edit',
                        'wireMethod' => 'swipeIntentSimple',
                    ])
                @empty
                    <x-board-empty>Nichts offen. Erfasse etwas Neues — es landet direkt hier, ohne Umweg.</x-board-empty>
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
