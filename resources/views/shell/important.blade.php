{{-- Step 4.6, R5 / step 4.8: the „Wichtig" band at the very top of the board. Marked To-dos and Tasks stand
     here, in the shared board order, and leave their column; Start on a row works as ever. The order of the
     band can be changed like any other (drag the grip, press and hold, or Space and the arrows).
     The engine order is unchanged: deadline, „Wichtig", board order.
     @param string $layout, Collection<Task> $tasks (the marked To-dos and Tasks) --}}
@php
    $shown = $tasks->take(\App\Livewire\Shell\Board::LIST_LIMIT);
    $rest = $tasks->count() - $shown->count();
    $headingId = 'important-'.$layout;
@endphp
<section data-important-reminder class="fold-away flex min-w-0 flex-col gap-2 rounded-panel p-3" style="background: color-mix(in srgb, var(--t-warn) 6%, var(--t-surface)); border: var(--t-bw) solid color-mix(in srgb, var(--t-warn) 45%, var(--t-line)); border-left-width: 4px" aria-labelledby="{{ $headingId }}">
    <h3 id="{{ $headingId }}" class="flex items-center gap-2 font-display text-[13px] text-ink" style="font-weight: var(--t-dw)">
        @include('shell.icon', ['name' => 'flag', 'class' => 'size-4 text-warn', 'filled' => true])
        {{ __('shell.important.label') }}
        <em class="font-mono text-xs font-normal not-italic text-ink-faint">{{ $tasks->count() }}</em>
    </h3>
    <ul data-order-list x-data="boardOrder(@js(['text' => __('shell.order')]))" class="flex flex-col gap-1.5">
        @foreach ($shown as $task)
            @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => 'board', 'suggested' => $task->id === ($suggestedId ?? null), 'locked' => false, 'keyScope' => 'band', 'tagged' => false, 'sortable' => true])
        @endforeach
    </ul>
    @if ($rest > 0)
        <p class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $rest]) }}</p>
    @endif
</section>
