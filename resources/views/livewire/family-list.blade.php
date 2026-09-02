<div class="mx-auto max-w-3xl px-4 pb-16 pt-5 sm:px-6">
    <div class="mb-5 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-lg font-medium text-ink">
                {{ $this->currentSpace?->name ?? 'Familie' }}
            </h1>
            @if ($this->currentSpace)
                <p class="mt-0.5 text-[12.5px] text-ink-faint">Geteilte Aufgaben — antippen zum Übernehmen, nochmal zum Erledigen.</p>
            @endif
        </div>
        <button
            type="button"
            wire:click="openFamilySpaces"
            class="flex-none rounded-card border border-line px-3 py-1.5 text-[13px] text-ink-soft transition hover:bg-surface hover:text-ink"
        >
            Familien verwalten
        </button>
    </div>

    @if ($this->currentSpace === null)
        {{-- No family yet at all — the "day one" state, since unlike every other
             page in the app there is nothing private to fall back to here. --}}
        <div class="rounded-card border border-dashed border-line bg-surface p-8 text-center">
            <div class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-forest-soft text-forest">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="6.5" r="2.5"/><circle cx="14" cy="6.5" r="2"/><path d="M2.5 17v-1.5A3.5 3.5 0 0 1 6 12h2a3.5 3.5 0 0 1 3.5 3.5V17"/><path d="M12.5 12.3A3 3 0 0 1 15 15v2"/></svg>
            </div>
            <h2 class="text-[15px] font-medium text-ink">Noch keine Familie</h2>
            <p class="mx-auto mt-2 max-w-xs text-[13px] leading-relaxed text-ink-faint">
                Erstell eine Familie und teile den Einladungscode — oder tritt mit einem Code bei,
                den du bekommen hast.
            </p>
            <button
                type="button"
                wire:click="openFamilySpaces"
                class="mt-4 rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
            >
                Familie erstellen oder beitreten
            </button>
        </div>
    @else
        <form wire:submit="addTask" class="mb-5 flex gap-2">
            <input
                type="text"
                wire:model="newTaskTitle"
                placeholder="Was steht an? z. B. Müll rausbringen"
                maxlength="255"
                class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
            />
            <button type="submit" class="flex-none rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                +
            </button>
        </form>

        @php
            $members = $this->members;
            $memberColors = $members->mapWithKeys(fn ($m) => [$m->id => $m->pivot->color])->all();
        @endphp

        @if ($this->openTasks->isEmpty())
            <div class="rounded-card border border-dashed border-line p-8 text-center">
                <p class="text-[13px] leading-relaxed text-ink-faint">
                    Nichts offen. Trag oben etwas ein, oder lehn dich zurück — {{ $this->doneTasks->count() > 0 ? 'schon erledigt.' : 'noch nichts zu tun.' }}
                </p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($this->openTasks as $task)
                    @include('livewire.partials.family-task-card', ['task' => $task, 'memberColors' => $memberColors])
                @endforeach
            </div>
        @endif

        @if ($this->doneTasks->isNotEmpty())
            <div x-data="{ show: false }" class="mt-6">
                <button type="button" @click="show = !show" class="flex items-center gap-1.5 text-[12.5px] text-ink-faint transition hover:text-ink-soft">
                    <svg class="h-3 w-3 transition" :class="show ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    {{ $this->doneTasks->count() }} erledigt · anzeigen
                </button>
                <div x-show="show" x-transition class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4" style="display:none">
                    @foreach ($this->doneTasks as $task)
                        @include('livewire.partials.family-task-card', ['task' => $task, 'memberColors' => $memberColors])
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    @include('livewire.partials.family-spaces-sheet')
    @include('livewire.partials.family-task-edit-sheet')
</div>
