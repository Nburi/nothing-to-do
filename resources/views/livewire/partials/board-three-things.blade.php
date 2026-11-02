{{--
    "3 Things" board: To-Do vs. Task, sorted by size and shape,
    with Inbox → triage. Extracted verbatim from what used to be the whole of
    task-board.blade.php when the `@switch` seam (ListConcepts, see
    task-board.blade.php and CLAUDE.md's "To-Do-Listen-Konzepte") was added —
    a pure refactor, no behavior change. This partial owns everything about
    how the board looks and behaves for this concept, including its own
    mobile bottom-nav shape (per-concept by design, see PLAN_LIST_CONCEPTS.md
    §4 — a 3-tab/4-quadrant/Kanban-column nav is inherently concept-specific)
    and the edit sheet include at the bottom, which every concept needs and so
    is simply part of what "3 Things" renders, not shared infrastructure
    sitting outside the switch.
--}}
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>

            <div class="grid grid-cols-3 gap-5">
                @include('livewire.partials.column', [
                    'list' => 'inbox', 'title' => 'Inbox', 'count' => $this->counts['inbox'],
                    'rest' => $this->inbox->where('is_completed', false)->values(),
                    'completed' => $this->inbox->where('is_completed', true)->values(),
                    'empty' => 'Posteingang leer. Saubere Ausgangslage.',
                ])
                @include('livewire.partials.column', [
                    'list' => 'todos', 'title' => 'To-Dos', 'count' => $this->counts['todos'],
                    'hasToday' => true, 'today' => $this->todosToday, 'rest' => $this->todosRest,
                    'completed' => $this->todosAll->where('is_completed', true)->values(),
                    'empty' => 'Keine To-Dos. Zieh etwas aus der Inbox herüber.',
                ])
                @include('livewire.partials.column', [
                    'list' => 'tasks', 'title' => 'Tasks', 'count' => $this->counts['tasks'],
                    'hasToday' => true, 'today' => $this->tasksToday, 'rest' => $this->tasksRest,
                    'completed' => $this->tasksAll->where('is_completed', true)->values(),
                    'empty' => 'Keine Tasks. Grössere Brocken landen hier.',
                ])
            </div>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    <div class="md:hidden">
        <div class="px-4 pb-28 pt-4">
            <div class="mb-4">
                @include('livewire.partials.schedule-strip')
            </div>

            <div class="flex flex-col gap-2.5">
                @switch($mobileTab)
                    @case('inbox')
                        @php
                            $inboxActive = $this->inbox->where('is_completed', false)->values();
                            $inboxDone = $this->inbox->where('is_completed', true);
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'inbox',
                            'rest' => $inboxActive,
                            'done' => $inboxDone,
                            'emptyMessage' => 'Posteingang leer. Saubere Ausgangslage.',
                        ])
                        @break

                    @case('todos')
                        @php
                            $todosDone = $this->todosAll->where('is_completed', true);
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'todos',
                            'today' => $this->todosToday,
                            'rest' => $this->todosRest,
                            'done' => $todosDone,
                            'emptyMessage' => 'Keine To-Dos. Wisch eine Inbox-Aufgabe nach rechts.',
                        ])
                        @break

                    @case('tasks')
                        @php
                            $tasksDone = $this->tasksAll->where('is_completed', true);
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'tasks',
                            'today' => $this->tasksToday,
                            'rest' => $this->tasksRest,
                            'done' => $tasksDone,
                            'emptyMessage' => 'Keine Tasks. Grössere Brocken landen hier.',
                        ])
                        @break

                    @case('today')
                        @forelse ($this->today as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @empty
                            <x-board-empty>Noch kein Tagesfokus. Wisch in To-Dos oder Tasks nach rechts.</x-board-empty>
                        @endforelse
                        @break
                @endswitch
            </div>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/90 backdrop-blur-sm">
            <div class="mx-auto grid max-w-md grid-cols-4">
                @php
                    $tabs = [
                        'inbox' => ['label' => 'Inbox', 'path' => 'M3 13h4l1.5 2.5h7L17 13h4M5 6h14l2 7v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4l2-7Z'],
                        'todos' => ['label' => 'To-Dos', 'path' => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01'],
                        'tasks' => ['label' => 'Tasks', 'path' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z'],
                        'today' => ['label' => 'Heute', 'path' => 'M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z'],
                    ];
                @endphp
                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        wire:click="setMobileTab('{{ $key }}')"
                        @class([
                            'relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium transition',
                            'text-forest' => $mobileTab === $key,
                            'text-ink-faint' => $mobileTab !== $key,
                        ])
                        @if($mobileTab === $key) aria-current="page" @endif
                    >
                        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $tab['path'] }}" />
                        </svg>
                        {{ $tab['label'] }}
                        @if ($this->counts[$key] > 0)
                            <span class="tnum absolute right-[18%] top-1.5 min-w-[15px] rounded-full bg-overprint px-1 text-[9px] leading-[15px] text-white">{{ $this->counts[$key] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- ════════════════ EDIT SHEET ════════════════ --}}
    @include('livewire.partials.edit-sheet')
