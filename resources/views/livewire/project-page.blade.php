<div>
    @php
        $done = $this->doneCount;
        $total = $this->totalCount;
        $pct = $total > 0 ? round(($done / $total) * 100) : 0;
    @endphp

    <div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-6 md:pb-12">
        {{-- Back --}}
        <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Alle Listen
        </a>

        {{-- Header --}}
        <div class="mt-3 flex items-start justify-between gap-3">
            @if ($renaming)
                <form wire:submit="saveRename" class="flex flex-1 items-center gap-2">
                    <input
                        type="text"
                        wire:model="projectName"
                        autofocus
                        class="min-w-0 flex-1 rounded-card border-line bg-paper text-lg font-medium text-ink focus:border-overprint focus:ring-0"
                    />
                    <button type="submit" class="flex-none rounded-card bg-forest px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Speichern</button>
                    <button type="button" wire:click="cancelRename" class="flex-none rounded-card px-2 py-1.5 text-sm text-ink-soft transition hover:bg-surface">Abbrechen</button>
                </form>
                @error('projectName') <p class="text-xs text-signal">{{ $message }}</p> @enderror
            @else
                <div class="min-w-0">
                    <h1 class="break-words text-xl font-medium tracking-tight text-ink">{{ $this->project->name }}</h1>
                    <p class="mt-1 text-[13px] text-ink-faint">
                        @if ($total === 0)
                            Noch keine Aufgaben.
                        @else
                            <span class="tnum">{{ $done }}</span> von <span class="tnum">{{ $total }}</span> erledigt
                        @endif
                    </p>
                </div>

                <div x-data="{ open: false }" class="relative flex-none">
                    <button
                        type="button"
                        @click.stop="open = !open"
                        @keydown.escape.window="open = false"
                        class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                        aria-label="Projekt-Aktionen"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="8" cy="3" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="8" cy="13" r="1.4"/></svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition.opacity.duration.120ms
                        @click.outside="open = false"
                        class="absolute right-0 top-9 z-20 w-44 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
                        style="display: none;"
                    >
                        <button type="button" wire:click="$set('renaming', true)" @click="open = false" class="block w-full px-3 py-1.5 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                            Umbenennen
                        </button>
                        <button
                            type="button"
                            wire:click="deleteProject"
                            wire:confirm="Projekt löschen? Offene Aufgaben wandern zurück in die Inbox."
                            @click="open = false"
                            class="block w-full px-3 py-1.5 text-left text-sm text-signal transition hover:bg-signal-soft"
                        >
                            Projekt löschen
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Progress bar --}}
        @if ($total > 0)
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ $total }}" aria-label="Projektfortschritt">
                <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
            </div>
        @endif

        {{-- Quick-add --}}
        <form wire:submit="addTask" class="mt-5">
            <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2.5 shadow-map focus-within:border-ink-faint/60">
                <input
                    type="text"
                    wire:model="newTitle"
                    placeholder="Aufgabe zum Projekt …"
                    autocomplete="off"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                />
                <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Hinzufügen">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
            @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
        </form>

        {{-- Task list --}}
        <div class="mt-4 flex flex-col gap-2">
            @forelse ($this->tasks as $task)
                @include('livewire.partials.project-task-card', ['task' => $task])
            @empty
                <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-12 text-center">
                    <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <path d="M8 16a2 2 0 0 1 2-2h9l3 4h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V16Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                    <p class="max-w-[26ch] text-xs leading-relaxed text-ink-faint">Noch keine offenen Aufgaben. Füge oben eine hinzu oder hol dir eine aus der Inbox.</p>
                </div>
            @endforelse
        </div>

        {{-- Pull tasks in from the inbox --}}
        @if ($this->inboxTasks->isNotEmpty())
            <div x-data="{ open: false }" class="mt-6 rounded-card border border-line bg-paper/60">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm font-medium text-ink-soft transition hover:text-ink"
                    :aria-expanded="open"
                >
                    <span>Aus der Inbox hinzufügen
                        <span class="tnum ml-1 rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->inboxTasks->count() }}</span>
                    </span>
                    <svg class="h-4 w-4 flex-none text-ink-faint transition" :class="open && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <div x-show="open" x-collapse style="display: none;" class="border-t border-line px-2 py-2">
                    <ul class="flex flex-col gap-1">
                        @foreach ($this->inboxTasks as $task)
                            <li wire:key="inbox-pick-{{ $task->id }}">
                                <button
                                    type="button"
                                    wire:click="assignToProject({{ $task->id }})"
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

    {{-- Shared edit sheet --}}
    @include('livewire.partials.edit-sheet')
</div>
