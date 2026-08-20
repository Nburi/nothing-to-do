<div>
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-6'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-6'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-6', 'interaction' => 'drag'])

            <div class="grid grid-cols-4 gap-5">
                @include('livewire.partials.column', [
                    'list' => 'inbox', 'title' => 'Inbox', 'count' => $this->counts['inbox'],
                    'rest' => $this->inbox->where('is_completed', false)->values(),
                    'completed' => $this->inbox->where('is_completed', true)->values(),
                    'empty' => 'Posteingang leer. Saubere Ausgangslage.',
                    'emergencyProject' => $this->emergencyProject, 'emergencyTasks' => $this->emergencyTasksFor('inbox'),
                    'groupBoxes' => $this->groupBoxesFor('inbox'),
                ])
                @include('livewire.partials.column', [
                    'list' => 'todos', 'title' => 'To-Dos', 'count' => $this->counts['todos'],
                    'hasToday' => true, 'today' => $this->todosToday, 'rest' => $this->todosRest,
                    'completed' => $this->todosAll->where('is_completed', true)->values(),
                    'empty' => 'Keine To-Dos. Zieh etwas aus der Inbox herüber.',
                    'emergencyProject' => $this->emergencyProject, 'emergencyTasks' => $this->emergencyTasksFor('todos'),
                    'groupBoxes' => $this->groupBoxesFor('todos'),
                ])
                @include('livewire.partials.column', [
                    'list' => 'tasks', 'title' => 'Tasks', 'count' => $this->counts['tasks'],
                    'hasToday' => true, 'today' => $this->tasksToday, 'rest' => $this->tasksRest,
                    'completed' => $this->tasksAll->where('is_completed', true)->values(),
                    'empty' => 'Keine Tasks. Grössere Brocken landen hier.',
                    'emergencyProject' => $this->emergencyProject, 'emergencyTasks' => $this->emergencyTasksFor('tasks'),
                    'groupBoxes' => $this->groupBoxesFor('tasks'),
                ])

                {{-- Projects: one card per project, opens the project page. --}}
                <section class="flex min-h-[55vh] flex-col">
                    <header class="mb-3 flex items-center justify-between px-1">
                        <h2 class="flex items-center gap-2 text-sm font-medium text-ink">
                            Projekte
                            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->counts['projects'] }}</span>
                        </h2>
                    </header>

                    {{-- Drop a task here to spin it into its own new project. Only
                         takes up space while a task is actually being dragged. --}}
                    <div
                        class="mb-0 max-h-0 overflow-hidden opacity-0 transition-all duration-200 [body.dragging-task_&]:mb-3 [body.dragging-task_&]:max-h-16 [body.dragging-task_&]:opacity-100"
                        x-data
                        x-init="window.newProjectDropZone($el, $wire)"
                    >
                        <div class="flex h-16 items-center justify-center gap-2 rounded-card border border-dashed border-forest/60 bg-forest-soft/50 text-xs font-medium text-forest">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Ablegen für neues Projekt
                        </div>
                    </div>

                    {{-- Standalone project tasks (list=projects, no project_id) --}}
                    @php
                        $projectTasksActive = $this->projectTasks->where('is_completed', false)->values();
                        $projectTasksDone   = $this->projectTasks->where('is_completed', true)->values();
                    @endphp
                    @if ($projectTasksActive->isNotEmpty() || $projectTasksDone->isNotEmpty())
                        <div
                            class="mb-2.5 rounded-card border border-line/70 bg-surface/50 p-1.5"
                            data-list="projects"
                            data-today="false"
                            x-data
                            x-init="window.boardSortable($el, $wire)"
                        >
                            @foreach ($projectTasksActive as $task)
                                @include('livewire.partials.task-card', ['task' => $task])
                            @endforeach
                            @if ($projectTasksDone->isNotEmpty())
                                <div class="mt-1 border-t border-line/50 pt-1">
                                    @foreach ($projectTasksDone as $task)
                                        @include('livewire.partials.task-card', ['task' => $task])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Drop target so tasks can still be dragged here even with none
                             yet — only takes up space while a task is being dragged. --}}
                        <div
                            class="mb-0 max-h-0 overflow-hidden rounded-card border border-dashed border-line/40 transition-all duration-200 [body.dragging-task_&]:mb-2.5 [body.dragging-task_&]:max-h-16 [body.dragging-task_&]:border-line [body.dragging-task_&]:bg-surface/60"
                            data-list="projects"
                            data-today="false"
                            x-data
                            x-init="window.boardSortable($el, $wire)"
                        ></div>
                    @endif

                    <div class="flex flex-1 flex-col gap-2.5">
                        @forelse ($this->projects as $project)
                            @include('livewire.partials.project-card', ['project' => $project])
                        @empty
                            @if ($projectTasksActive->isEmpty() && $projectTasksDone->isEmpty())
                                <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-10 text-center">
                                    <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                        <path d="M8 16a2 2 0 0 1 2-2h9l3 4h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V16Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    </svg>
                                    <p class="max-w-[22ch] text-xs leading-relaxed text-ink-faint">Noch keine Projekte. Sammle hier grössere Vorhaben.</p>
                                </div>
                            @endif
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    <div class="md:hidden">
        <div class="px-4 pb-28 pt-4">
            <div class="mb-4">
                @include('livewire.partials.schedule-strip')
            </div>
            @include('livewire.partials.emergency-banner', ['spacing' => 'mb-4'])
            @include('livewire.partials.prepare-prompt', ['spacing' => 'mb-4'])
            @include('livewire.partials.homework-preview-strip', ['spacing' => 'mb-4', 'interaction' => 'swipe'])

            <div class="flex flex-col gap-2.5">
                @switch($mobileTab)
                    @case('inbox')
                        @php
                            $inboxActive = $this->inbox->where('is_completed', false)->values();
                            $inboxDone = $this->inbox->where('is_completed', true);
                            $emergencyProject = $this->emergencyProject;
                            $emergencyTasks = $this->emergencyTasksFor('inbox');
                            $importantRest = $emergencyProject ? $inboxActive->where('is_important', true)->values() : collect();
                            $normalRest = $emergencyProject ? $inboxActive->where('is_important', false)->values() : $inboxActive;
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'inbox',
                            'normalRest' => $normalRest,
                            'done' => $inboxDone,
                            'emergencyProject' => $emergencyProject,
                            'emergencyTasks' => $emergencyTasks,
                            'importantRest' => $importantRest,
                            'groupBoxes' => $this->groupBoxesFor('inbox'),
                            'emptyMessage' => 'Posteingang leer. Saubere Ausgangslage.',
                        ])
                        @break

                    @case('todos')
                        @php
                            $todosDone = $this->todosAll->where('is_completed', true);
                            $emergencyProject = $this->emergencyProject;
                            $emergencyTasks = $this->emergencyTasksFor('todos');
                            $importantRest = $emergencyProject ? $this->todosRest->where('is_important', true)->values() : collect();
                            $normalRest = $emergencyProject ? $this->todosRest->where('is_important', false)->values() : $this->todosRest;
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'todos',
                            'today' => $this->todosToday,
                            'normalRest' => $normalRest,
                            'done' => $todosDone,
                            'emergencyProject' => $emergencyProject,
                            'emergencyTasks' => $emergencyTasks,
                            'importantRest' => $importantRest,
                            'groupBoxes' => $this->groupBoxesFor('todos'),
                            'emptyMessage' => 'Keine To-Dos. Wisch eine Inbox-Aufgabe nach rechts.',
                        ])
                        @break

                    @case('tasks')
                        @php
                            $tasksDone = $this->tasksAll->where('is_completed', true);
                            $emergencyProject = $this->emergencyProject;
                            $emergencyTasks = $this->emergencyTasksFor('tasks');
                            $importantRest = $emergencyProject ? $this->tasksRest->where('is_important', true)->values() : collect();
                            $normalRest = $emergencyProject ? $this->tasksRest->where('is_important', false)->values() : $this->tasksRest;
                        @endphp
                        @include('livewire.partials.mobile-task-list', [
                            'list' => 'tasks',
                            'today' => $this->tasksToday,
                            'normalRest' => $normalRest,
                            'done' => $tasksDone,
                            'emergencyProject' => $emergencyProject,
                            'emergencyTasks' => $emergencyTasks,
                            'importantRest' => $importantRest,
                            'groupBoxes' => $this->groupBoxesFor('tasks'),
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

                    @case('projects')
                        @php
                            $mobileProjectTasksActive = $this->projectTasks->where('is_completed', false)->values();
                            $mobileProjectTasksDone   = $this->projectTasks->where('is_completed', true)->values();
                        @endphp
                        @if ($mobileProjectTasksActive->isNotEmpty())
                            <div
                                class="flex flex-col gap-2.5"
                                data-list="projects" data-today="false"
                                x-data x-init="window.boardSortable($el, $wire, '[data-drag-handle]')"
                            >
                                @foreach ($mobileProjectTasksActive as $task)
                                    @include('livewire.partials.task-card-mobile', ['task' => $task])
                                @endforeach
                            </div>
                            @if ($this->projects->isNotEmpty())
                                <div class="my-1 border-t border-line"></div>
                            @endif
                        @endif
                        @forelse ($this->projects as $project)
                            @include('livewire.partials.project-card', ['project' => $project])
                        @empty
                            @if ($mobileProjectTasksActive->isEmpty() && $mobileProjectTasksDone->isEmpty())
                                <x-board-empty>Noch keine Projekte. Sammle hier grössere Vorhaben.</x-board-empty>
                            @endif
                        @endforelse
                        @if ($mobileProjectTasksDone->isNotEmpty())
                            <div class="mt-0.5 border-t border-line/50 pt-1">
                                <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                                @foreach ($mobileProjectTasksDone as $task)
                                    @include('livewire.partials.task-card-mobile', ['task' => $task])
                                @endforeach
                            </div>
                        @endif
                        @break
                @endswitch
            </div>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/90 backdrop-blur-sm">
            <div class="mx-auto grid max-w-md grid-cols-5">
                @php
                    $tabs = [
                        'inbox' => ['label' => 'Inbox', 'path' => 'M3 13h4l1.5 2.5h7L17 13h4M5 6h14l2 7v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4l2-7Z'],
                        'todos' => ['label' => 'To-Dos', 'path' => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01'],
                        'tasks' => ['label' => 'Tasks', 'path' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z'],
                        'today' => ['label' => 'Heute', 'path' => 'M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z'],
                        'projects' => ['label' => 'Projekte', 'path' => 'M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z'],
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

    {{-- ════════════════ PROJECT PICKER (mobile long-press) ════════════════ --}}
    @include('livewire.partials.project-picker-sheet')
</div>
