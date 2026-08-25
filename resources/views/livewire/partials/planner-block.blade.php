@php
    $capacity = $block->durationMinutes();
    $used = $block->linkedTasks->sum(fn ($t) => $t->duration_minutes ?? (\App\Services\WorkPlanner::DEFAULT_DURATION[$t->list] ?? 25));
    $colorClasses = [
        'contour' => 'bg-contour', 'overprint' => 'bg-overprint', 'forest' => 'bg-forest',
        'signal' => 'bg-signal', 'ink' => 'bg-ink-faint',
    ];
@endphp
<div wire:key="planner-block-{{ $block->id }}" class="rounded-card border border-line bg-surface p-3 shadow-map">
    <div class="mb-2 flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            <span class="h-2.5 w-2.5 flex-none rounded-full {{ $colorClasses[$block->colorToken()] ?? 'bg-ink-faint' }}" aria-hidden="true"></span>
            <span class="min-w-0 truncate text-sm font-medium text-ink">{{ $block->displayTitle() }}</span>
            <span class="tnum flex-none text-xs text-ink-faint">{{ $block->start_time }}–{{ $block->end_time }}</span>
        </div>
        <div class="flex flex-none items-center gap-2">
            <span class="tnum text-xs text-ink-faint">{{ $used }}/{{ $capacity }} min</span>
            <button
                type="button"
                wire:click="startEditEvent({{ $block->id }})"
                class="grid h-6 w-6 place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink"
                aria-label="Block verschieben: {{ $block->displayTitle() }}"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
            </button>
        </div>
    </div>

    <div
        data-planner-block="{{ $block->id }}"
        x-data
        x-init="window.plannerBlockSortable($el, $wire)"
        class="min-h-[2.75rem] space-y-1.5"
    >
        @forelse ($block->linkedTasks as $task)
            <div data-id="{{ $task->id }}" wire:key="planner-task-{{ $block->id }}-{{ $task->id }}" class="flex items-center justify-between gap-2 rounded-card bg-paper px-2 py-1.5">
                <div class="flex min-w-0 items-center gap-2">
                    <span data-drag-handle class="grid h-6 w-6 flex-none touch-none cursor-grab place-items-center text-ink-faint active:cursor-grabbing" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" />
                            <circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" />
                            <circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" />
                        </svg>
                    </span>
                    <span
                        class="h-1.5 w-1.5 flex-none rounded-full {{ $task->pivot->source === 'manual' ? 'bg-ink-soft' : 'border border-ink-faint' }}"
                        title="{{ $task->pivot->source === 'manual' ? 'Von dir platziert' : 'Automatisch platziert' }}"
                        aria-hidden="true"
                    ></span>
                    <span class="min-w-0 truncate text-sm text-ink">{{ $task->title }}</span>
                </div>
                <div class="flex flex-none items-center gap-1.5">
                    <span class="tnum text-xs text-ink-faint {{ $task->duration_minutes ? '' : 'italic' }}">
                        @if ($task->duration_minutes)
                            {{ $task->duration_minutes }} min
                        @else
                            ≈{{ \App\Services\WorkPlanner::DEFAULT_DURATION[$task->list] ?? 25 }} min
                        @endif
                    </span>
                    <button
                        type="button"
                        wire:click="unassignTask({{ $task->id }})"
                        class="grid h-5 w-5 place-items-center rounded text-ink-faint transition hover:bg-signal-soft hover:text-signal"
                        aria-label="{{ $task->title }} aus diesem Block entfernen"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                    </button>
                </div>
            </div>
        @empty
            <p class="px-2 py-1.5 text-xs text-ink-faint">Noch nichts geplant.</p>
        @endforelse
    </div>
</div>
