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
        aria-label="Projekt zuweisen"
    >
        <div class="mx-auto max-h-[70dvh] overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">Projekt zuweisen</h2>
                <button
                    type="button"
                    @click="$store.projectPicker.taskId = null"
                    class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                    aria-label="Schließen"
                >
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

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
