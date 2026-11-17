{{--
    The "Teil 2/3" marker of a task that was split across days (Planer,
    "Aufteilen"). Rendered on the card face of the main board, the project and
    group pages — nothing at all for an ordinary task or while the Planer is
    off. One line: the pill (dots + next day) opens the split sheet to change
    the plan; beside it the one action that matters right now —
    "Teil erledigt" once a part is due, or "Aufgabe abhaken?" when every part
    is ticked (finishing the task is never automatic: that would fire streak
    and celebration for something you may not consider done).

    Expects `$task` with its `sessions` relation eager-loaded; the component
    behind it needs ManagesTasks (completeTaskPart / toggleComplete).
--}}
@php
    $sessionProgress = auth()->user()->planner_enabled && ! $task->is_completed ? $task->sessionProgress() : null;
@endphp
@if ($sessionProgress)
    @php
        $today = auth()->user()->localToday();
        $next = $sessionProgress['next'];
        $nextDate = $next?->planned_date;
        $isDue = $nextDate !== null && $nextDate->lessThanOrEqualTo($today);
        $isLate = $nextDate !== null && $nextDate->lessThan($today);
        $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $nextLabel = $nextDate === null ? null : match (true) {
            $nextDate->isSameDay($today) => 'heute',
            $nextDate->isSameDay($today->copy()->addDay()) => 'morgen',
            $isLate => 'war für '.$wd[$nextDate->dayOfWeek].' '.$nextDate->isoFormat('D.M.'),
            default => $wd[$nextDate->dayOfWeek].' '.$nextDate->isoFormat('D.M.'),
        };
    @endphp
    <div class="mt-1 flex flex-wrap items-center gap-1.5">
        <button
            type="button"
            @click.stop="Livewire.dispatch('open-split-sheet', { taskId: {{ $task->id }} })"
            class="tnum inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-[11px] font-medium transition hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest {{ $isLate ? 'bg-signal-soft text-signal' : 'bg-forest-soft text-forest' }}"
            aria-label="{{ $task->title }}: in {{ $sessionProgress['total'] }} Teile aufgeteilt, {{ $sessionProgress['done'] }} erledigt. Antippen zum Ändern"
        >
            @if ($sessionProgress['total'] <= 8)
                <span class="flex items-center gap-0.5" aria-hidden="true">
                    @foreach ($task->sessions as $part)
                        <span class="h-1.5 w-1.5 rounded-full {{ $part->isDone() ? ($isLate ? 'bg-signal' : 'bg-forest') : 'border '.($isLate ? 'border-signal' : 'border-forest') }}"></span>
                    @endforeach
                </span>
            @endif
            @if ($sessionProgress['allDone'])
                Alle {{ $sessionProgress['total'] }} Teile erledigt
            @else
                Teil {{ $sessionProgress['position'] }}/{{ $sessionProgress['total'] }}@if ($nextLabel) · {{ $nextLabel }}@endif
            @endif
        </button>

        @if ($sessionProgress['allDone'])
            <button
                type="button"
                wire:click.stop="toggleComplete({{ $task->id }})"
                class="rounded px-1.5 py-0.5 text-[11px] font-medium text-forest underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-forest"
            >Aufgabe abhaken?</button>
        @elseif ($isDue)
            <button
                type="button"
                wire:click.stop="completeTaskPart({{ $next->id }})"
                wire:loading.attr="disabled"
                wire:target="completeTaskPart({{ $next->id }})"
                class="inline-flex items-center gap-1 rounded border border-forest/40 px-1.5 py-0.5 text-[11px] font-medium text-forest transition hover:bg-forest-soft active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest disabled:opacity-50"
                aria-label="Teil {{ $sessionProgress['position'] }} von {{ $task->title }} erledigt"
            >
                <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Teil erledigt
            </button>
        @endif
    </div>
@endif
