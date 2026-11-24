{{-- One Werkbank column's content. Expects $list, $lists, $counts, $suggestedId from the board. --}}
<h3 id="col-{{ $list }}" class="flex items-baseline justify-between px-1 font-display text-[13px] text-ink" style="font-weight: var(--t-dw)">
    {{ __('shell.lists.'.$list) }}
    <em class="font-mono text-xs font-normal not-italic text-ink-faint">{{ $counts[$list] }}</em>
</h3>
<ul class="flex flex-col gap-1.5">
    @forelse ($lists[$list]->take(\App\Livewire\Shell\Board::LIST_LIMIT) as $task)
        @include('shell.item', ['task' => $task, 'layout' => 'werkbank', 'mode' => 'board', 'suggested' => $task->id === $suggestedId, 'locked' => false])
    @empty
        <li class="rounded-card border border-dashed border-line p-3 text-[12.5px] text-ink-faint">{{ __('shell.empty.'.$list) }}</li>
    @endforelse
    @if ($counts[$list] > \App\Livewire\Shell\Board::LIST_LIMIT)
        <li class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $counts[$list] - \App\Livewire\Shell\Board::LIST_LIMIT]) }}</li>
    @endif
</ul>
