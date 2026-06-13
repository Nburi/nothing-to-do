<div>
    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            <form wire:submit="addTask" class="mb-6">
                <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 shadow-map focus-within:border-ink-faint/60">
                    <x-logo class="h-4 w-4 flex-none text-ink-faint" />
                    <input
                        type="text"
                        wire:model="newTitle"
                        placeholder="Was steht an?"
                        autocomplete="off"
                        class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                    />
                    <label class="sr-only" for="newList-d">Zielliste</label>
                    <select id="newList-d" wire:model="newList" class="rounded-card border-line bg-paper py-1.5 pl-2.5 pr-8 text-sm text-ink-soft focus:border-overprint focus:ring-0">
                        <option value="inbox">Inbox</option>
                        <option value="todos">To-Dos</option>
                        <option value="tasks">Tasks</option>
                    </select>
                    <button type="submit" class="flex-none rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        Hinzufügen
                    </button>
                </div>
                @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
            </form>

            <div class="grid grid-cols-3 gap-5">
                @include('livewire.partials.column', [
                    'list' => 'inbox', 'title' => 'Inbox', 'count' => $this->counts['inbox'],
                    'rest' => $this->inbox, 'empty' => 'Posteingang leer. Saubere Ausgangslage.',
                ])
                @include('livewire.partials.column', [
                    'list' => 'todos', 'title' => 'To-Dos', 'count' => $this->counts['todos'],
                    'hasToday' => true, 'today' => $this->todosToday, 'rest' => $this->todosRest,
                    'empty' => 'Keine To-Dos. Zieh etwas aus der Inbox herüber.',
                ])
                @include('livewire.partials.column', [
                    'list' => 'tasks', 'title' => 'Tasks', 'count' => $this->counts['tasks'],
                    'hasToday' => true, 'today' => $this->tasksToday, 'rest' => $this->tasksRest,
                    'empty' => 'Keine Tasks. Grössere Brocken landen hier.',
                ])
            </div>
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    <div class="md:hidden">
        <div class="px-4 pb-28 pt-4">
            @if ($mobileTab !== 'today')
                <form wire:submit="addTask" class="mb-4">
                    <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2.5 shadow-map focus-within:border-ink-faint/60">
                        <input
                            type="text"
                            wire:model="newTitle"
                            placeholder="@switch($mobileTab)@case('todos')Neue To-Do …@break @case('tasks')Neue Task …@break @default In die Inbox …@endswitch"
                            autocomplete="off"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                        />
                        <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition active:scale-95" aria-label="Hinzufügen">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                </form>
            @endif

            <div class="flex flex-col gap-2.5">
                @switch($mobileTab)
                    @case('inbox')
                        @forelse ($this->inbox as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @empty
                            <x-board-empty>Posteingang leer. Saubere Ausgangslage.</x-board-empty>
                        @endforelse
                        @break

                    @case('todos')
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
                            @if ($this->todosToday->isEmpty())
                                <x-board-empty>Keine To-Dos. Wisch eine Inbox-Aufgabe nach rechts.</x-board-empty>
                            @endif
                        @endforelse
                        @break

                    @case('tasks')
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
                            @if ($this->tasksToday->isEmpty())
                                <x-board-empty>Keine Tasks. Grössere Brocken landen hier.</x-board-empty>
                            @endif
                        @endforelse
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
    @if ($editingId)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Aufgabe bearbeiten">
            <div class="absolute inset-0 bg-ink/40" wire:click="cancelEdit"></div>
            <div class="animate-rise relative w-full max-w-md rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.cancelEdit()">
                <form wire:submit="saveEdit" class="space-y-4">
                    <h2 class="text-base font-medium text-ink">Aufgabe bearbeiten</h2>

                    <div>
                        <label for="editTitle" class="mb-1 block text-xs font-medium text-ink-soft">Titel</label>
                        <input id="editTitle" type="text" wire:model="editTitle" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        @error('editTitle') <p class="mt-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="editDeadline" class="mb-1 block text-xs font-medium text-ink-soft">Deadline · hart</label>
                            <input id="editDeadline" type="date" wire:model="editDeadline" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                        <div>
                            <label for="editDueDate" class="mb-1 block text-xs font-medium text-ink-soft">Wunschtermin · weich</label>
                            <input id="editDueDate" type="date" wire:model="editDueDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0" />
                        </div>
                    </div>
                    @error('editDeadline') <p class="text-xs text-signal">{{ $message }}</p> @enderror
                    @error('editDueDate') <p class="text-xs text-signal">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-between pt-1">
                        <button type="button" wire:click="deleteTask({{ $editingId }})" class="rounded-card px-2 py-1.5 text-sm text-signal transition hover:bg-signal-soft">
                            Löschen
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="cancelEdit" class="rounded-card px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper">
                                Abbrechen
                            </button>
                            <button type="submit" class="rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                                Speichern
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
