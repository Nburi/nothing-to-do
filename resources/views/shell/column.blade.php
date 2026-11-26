{{-- One Werkbank column's content. Expects $list, $lists, $bundles, $counts, $suggestedId from the board. --}}
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
<ul class="flex flex-col gap-1.5">
    {{-- The To-do session: the day's To-dos as one card, first in the To-dos column. --}}
    @if ($list === 'todos')
        @include('shell.todo-session', ['todoSession' => $todoSession, 'layout' => 'werkbank', 'mode' => 'board', 'suggestedId' => $suggestedId, 'locked' => false])
    @endif
    {{-- Step 4.3: projects and groups first, one card each with their next two steps. --}}
    @foreach ($bundles[$list] as $bundle)
        @include('shell.bundle', ['bundle' => $bundle, 'layout' => 'werkbank', 'mode' => 'board', 'suggestedId' => $suggestedId, 'locked' => false])
    @endforeach
    @foreach ($lists[$list]->take(\App\Livewire\Shell\Board::LIST_LIMIT) as $task)
        @include('shell.item', ['task' => $task, 'layout' => 'werkbank', 'mode' => 'board', 'suggested' => $task->id === $suggestedId, 'locked' => false])
    @endforeach
    @if ($counts[$list] === 0)
        <li class="rounded-card border border-dashed border-line p-3 text-[12.5px] text-ink-faint">{{ __('shell.empty.'.$list) }}</li>
    @endif
    @if ($lists[$list]->count() > \App\Livewire\Shell\Board::LIST_LIMIT)
        <li class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $lists[$list]->count() - \App\Livewire\Shell\Board::LIST_LIMIT]) }}</li>
    @endif
</ul>
