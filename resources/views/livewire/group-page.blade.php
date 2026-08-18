<div>
    @php
        $done = $this->doneCount;
        $total = $this->totalCount;
        $pct = $total > 0 ? round(($done / $total) * 100) : 0;
        $lists = [
            'inbox' => ['title' => 'Inbox', 'empty' => 'Unsortiertes zu dieser Gruppe landet hier.'],
            'todos' => ['title' => 'To-Dos', 'empty' => 'Kleine Schritte, die schnell erledigt sind.'],
            'tasks' => ['title' => 'Tasks', 'empty' => 'Grössere Brocken dieser Gruppe.'],
        ];
    @endphp

    {{-- ════════════════ DESKTOP (≥ md) ════════════════ --}}
    <div class="hidden md:block">
        <div class="mx-auto max-w-[1400px] px-6 py-6">
            @include('livewire.partials.group-header', ['done' => $done, 'total' => $total, 'pct' => $pct, 'compact' => false])

            <div class="mt-5 grid grid-cols-4 gap-5">
                @foreach ($lists as $list => $meta)
                    @include('livewire.partials.group-column', [
                        'list' => $list,
                        'title' => $meta['title'],
                        'empty' => $meta['empty'],
                        'active' => $this->activeIn($list),
                        'completed' => $this->completedIn($list),
                    ])
                @endforeach

                <section class="flex min-h-[55vh] flex-col">
                    <header class="mb-3 flex items-center justify-between px-1">
                        <h2 class="text-sm font-medium text-ink">Notizen</h2>
                    </header>
                    @include('livewire.partials.group-notes')
                </section>
            </div>

            {{-- Pull loose board tasks into the group. --}}
            @if ($this->boardInboxTasks->isNotEmpty())
                <div x-data="{ open: false }" class="mt-6 max-w-md rounded-card border border-line bg-paper/60">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm font-medium text-ink-soft transition hover:text-ink"
                        :aria-expanded="open"
                    >
                        <span>Aus der Inbox hinzufügen
                            <span class="tnum ml-1 rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->boardInboxTasks->count() }}</span>
                        </span>
                        <svg class="h-4 w-4 flex-none text-ink-faint transition" :class="open && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <div x-show="open" x-collapse style="display: none;" class="border-t border-line px-2 py-2">
                        <ul class="flex flex-col gap-1">
                            @foreach ($this->boardInboxTasks as $task)
                                <li wire:key="group-pick-{{ $task->id }}">
                                    <button
                                        type="button"
                                        wire:click="assignToGroup({{ $task->id }})"
                                        class="group/pick flex w-full items-center gap-2.5 rounded-card px-2.5 py-2 text-left transition hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                    >
                                        <span class="grid h-5 w-5 flex-none place-items-center rounded-card border border-line text-ink-faint transition group-hover/pick:border-forest group-hover/pick:text-forest">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </span>
                                        <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $task->title }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════ MOBILE (< md) ════════════════ --}}
    <div class="md:hidden">
        <div class="px-4 pb-28 pt-4">
            @include('livewire.partials.group-header', ['done' => $done, 'total' => $total, 'pct' => $pct, 'compact' => true])

            <div class="mt-4 flex flex-col gap-2.5">
                @if ($mobileTab === 'notes')
                    @include('livewire.partials.group-notes')
                @else
                    @php
                        $active = $this->activeIn($mobileTab);
                        $completed = $this->completedIn($mobileTab);
                    @endphp

                    <form wire:submit="addTask('{{ $mobileTab }}')">
                        <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2.5 shadow-map focus-within:border-ink-faint/60">
                            <input
                                type="text"
                                wire:model="newTitle.{{ $mobileTab }}"
                                placeholder="Hinzufügen …"
                                autocomplete="off"
                                aria-label="Aufgabe hinzufügen"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                            />
                            <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Hinzufügen">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                        @error('newTitle.'.$mobileTab) <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                    </form>

                    <div
                        class="flex flex-col gap-2.5"
                        data-list="{{ $mobileTab }}" data-today="false"
                        wire:key="group-mobile-zone-{{ $mobileTab }}"
                        x-data x-init="window.boardSortable($el, $wire, '[data-drag-handle]')"
                    >
                        @foreach ($active as $task)
                            @include('livewire.partials.task-card-mobile', ['task' => $task])
                        @endforeach
                    </div>

                    @if ($active->isEmpty() && $completed->isEmpty())
                        <x-board-empty>{{ $lists[$mobileTab]['empty'] }}</x-board-empty>
                    @endif

                    @if ($completed->isNotEmpty())
                        <div class="mt-0.5 border-t border-line/50 pt-2">
                            <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                            <div class="flex flex-col gap-2.5">
                                @foreach ($completed as $task)
                                    @include('livewire.partials.task-card-mobile', ['task' => $task])
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/90 backdrop-blur-sm">
            <div class="mx-auto grid max-w-md grid-cols-4">
                @php
                    $tabs = [
                        'inbox' => ['label' => 'Inbox', 'path' => 'M3 13h4l1.5 2.5h7L17 13h4M5 6h14l2 7v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4l2-7Z'],
                        'todos' => ['label' => 'To-Dos', 'path' => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01'],
                        'tasks' => ['label' => 'Tasks', 'path' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z'],
                        'notes' => ['label' => 'Notizen', 'path' => 'M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM8 11h8M8 15h5'],
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
                        @if ($key !== 'notes' && $this->counts[$key] > 0)
                            <span class="tnum absolute right-[22%] top-1.5 min-w-[15px] rounded-full bg-overprint px-1 text-[9px] leading-[15px] text-white">{{ $this->counts[$key] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- ════════════════ EDIT SHEET ════════════════ --}}
    @include('livewire.partials.edit-sheet')

    {{-- ════════════════ MOVE SHEET (mobile long-press) ════════════════ --}}
    @include('livewire.partials.group-task-picker-sheet')
</div>
