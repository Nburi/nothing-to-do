<div>
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            <div class="mb-6">
                @include('livewire.partials.schedule-strip')
            </div>
            <form
                wire:submit="addTask"
                x-data="{ exp: false }"
                @task-added.window="exp = false"
                @click.outside="exp = false"
                class="mb-6"
            >
                <div
                    class="rounded-card border border-line bg-surface shadow-map transition-[border-color]"
                    :class="exp ? 'border-ink-faint/60' : 'focus-within:border-ink-faint/60'"
                >
                    <div class="flex items-center gap-2 px-3 py-2">
                        <x-logo class="h-4 w-4 flex-none text-ink-faint" />
                        <input
                            type="text"
                            wire:model="newTitle"
                            placeholder="Was steht an?"
                            autocomplete="off"
                            @focus="exp = true"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                        />
                        {{-- List dropdown --}}
                        <div
                            x-data="{
                                open: false,
                                list: $wire.entangle('newList'),
                                options: [
                                    { value: 'inbox', label: 'Inbox' },
                                    { value: 'todos', label: 'To-Dos' },
                                    { value: 'tasks', label: 'Tasks' },
                                ],
                                get label() { return this.options.find(o => o.value === this.list)?.label ?? 'Inbox'; },
                            }"
                            class="relative flex-none"
                        >
                            <span class="sr-only">Zielliste</span>
                            <button
                                type="button"
                                @click.stop="open = !open; exp = true"
                                @click.outside="open = false"
                                @keydown.escape.window="open = false"
                                :aria-expanded="open"
                                aria-haspopup="listbox"
                                class="flex items-center gap-1.5 rounded-card border border-line bg-paper py-1.5 pl-3 pr-2 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                            >
                                <span x-text="label" class="min-w-[3.5rem] text-left"></span>
                                <svg class="h-3.5 w-3.5 flex-none text-ink-faint transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 top-full z-20 mt-1 min-w-full origin-top-right overflow-hidden rounded-card border border-line bg-surface p-1 shadow-map"
                                role="listbox"
                                style="display: none;"
                            >
                                <template x-for="opt in options" :key="opt.value">
                                    <button
                                        type="button"
                                        @click="list = opt.value; open = false"
                                        role="option"
                                        :aria-selected="list === opt.value"
                                        class="flex w-full items-center gap-2 rounded-[0.4rem] px-2.5 py-1.5 text-left text-sm transition"
                                        :class="list === opt.value ? 'bg-paper font-medium text-ink' : 'text-ink-soft hover:bg-paper hover:text-ink'"
                                    >
                                        <span x-text="opt.label"></span>
                                        <svg x-show="list === opt.value" class="ml-auto h-3 w-3 flex-none text-forest" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                            <path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="submit" class="flex-none rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                            Hinzufügen
                        </button>
                    </div>

                    {{-- Expanded: deadline + due date --}}
                    <div
                        x-show="exp"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="grid grid-cols-2 gap-3 border-t border-line/60 px-3 py-2.5"
                        style="display: none;"
                    >
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline</label>
                            <input type="date" wire:model="newDeadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Wunschtermin</label>
                            <input type="date" wire:model="newDueDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                    </div>
                </div>
                @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
            </form>

            <div class="grid grid-cols-4 gap-5">
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

                {{-- Projects: one card per project, opens the project page. --}}
                <section class="flex min-h-[55vh] flex-col">
                    <header class="mb-3 flex items-center justify-between px-1">
                        <h2 class="flex items-center gap-2 text-sm font-medium text-ink">
                            Projekte
                            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->counts['projects'] }}</span>
                        </h2>
                    </header>

                    <form
                        wire:submit="addProject"
                        x-data="{ exp: false }"
                        @project-added.window="exp = false"
                        @click.outside="exp = false"
                        class="mb-3"
                    >
                        <div
                            class="rounded-card border border-line bg-surface shadow-map transition-[border-color]"
                            :class="exp ? 'border-ink-faint/60' : 'focus-within:border-ink-faint/60'"
                        >
                            <div class="flex items-center gap-2 px-2.5 py-1.5">
                                <input
                                    type="text"
                                    wire:model="newProjectName"
                                    placeholder="Neues Projekt …"
                                    autocomplete="off"
                                    @focus="exp = true"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                                />
                                <button type="submit" class="grid h-7 w-7 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest" aria-label="Projekt hinzufügen">
                                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <div
                                x-show="exp"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="border-t border-line/60 px-2.5 py-2"
                                style="display: none;"
                            >
                                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline</label>
                                <input type="date" wire:model="newProjectDeadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                            </div>
                        </div>
                        @error('newProjectName') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </form>

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
                        {{-- Invisible drop target so tasks can always be dragged here --}}
                        <div
                            class="mb-2.5 min-h-[2.5rem] rounded-card border border-dashed border-line/40 transition-colors [body.dragging-task_&]:border-line [body.dragging-task_&]:bg-surface/60"
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
            @if ($mobileTab === 'projects')
                <form
                    wire:submit="addProject"
                    x-data="{ exp: false }"
                    @project-added.window="exp = false"
                    @click.outside="exp = false"
                    class="mb-4"
                >
                    <div
                        class="rounded-card border border-line bg-surface shadow-map transition-[border-color]"
                        :class="exp ? 'border-ink-faint/60' : 'focus-within:border-ink-faint/60'"
                    >
                        <div class="flex items-center gap-2 px-3 py-2.5">
                            <input
                                type="text"
                                wire:model="newProjectName"
                                placeholder="Neues Projekt …"
                                autocomplete="off"
                                @focus="exp = true"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                            />
                            <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition active:scale-95" aria-label="Projekt hinzufügen">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                        <div
                            x-show="exp"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="border-t border-line/60 px-3 py-2.5"
                            style="display: none;"
                        >
                            <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline</label>
                            <input type="date" wire:model="newProjectDeadline" class="w-full rounded-card border-line bg-paper text-[15px] text-ink focus:border-overprint focus:ring-0" />
                        </div>
                    </div>
                    @error('newProjectName') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                </form>
            @elseif ($mobileTab !== 'today')
                <form
                    wire:submit="addTask"
                    x-data="{ exp: false }"
                    @task-added.window="exp = false"
                    @click.outside="exp = false"
                    class="mb-4"
                >
                    <div
                        class="rounded-card border border-line bg-surface shadow-map transition-[border-color]"
                        :class="exp ? 'border-ink-faint/60' : 'focus-within:border-ink-faint/60'"
                    >
                        <div class="flex items-center gap-2 px-3 py-2.5">
                            <input
                                type="text"
                                wire:model="newTitle"
                                placeholder="@switch($mobileTab)@case('todos')Neue To-Do …@break @case('tasks')Neue Task …@break @default In die Inbox …@endswitch"
                                autocomplete="off"
                                @focus="exp = true"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                            />
                            <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition active:scale-95" aria-label="Hinzufügen">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                        <div
                            x-show="exp"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="grid grid-cols-2 gap-3 border-t border-line/60 px-3 py-2.5"
                            style="display: none;"
                        >
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Deadline</label>
                                <input type="date" wire:model="newDeadline" class="w-full rounded-card border-line bg-paper text-[15px] text-ink focus:border-overprint focus:ring-0" />
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-ink-faint">Wunschtermin</label>
                                <input type="date" wire:model="newDueDate" class="w-full rounded-card border-line bg-paper text-[15px] text-ink focus:border-overprint focus:ring-0" />
                            </div>
                        </div>
                    </div>
                    @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                </form>
            @endif

            <div class="flex flex-col gap-2.5">
                @switch($mobileTab)
                    @case('inbox')
                        @php $inboxActive = $this->inbox->where('is_completed', false); $inboxDone = $this->inbox->where('is_completed', true); @endphp
                        @forelse ($inboxActive as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @empty
                            @if ($inboxDone->isEmpty())
                                <x-board-empty>Posteingang leer. Saubere Ausgangslage.</x-board-empty>
                            @endif
                        @endforelse
                        @if ($inboxDone->isNotEmpty())
                            <div class="mt-0.5 border-t border-line/50 pt-1">
                                <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                            </div>
                            @foreach ($inboxDone as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
                        @endif
                        @break

                    @case('todos')
                        @php $todosDone = $this->todosAll->where('is_completed', true); @endphp
                        @if ($this->todosToday->isNotEmpty())
                            <p class="px-1 pt-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">Heute</p>
                            @foreach ($this->todosToday as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
                            <div class="my-1 border-t border-line"></div>
                        @endif
                        @forelse ($this->todosRest as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @empty
                            @if ($this->todosToday->isEmpty() && $todosDone->isEmpty())
                                <x-board-empty>Keine To-Dos. Wisch eine Inbox-Aufgabe nach rechts.</x-board-empty>
                            @endif
                        @endforelse
                        @if ($todosDone->isNotEmpty())
                            <div class="mt-0.5 border-t border-line/50 pt-1">
                                <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                            </div>
                            @foreach ($todosDone as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
                        @endif
                        @break

                    @case('tasks')
                        @php $tasksDone = $this->tasksAll->where('is_completed', true); @endphp
                        @if ($this->tasksToday->isNotEmpty())
                            <p class="px-1 pt-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">Heute</p>
                            @foreach ($this->tasksToday as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
                            <div class="my-1 border-t border-line"></div>
                        @endif
                        @forelse ($this->tasksRest as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @empty
                            @if ($this->tasksToday->isEmpty() && $tasksDone->isEmpty())
                                <x-board-empty>Keine Tasks. Grössere Brocken landen hier.</x-board-empty>
                            @endif
                        @endforelse
                        @if ($tasksDone->isNotEmpty())
                            <div class="mt-0.5 border-t border-line/50 pt-1">
                                <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                            </div>
                            @foreach ($tasksDone as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
                        @endif
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
                            @foreach ($mobileProjectTasksActive as $task)
                                @include('livewire.partials.task-card-mobile', ['task' => $task])
                            @endforeach
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
</div>
