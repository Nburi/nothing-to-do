{{-- Category task-link sheet — opened from a Pomodoro-enabled category's row in
     Settings (manageCategoryLink). Picks what TaskSuggestor::suggest() prefers
     during that category's focus sessions: specific tasks, a whole project/group,
     an Agenda entry (or "HAs erledigen" generically), or free text. Mirrors
     edit-sheet.blade.php's modal shape (animate-rise, no leave-transition). --}}
@php
    $linking = $this->linkingCategory;
    $topGroup = $linking === null ? null : match ($linking->task_source) {
        'agenda_entry', 'agenda_generic' => 'agenda',
        default => $linking->task_source,
    };
    $pinnedIds = $linking?->pinnedTasks->pluck('id')->all() ?? [];
    $taskCandidates = $linking === null ? collect() : $this->linkTaskCandidates->reject(fn ($t) => in_array($t->id, $pinnedIds, true));

    $chipClasses = fn (bool $active) => $active
        ? 'bg-forest text-white'
        : 'bg-paper text-ink-soft hover:bg-line/60';

    $rowClasses = fn (bool $active) => $active
        ? 'border-forest/40 bg-forest-soft text-ink'
        : 'border-line text-ink hover:bg-paper';
@endphp
@if ($linking)
    <div x-data="{ reveal: @js($topGroup) }" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Aufgaben-Verknüpfung">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeCategoryLink"></div>
        <div class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.closeCategoryLink()">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">Verknüpft mit „{{ $linking->name }}“</h2>
                <button type="button" wire:click="closeCategoryLink" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm leading-relaxed text-ink-soft">
                Während einer Fokus-Session mit dieser Kategorie schlägt die App bevorzugt hieraus etwas vor.
            </p>

            {{-- Source-type chips --}}
            <div class="mb-4 flex flex-wrap gap-1.5">
                <button type="button" wire:click="clearCategoryLink({{ $linking->id }})" @click="reveal = null" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === null) }}">Keine</button>
                <button type="button" wire:click="setCategoryTasksMode({{ $linking->id }})" @click="reveal = 'tasks'" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === 'tasks') }}">Bestimmte Aufgaben</button>
                <button type="button" @click="reveal = 'project'" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === 'project') }}">Projekt</button>
                <button type="button" @click="reveal = 'group'" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === 'group') }}">Gruppe</button>
                <button type="button" @click="reveal = 'agenda'" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === 'agenda') }}">Agenda</button>
                <button type="button" @click="reveal = 'text'" class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $chipClasses($topGroup === 'text') }}">Text</button>
            </div>

            {{-- Bestimmte Aufgaben --}}
            <div x-show="reveal === 'tasks'" style="display: none;">
                <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Ausgewählt</p>
                <div class="mb-3 space-y-1">
                    @forelse ($linking->pinnedTasks as $pinned)
                        <button type="button" wire:key="pinned-{{ $pinned->id }}" wire:click="togglePinnedTask({{ $linking->id }}, {{ $pinned->id }})" class="flex w-full items-center gap-2 rounded-card border {{ $rowClasses(true) }} px-3 py-2 text-left text-sm transition">
                            <svg class="h-3.5 w-3.5 flex-none text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            <span class="min-w-0 flex-1 truncate">{{ $pinned->title }}</span>
                            <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    @empty
                        <p class="rounded-card border border-dashed border-line px-3 py-2 text-sm text-ink-faint">Noch keine Aufgabe ausgewählt.</p>
                    @endforelse
                </div>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="linkTaskSearch"
                    placeholder="Aufgabe suchen…"
                    autocomplete="off"
                    class="mb-2 w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                />
                <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
                    {{ $this->linkTaskSearch === '' ? 'Vorschläge — fällig in 2 Tagen oder Wunschtermin heute' : 'Suchergebnisse' }}
                </p>
                <div class="space-y-1">
                    @forelse ($taskCandidates as $task)
                        <button type="button" wire:key="candidate-{{ $task->id }}" wire:click="togglePinnedTask({{ $linking->id }}, {{ $task->id }})" class="flex w-full items-center gap-2 rounded-card border {{ $rowClasses(false) }} px-3 py-2 text-left text-sm transition">
                            <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <span class="min-w-0 flex-1 truncate">{{ $task->title }}</span>
                            @if ($task->effectiveDateLabel())
                                <span class="flex-none text-xs text-ink-faint">{{ $task->effectiveDateLabel() }}</span>
                            @endif
                        </button>
                    @empty
                        <p class="rounded-card border border-dashed border-line px-3 py-2 text-sm text-ink-faint">
                            {{ $this->linkTaskSearch === '' ? 'Nichts Passendes fällig — probier die Suche.' : 'Keine Treffer.' }}
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Projekt --}}
            <div x-show="reveal === 'project'" style="display: none;">
                <div class="space-y-1">
                    @forelse ($this->linkableProjects as $project)
                        <button type="button" wire:key="proj-{{ $project->id }}" wire:click="linkCategoryToProject({{ $linking->id }}, {{ $project->id }})" class="flex w-full items-center justify-between gap-2 rounded-card border {{ $rowClasses($linking->task_source === 'project' && $linking->linked_project_id === $project->id) }} px-3 py-2 text-left text-sm transition">
                            <span class="min-w-0 flex-1 truncate">{{ $project->name }}</span>
                            @if ($linking->task_source === 'project' && $linking->linked_project_id === $project->id)
                                <svg class="h-3.5 w-3.5 flex-none text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </button>
                    @empty
                        <p class="rounded-card border border-dashed border-line px-3 py-2 text-sm text-ink-faint">Noch keine Projekte angelegt.</p>
                    @endforelse
                </div>
            </div>

            {{-- Gruppe --}}
            <div x-show="reveal === 'group'" style="display: none;">
                <div class="space-y-1">
                    @forelse ($this->linkableGroups as $group)
                        <button type="button" wire:key="grp-{{ $group->id }}" wire:click="linkCategoryToGroup({{ $linking->id }}, {{ $group->id }})" class="flex w-full items-center justify-between gap-2 rounded-card border {{ $rowClasses($linking->task_source === 'group' && $linking->linked_group_id === $group->id) }} px-3 py-2 text-left text-sm transition">
                            <span class="min-w-0 flex-1 truncate">{{ $group->name }}</span>
                            @if ($linking->task_source === 'group' && $linking->linked_group_id === $group->id)
                                <svg class="h-3.5 w-3.5 flex-none text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </button>
                    @empty
                        <p class="rounded-card border border-dashed border-line px-3 py-2 text-sm text-ink-faint">Noch keine Gruppen angelegt.</p>
                    @endforelse
                </div>
            </div>

            {{-- Agenda: generic nudge or one specific entry --}}
            <div x-show="reveal === 'agenda'" style="display: none;">
                <button type="button" wire:click="linkCategoryToAgendaGeneric({{ $linking->id }})" class="mb-3 flex w-full items-center justify-between gap-2 rounded-card border {{ $rowClasses($linking->task_source === 'agenda_generic') }} px-3 py-2 text-left text-sm font-medium transition">
                    <span>Alle offenen Hausaufgaben («HAs erledigen»)</span>
                    @if ($linking->task_source === 'agenda_generic')
                        <svg class="h-3.5 w-3.5 flex-none text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    @endif
                </button>

                <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Oder ein bestimmter Eintrag</p>
                <div class="space-y-1">
                    @forelse ($this->linkableAgendaEntries as $entry)
                        <button type="button" wire:key="agenda-{{ $entry->id }}" wire:click="linkCategoryToAgendaEntry({{ $linking->id }}, {{ $entry->id }})" class="flex w-full items-center justify-between gap-2 rounded-card border {{ $rowClasses($linking->task_source === 'agenda_entry' && $linking->linked_agenda_entry_id === $entry->id) }} px-3 py-2 text-left text-sm transition">
                            <span class="min-w-0 flex-1 truncate">{{ $entry->typeLabel() }} · {{ $entry->subject }} · {{ $entry->title }}</span>
                            <span class="flex-none text-xs text-ink-faint">{{ $entry->dateLabel() }}</span>
                        </button>
                    @empty
                        <p class="rounded-card border border-dashed border-line px-3 py-2 text-sm text-ink-faint">Keine offenen Hausaufgaben oder Prüfungen.</p>
                    @endforelse
                </div>
            </div>

            {{-- Text --}}
            <div x-show="reveal === 'text'" style="display: none;">
                <form wire:submit="saveCategoryLinkText({{ $linking->id }})" class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model="linkTextDraft"
                        placeholder="z. B. „Zimmer aufräumen“"
                        autocomplete="off"
                        class="min-w-0 flex-1 rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                    />
                    <button type="submit" class="flex-none rounded-card bg-forest px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Speichern</button>
                </form>
                @error('linkTextDraft') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
@endif
