{{-- The four lists behind tabs, plus „Wichtig" (step 4.6): the Stapel sheet (piles, bottom tabs, drawer)
     and the Werkbank peek panel during a session. Opened by the parent's Alpine `peek`
     (inbox|todos|tasks|later|important).
     @param $layout, $mode, $lists, $bundles (step 4.3), $counts, $important (open marked tasks), $suggestedId, $locked --}}
<div class="flex min-h-0 flex-1 flex-col">
    @php
        $tabClass = 'shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    @endphp
    <div class="flex gap-1 overflow-x-auto border-b border-line px-3 pb-2" role="tablist">
        @foreach (\App\Models\Task::LISTS as $list)
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
        <button type="button" role="tab" data-peek-tab="important" @click="peek = 'important'" x-bind:aria-selected="peek === 'important'"
            x-bind:class="peek === 'important' ? 'bg-ink text-paper' : 'text-ink-soft hover:text-ink'"
            class="{{ $tabClass }}">{{ __('shell.important.label') }} <span class="font-mono text-xs opacity-70">{{ $important->count() }}</span></button>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3">
        @foreach (\App\Models\Task::LISTS as $list)
            <ul x-show="peek === '{{ $list }}'" x-cloak class="flex flex-col gap-2" role="tabpanel">
                @if ($list === 'inbox' && $counts['inbox'] > 0)
                    <li>
                        <x-shell.action key="sort" :layout="$layout" :mode="$mode" wire:click="$dispatch('open-sort')" @click="peek = null"
                            class="flex min-h-11 w-full items-center justify-center gap-2 rounded-card bg-accent px-4 font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            {{ __('shell.sort_inbox', ['count' => $counts['inbox']]) }}
                        </x-shell.action>
                    </li>
                @endif
                @if ($list === 'later' && $counts['later'] > 0 && $mode === 'board')
                    <li>
                        <a href="{{ route('shell.ideas') }}" wire:navigate class="flex min-h-11 w-full items-center justify-center gap-2 rounded-card border border-line px-4 text-sm font-semibold text-ink transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.ideas.as_cards') }}</a>
                    </li>
                @endif
                @foreach ($bundles[$list] as $bundle)
                    @include('shell.bundle', ['bundle' => $bundle, 'layout' => $layout, 'mode' => $mode, 'suggestedId' => $suggestedId, 'locked' => $locked])
                @endforeach
                @foreach ($lists[$list]->take(\App\Livewire\Shell\Board::LIST_LIMIT) as $task)
                    @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'suggested' => $task->id === $suggestedId, 'locked' => $locked])
                @endforeach
                @if ($counts[$list] === 0)
                    <li class="rounded-card border border-dashed border-line p-4 text-sm text-ink-faint">{{ __('shell.empty.'.$list) }}</li>
                @endif
                @if ($lists[$list]->count() > \App\Livewire\Shell\Board::LIST_LIMIT)
                    <li class="px-1 text-xs text-ink-faint">{{ __('shell.more_items', ['count' => $lists[$list]->count() - \App\Livewire\Shell\Board::LIST_LIMIT]) }}</li>
                @endif
            </ul>
        @endforeach
        {{-- Step 4.6: everything marked „Wichtig" in one place (Inbox, To-dos, Tasks; parked ideas stay out). --}}
        <ul x-show="peek === 'important'" x-cloak data-peek-panel="important" class="flex flex-col gap-2" role="tabpanel">
            @forelse ($important->take(\App\Livewire\Shell\Board::LIST_LIMIT) as $task)
                @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'suggested' => false, 'locked' => $locked, 'keyScope' => 'important', 'tagged' => false])
            @empty
                <li class="rounded-card border border-dashed border-line p-4 text-sm text-ink-faint">{{ __('shell.important.empty') }}</li>
            @endforelse
        </ul>
    </div>
</div>
