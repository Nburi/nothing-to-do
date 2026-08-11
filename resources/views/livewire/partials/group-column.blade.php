{{-- One column of a task group's dashboard. Variables: $list, $title, $active,
     $completed, $empty. Deliberately the board column's shape minus the Today
     area — the day's focus is owned by the main board alone. --}}
<section class="flex min-h-[55vh] flex-col">
    <header class="mb-3 flex items-center justify-between px-1">
        <h2 class="flex items-center gap-2 text-sm font-medium text-ink">
            {{ $title }}
            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $active->count() }}</span>
        </h2>
    </header>

    {{-- Sortable zone: active tasks only. --}}
    <div
        class="flex flex-col gap-2"
        data-list="{{ $list }}" data-today="false"
        x-data x-init="window.boardSortable($el, $wire)"
    >
        @foreach ($active as $task)
            @include('livewire.partials.task-card', ['task' => $task])
        @endforeach
    </div>

    @if ($active->isEmpty() && $completed->isEmpty())
        <div class="mt-2 flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-8 text-center">
            <p class="max-w-[24ch] text-xs leading-relaxed text-ink-faint">{{ $empty }}</p>
        </div>
    @endif

    {{-- Quick-add. A page entirely about one bundle of tasks shouldn't send you
         through the app-wide capture panel to add the next step. --}}
    <form wire:submit="addTask('{{ $list }}')" class="mt-2">
        <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-2.5 py-2 shadow-map transition focus-within:border-ink-faint/60">
            <input
                type="text"
                wire:model="newTitle.{{ $list }}"
                placeholder="Hinzufügen …"
                autocomplete="off"
                aria-label="Aufgabe zu {{ $title }} hinzufügen"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] text-ink placeholder:text-ink-faint focus:ring-0"
            />
            <button type="submit" class="grid h-6 w-6 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Hinzufügen">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
        @error('newTitle.'.$list) <p class="mt-1 px-1 text-xs text-signal">{{ $message }}</p> @enderror
    </form>

    {{-- Recently completed — outside the sortable zone, not draggable. --}}
    @if ($completed->isNotEmpty())
        <div class="mt-3 border-t border-line/50 pt-2">
            <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
            <div class="flex flex-col gap-2">
                @foreach ($completed as $task)
                    @include('livewire.partials.task-card', ['task' => $task])
                @endforeach
            </div>
        </div>
    @endif
</section>
