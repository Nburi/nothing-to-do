<div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-6 md:pb-12">
    @if ($this->project === null)
        {{-- ════════════════ STEP 1 · PROJEKT WÄHLEN ════════════════ --}}
        <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Zurück zum Dashboard
        </a>

        <div class="mt-4 flex items-center gap-3">
            <div class="grid h-10 w-10 flex-none place-items-center rounded-full bg-signal-soft text-signal">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="10" cy="14" r="1" fill="currentColor"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-medium tracking-tight text-ink">Notfallmodus</h1>
                <p class="text-[13px] text-ink-faint">Für welches Projekt brauchst du volle Konzentration?</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2.5">
            @forelse ($this->projects as $project)
                <button
                    type="button"
                    wire:click="selectProject({{ $project->id }})"
                    class="flex items-center justify-between gap-3 rounded-card border border-line bg-surface p-3.5 text-left shadow-map transition hover:border-ink-faint/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $project->name }}</p>
                        <p class="mt-0.5 text-xs text-ink-faint">{{ $project->active_count }} {{ $project->active_count === 1 ? 'Aufgabe' : 'Aufgaben' }} offen</p>
                    </div>
                    <div class="flex flex-none items-center gap-2">
                        @if ($project->deadline)
                            <span
                                class="tnum rounded-full px-2 py-0.5 text-[10px] font-medium leading-tight"
                                @class([
                                    'bg-signal-soft text-signal' => $project->isOverdue(),
                                    'bg-contour-soft text-contour' => !$project->isOverdue() && $project->isUrgent(),
                                    'bg-paper text-ink-faint border border-line' => !$project->isOverdue() && !$project->isUrgent(),
                                ])
                            >{{ $project->deadlineLabel() }}</span>
                        @endif
                        <svg class="h-4 w-4 text-ink-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </button>
            @empty
                <x-board-empty>Noch keine Projekte angelegt. Leg zuerst eins an, dann kannst du es hierher holen.</x-board-empty>
            @endforelse
        </div>
    @else
        {{-- ════════════════ STEP 2 · REIHENFOLGE FESTLEGEN ════════════════ --}}
        @if ($this->isActiveEmergency)
            <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Zurück zum Dashboard
            </a>
        @else
            <button type="button" wire:click="backToPicker" class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Anderes Projekt
            </button>
        @endif

        <div class="mt-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-medium tracking-tight text-ink">{{ $this->project->name }}</h1>
                    @if ($this->project->deadline)
                        <span
                            class="tnum flex-none rounded-full px-2 py-0.5 text-[11px] font-medium leading-tight"
                            @class([
                                'bg-signal-soft text-signal' => $this->project->isOverdue(),
                                'bg-contour-soft text-contour' => !$this->project->isOverdue() && $this->project->isUrgent(),
                                'bg-paper text-ink-faint border border-line' => !$this->project->isOverdue() && !$this->project->isUrgent(),
                            ])
                        >{{ $this->project->deadlineLabel() }}</span>
                    @endif
                </div>
                <p class="mt-1 text-[13px] text-ink-faint">Bring die Schritte in die Reihenfolge, in der du sie angehst.</p>
            </div>
            @if ($this->isActiveEmergency)
                <span class="flex-none rounded-full bg-signal-soft px-2.5 py-1 text-[11px] font-medium text-signal">Aktiv</span>
            @endif
        </div>

        {{-- Quick-add --}}
        <form wire:submit="addTask" class="mt-5">
            <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2.5 shadow-map focus-within:border-ink-faint/60">
                <input
                    type="text"
                    wire:model="newTitle"
                    placeholder="Nächsten Schritt ergänzen …"
                    autocomplete="off"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                />
                <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Hinzufügen">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
            @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
        </form>

        {{-- Ordered sequence --}}
        <div class="mt-4 flex flex-col gap-2" x-data x-init="window.emergencySortable($el, $wire)">
            @forelse ($this->orderedTasks as $index => $task)
                @include('livewire.partials.emergency-task-row', ['task' => $task, 'order' => $index + 1])
            @empty
                <x-board-empty>Noch keine Aufgaben. Leg oben die erste an.</x-board-empty>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="mt-6 flex items-center gap-3 border-t border-line pt-4 {{ $this->isActiveEmergency ? 'justify-between' : 'justify-end' }}">
            @if ($this->isActiveEmergency)
                <button type="button" wire:click="end" class="rounded-card px-3 py-2 text-sm text-signal transition hover:bg-signal-soft">
                    Notfallmodus beenden
                </button>
                <a href="{{ route('app') }}" wire:navigate class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    Fertig — zum Dashboard
                </a>
            @else
                <button type="button" wire:click="start" class="rounded-card bg-signal px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                    Notfallmodus starten
                </button>
            @endif
        </div>
    @endif

    @include('livewire.partials.edit-sheet')
</div>
