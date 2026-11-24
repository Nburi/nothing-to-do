{{-- One Werkbank column's content. Expects $list, $entries (step 4.8: cards and rows in the one board order),
     $resting, $lists, $counts, $suggestedId from the board. --}}
<h3 id="col-{{ $list }}" class="flex items-baseline gap-2 px-1 font-display text-[13px] text-ink" style="font-weight: var(--t-dw)">
    {{ __('shell.lists.'.$list) }}
    <em class="font-mono text-xs font-normal not-italic text-ink-faint">{{ $counts[$list] }}</em>
    @if ($list === 'inbox' && $counts['inbox'] > 0)
        <x-shell.action key="sort" layout="werkbank" mode="board" wire:click="$dispatch('open-sort')"
            class="ml-auto font-sans text-xs font-bold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            {{ __('shell.sort_inbox', ['count' => $counts['inbox']]) }}
        </x-shell.action>
    @endif
    @if ($list === 'later' && $counts['later'] > 0)
        {{-- Step 3.4: the same list as cards, with "Starte mit" on each. --}}
        <a href="{{ route('shell.ideas') }}" wire:navigate class="ml-auto font-sans text-xs font-bold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.ideas.as_cards') }}</a>
    @endif
</h3>
<ul @if (in_array($list, \App\Models\Task::WORK_LISTS, true)) data-order-list x-data="boardOrder(@js(['text' => __('shell.order')]))" @endif class="flex flex-col gap-1.5">
    {{-- The To-do session: the day's To-dos as one card, first in the To-dos column. --}}
    @if ($list === 'todos')
        @include('shell.todo-session', ['todoSession' => $todoSession, 'layout' => 'werkbank', 'mode' => 'board', 'suggestedId' => $suggestedId, 'locked' => false])
    @endif
    {{-- Step 4.3 / 4.8: projects, groups and loose tasks in the one board order, a project or group as one card with its next two steps. --}}
    @foreach ($entries[$list] as $entry)
        @if ($entry['bundle'])
            @include('shell.bundle', ['bundle' => $entry['bundle'], 'layout' => 'werkbank', 'mode' => 'board', 'suggestedId' => $suggestedId, 'locked' => false])
        @else
            @include('shell.item', ['task' => $entry['task'], 'layout' => 'werkbank', 'mode' => 'board', 'suggested' => $entry['task']->id === $suggestedId, 'locked' => false, 'sortable' => in_array($list, \App\Models\Task::WORK_LISTS, true)])
        @endif
    @endforeach
    @if ($counts[$list] === 0 && $resting[$list]->isEmpty())
        <li class="rounded-card border border-dashed border-line p-3 text-[12.5px] text-ink-faint">{{ __('shell.empty.'.$list) }}</li>
    @endif
    @if ($lists[$list]->count() > \App\Livewire\Shell\Board::LIST_LIMIT)
        <li class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $lists[$list]->count() - \App\Livewire\Shell\Board::LIST_LIMIT]) }}</li>
    @endif
</ul>
@if ($resting[$list]->isNotEmpty())
    @include('shell.resting', ['list' => $list, 'cards' => $resting[$list]])
@endif
