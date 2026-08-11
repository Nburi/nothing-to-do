{{-- Quick project-assign sheet — opened by a long-press on a mobile task card
     (see the swipeCard Alpine component in app.js). Desktop already has a
     1-gesture equivalent via drag-and-drop onto a project card. --}}
<div x-data x-cloak>
    <div
        x-show="$store.projectPicker.taskId !== null"
        x-transition.opacity.duration.150ms
        @click="$store.projectPicker.taskId = null"
        class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"
        style="display: none;"
    ></div>

    <div
        x-show="$store.projectPicker.taskId !== null"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="opacity-0 translate-y-6"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @keydown.escape.window="$store.projectPicker.taskId = null"
        class="fixed inset-x-0 bottom-0 z-50"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-label="Aufgabe einordnen"
    >
        <div class="mx-auto max-h-[70dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">Aufgabe einordnen</h2>
                <button
                    type="button"
                    @click="$store.projectPicker.taskId = null"
                    class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                    aria-label="Schließen"
                >
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Groups first: bundling is the lighter, more frequent move, and it
                 keeps the task on the board. A task dropped into a group keeps
                 its list, exactly like the desktop drag onto a group box. --}}
            @if ($this->taskGroups->isNotEmpty())
                <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Gruppe</p>
                <div class="mb-4 space-y-1">
                    @foreach ($this->taskGroups as $group)
                        <button
                            type="button"
                            wire:key="picker-group-{{ $group->id }}"
                            @click="$wire.assignTaskToGroup($store.projectPicker.taskId, {{ $group->id }}); $store.projectPicker.taskId = null"
                            class="flex w-full items-center gap-2 rounded-card px-3 py-2.5 text-left text-sm text-ink transition hover:bg-paper"
                        >
                            <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <rect x="2" y="4.5" width="12" height="9" rx="1.6" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M4 2.5h8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                            </svg>
                            <span class="min-w-0 flex-1 truncate">{{ $group->name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Projekt</p>

            <button
                type="button"
                @click="$wire.createProjectFromTask($store.projectPicker.taskId); $store.projectPicker.taskId = null"
                class="mb-3 flex w-full items-center gap-2 rounded-card border border-dashed border-line px-3 py-2.5 text-left text-sm font-medium text-signal transition hover:bg-signal-soft"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span class="min-w-0 flex-1 truncate">Neues Projekt mit diesem Namen</span>
            </button>

            @if ($this->projects->isEmpty())
                <p class="rounded-card border border-line bg-paper/60 p-3 text-sm leading-relaxed text-ink-soft">
                    Noch keine Projekte angelegt.
                </p>
            @else
                <div class="space-y-1">
                    @foreach ($this->projects as $project)
                        <button
                            type="button"
                            @click="$wire.assignTaskToProject($store.projectPicker.taskId, {{ $project->id }}); $store.projectPicker.taskId = null"
                            class="flex w-full items-center gap-2 rounded-card px-3 py-2.5 text-left text-sm text-ink transition hover:bg-paper"
                        >
                            <span class="min-w-0 flex-1 truncate">{{ $project->name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
