{{-- A board column. Variables: $list, $title, $count, $rest (active non-today tasks),
     $completed (recently completed tasks, optional), and optionally
     $hasToday + $today (active today tasks) for the To-Dos / Tasks columns.
     $emergencyProject + $emergencyTasks (optional): while emergency mode is
     active, that project's tasks for this column are pinned above (numbered,
     read-only — reorder happens on the arrange screen), independently-important
     tasks stay visible right below them, and the rest of the column collapses
     behind a disclosure so nothing is lost, just tucked out of the way. Today
     is deliberately left untouched — it's already an explicit, deliberate flag. --}}
@php
    $emergencyProject = $emergencyProject ?? null;
    $emergencyTasks = $emergencyTasks ?? collect();
    $importantRest = $emergencyProject ? $rest->where('is_important', true)->values() : collect();
    $normalRest = $emergencyProject ? $rest->where('is_important', false)->values() : $rest;
    $columnTrulyEmpty = $emergencyTasks->isEmpty() && $importantRest->isEmpty() && $normalRest->isEmpty() && (($completed ?? collect())->isEmpty());
@endphp
<section class="flex min-h-[55vh] flex-col">
    <header class="mb-3 flex items-center justify-between px-1">
        <h2 class="flex items-center gap-2 text-sm font-medium text-ink">
            {{ $title }}
            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $count }}</span>
        </h2>
    </header>

    @if (($hasToday ?? false))
        <div class="mb-3 rounded-card border border-forest/30 bg-forest-soft/60 p-2">
            <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">
                <x-logo class="h-3 w-3" />
                Heute
            </p>
            <div
                class="flex min-h-[40px] flex-col gap-2"
                data-list="{{ $list }}" data-today="true"
                x-data x-init="window.boardSortable($el, $wire)"
            >
                @foreach ($today as $task)
                    @include('livewire.partials.task-card', ['task' => $task])
                @endforeach
                @if ($today->isEmpty())
                    <p class="px-1 py-1.5 text-xs text-ink-faint">Hierher ziehen für den Tagesfokus.</p>
                @endif
            </div>
        </div>
    @endif

    @if ($emergencyProject)
        @if ($emergencyTasks->isNotEmpty())
            <div class="mb-2.5 rounded-card border border-signal/25 bg-signal-soft/40 p-2">
                <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-signal">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>
                    {{ $emergencyProject->name }}
                </p>
                <div class="flex flex-col gap-2">
                    @foreach ($emergencyTasks as $i => $task)
                        @include('livewire.partials.task-card', ['task' => $task, 'orderNumber' => $i + 1])
                    @endforeach
                </div>
            </div>
        @endif

        @if ($importantRest->isNotEmpty())
            <div class="mb-2.5">
                <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-overprint">Wichtig</p>
                <div class="flex flex-col gap-2">
                    @foreach ($importantRest as $task)
                        @include('livewire.partials.task-card', ['task' => $task])
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <div x-data="{ showAll: {{ $emergencyProject ? 'false' : 'true' }} }" class="flex flex-1 flex-col">
        @if ($emergencyProject && $normalRest->isNotEmpty())
            <button
                type="button"
                x-show="!showAll"
                @click="showAll = true"
                class="mb-2 px-1 py-1 text-left text-xs text-ink-faint transition hover:text-ink-soft"
            >{{ $normalRest->count() }} {{ $normalRest->count() === 1 ? 'weitere Aufgabe' : 'weitere Aufgaben' }} ausgeblendet · Alle anzeigen</button>
        @endif

        {{-- Sortable zone: active non-today tasks only (no completed tasks inside here). --}}
        <div
            x-show="showAll"
            class="flex flex-1 flex-col gap-2"
            data-list="{{ $list }}" data-today="false"
            x-init="window.boardSortable($el, $wire)"
            style="{{ $emergencyProject ? 'display:none' : '' }}"
        >
            @foreach ($normalRest as $task)
                @include('livewire.partials.task-card', ['task' => $task])
            @endforeach

            @if ($columnTrulyEmpty)
                <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-10 text-center">
                    <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <path d="M24 8c8 0 14 5 14 11s-7 10-14 10S11 25 11 19 16 8 24 8Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M24 13.5c5 0 8.5 2.6 8.5 6S29 25 24 25s-8-2-8-5.5 3-6 8-6Z" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="24" cy="19.5" r="1.8" fill="currentColor"/>
                    </svg>
                    <p class="max-w-[22ch] text-xs leading-relaxed text-ink-faint">{{ $empty }}</p>
                </div>
            @elseif ($normalRest->isEmpty())
                <p class="px-1 py-2 text-xs text-ink-faint">Hierher ziehen, um Aufgaben hinzuzufügen.</p>
            @endif
        </div>
    </div>

    {{-- Recently completed tasks — outside the sortable zone, not draggable. --}}
    @if (($completed ?? collect())->isNotEmpty())
        <div class="mt-2 border-t border-line/50 pt-2">
            <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
            <div class="flex flex-col gap-2">
                @foreach ($completed as $task)
                    @include('livewire.partials.task-card', ['task' => $task])
                @endforeach
            </div>
        </div>
    @endif
</section>
