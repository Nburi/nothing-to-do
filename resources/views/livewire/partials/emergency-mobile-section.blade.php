{{-- Shared by the mobile Inbox/To-Dos/Tasks tabs: the pinned, numbered emergency-project
     tasks for this list, plus any independently-important tasks right below them. --}}
@if ($emergencyProject && $emergencyTasks->isNotEmpty())
    <div class="mb-1 rounded-card border border-signal/25 bg-signal-soft/40 p-2">
        <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-signal">
            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>
            {{ $emergencyProject->name }}
        </p>
        <div class="flex flex-col gap-2.5">
            @foreach ($emergencyTasks as $i => $task)
                @include('livewire.partials.task-card-mobile', ['task' => $task, 'orderNumber' => $i + 1])
            @endforeach
        </div>
    </div>
@endif
@if (($importantRest ?? collect())->isNotEmpty())
    <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-overprint">Wichtig</p>
    <div class="flex flex-col gap-2.5">
        @foreach ($importantRest as $task)
            @include('livewire.partials.task-card-mobile', ['task' => $task])
        @endforeach
    </div>
@endif
