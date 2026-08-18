{{-- Mobile board list body — mirrors partials/column.blade.php's zones (an optional
     "Heute" zone + the main rest zone, both drag-sortable via the card's grip handle)
     but without a column header, reused by the Inbox/To-Dos/Tasks tabs.
     Variables: $list, $normalRest, $done, $emptyMessage, and optionally $today
     (only todos/tasks have one), $emergencyProject, $emergencyTasks, $importantRest. --}}
@php
    $today = $today ?? collect();
    $emergencyProject = $emergencyProject ?? null;
    $emergencyTasks = $emergencyTasks ?? collect();
    $importantRest = $importantRest ?? collect();
    $done = $done ?? collect();
    $groupBoxes = $groupBoxes ?? collect();
    $allEmpty = $today->isEmpty() && $emergencyTasks->isEmpty() && $importantRest->isEmpty()
        && $normalRest->isEmpty() && $done->isEmpty() && $groupBoxes->isEmpty();
@endphp

@if ($today->isNotEmpty())
    <p class="px-1 pt-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">Heute</p>
    <div
        class="flex flex-col gap-2.5"
        data-list="{{ $list }}" data-today="true"
        x-data x-init="window.boardSortable($el, $wire, '[data-drag-handle]')"
    >
        @foreach ($today as $task)
            @include('livewire.partials.task-card-mobile', ['task' => $task])
        @endforeach
    </div>
    <div class="my-1 border-t border-line"></div>
@endif

@include('livewire.partials.emergency-mobile-section', compact('emergencyProject', 'emergencyTasks', 'importantRest'))

{{-- Task groups that have open work in this list. --}}
@foreach ($groupBoxes as $box)
    @include('livewire.partials.task-group-box', ['box' => $box, 'mobile' => true])
@endforeach

<div x-data="{ showAll: {{ $emergencyProject ? 'false' : 'true' }} }">
    @if ($emergencyProject && $normalRest->isNotEmpty())
        <button type="button" x-show="!showAll" @click="showAll = true" class="mb-1.5 px-1 py-1 text-left text-xs text-ink-faint">{{ $normalRest->count() }} {{ $normalRest->count() === 1 ? 'weitere Aufgabe' : 'weitere Aufgaben' }} ausgeblendet · Alle anzeigen</button>
    @endif
    <div
        x-show="showAll"
        class="flex flex-col gap-2.5"
        data-list="{{ $list }}" data-today="false"
        x-init="window.boardSortable($el, $wire, '[data-drag-handle]')"
        style="{{ $emergencyProject ? 'display:none' : '' }}"
    >
        @forelse ($normalRest as $task)
            @include('livewire.partials.task-card-mobile', ['task' => $task])
        @empty
            @if ($allEmpty)
                <x-board-empty>{{ $emptyMessage }}</x-board-empty>
            @endif
        @endforelse
    </div>
</div>

@if ($done->isNotEmpty())
    <div class="mt-0.5 border-t border-line/50 pt-1">
        <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
    </div>
    @foreach ($done as $task)
        @include('livewire.partials.task-card-mobile', ['task' => $task])
    @endforeach
@endif
