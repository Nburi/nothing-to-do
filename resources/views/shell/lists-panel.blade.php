{{-- The four lists behind tabs: the Stapel sheet (piles, bottom tabs, drawer) and the Werkbank
     peek panel during a session. Opened by the parent's Alpine `peek` (inbox|todos|tasks|later).
     @param $layout, $mode, $lists, $counts, $suggestedId, $locked --}}
<div class="flex min-h-0 flex-1 flex-col">
    <div class="flex gap-1 overflow-x-auto border-b border-line px-3 pb-2" role="tablist">
        @foreach (\App\Models\Task::LISTS as $list)
            @php
                $tabClass = 'shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
            @endphp
            @if ($list === 'later')
                <x-shell.action key="browse_later" :layout="$layout" :mode="$mode" role="tab"
                    @click="peek = 'later'" x-bind:aria-selected="peek === 'later'"
                    x-bind:class="peek === 'later' ? 'bg-ink text-paper' : 'text-ink-soft hover:text-ink'"
                    class="{{ $tabClass }}">{{ __('shell.lists.later') }} <span class="font-mono text-xs opacity-70">{{ $counts['later'] }}</span></x-shell.action>
            @else
                <button type="button" role="tab" @click="peek = '{{ $list }}'" x-bind:aria-selected="peek === '{{ $list }}'"
                    x-bind:class="peek === '{{ $list }}' ? 'bg-ink text-paper' : 'text-ink-soft hover:text-ink'"
                    class="{{ $tabClass }}">{{ __('shell.lists.'.$list) }} <span class="font-mono text-xs opacity-70">{{ $counts[$list] }}</span></button>
            @endif
        @endforeach
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3">
        @foreach (\App\Models\Task::LISTS as $list)
            <ul x-show="peek === '{{ $list }}'" x-cloak class="flex flex-col gap-2" role="tabpanel">
                @forelse ($lists[$list]->take(\App\Livewire\Shell\Board::LIST_LIMIT) as $task)
                    @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'suggested' => $task->id === $suggestedId, 'locked' => $locked])
                @empty
                    <li class="rounded-card border border-dashed border-line p-4 text-sm text-ink-faint">{{ __('shell.empty.'.$list) }}</li>
                @endforelse
                @if ($counts[$list] > \App\Livewire\Shell\Board::LIST_LIMIT)
                    <li class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $counts[$list] - \App\Livewire\Shell\Board::LIST_LIMIT]) }}</li>
                @endif
            </ul>
        @endforeach
    </div>
</div>
