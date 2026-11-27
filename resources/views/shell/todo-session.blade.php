{{-- The To-do session (2026-11-24): the day's To-dos as ONE card at the top of the To-dos list (Werkbank column,
     Stapel list sheet, session peek). The most urgent loose To-dos that fit into 25 minutes; the rest waits
     below it for the next session. Starting it starts the first To-do; after each Done the next pick stays a
     To-do until the 25 minutes are used (Suggestions::nextInSession). The To-dos stay editable rows.
     Under the card (Niels, 2026-11-27): a quiet list of what the engine plans for the rest of today's work block
     (BlockPlan), session after session. It can be folded; the state is the page's own (Alpine), not saved.
     @param TodoSession $todoSession, string $layout, string $mode, ?int $suggestedId, bool $locked --}}
@if (! $todoSession->isEmpty())
    @php($first = $todoSession->first())
    <li wire:key="{{ $layout }}-{{ $mode }}-todo-session" data-todo-session data-fold-from
        class="flex flex-col gap-2 rounded-panel p-2.5"
        style="background: color-mix(in srgb, var(--t-accent) 6%, var(--t-surface)); border: var(--t-bw) solid var(--t-line); border-left: 3px solid var(--t-accent)">
        <div class="flex min-w-0 items-center gap-2 px-1 py-0.5 text-sm text-ink">
            @include('shell.icon', ['name' => 'todos', 'class' => 'size-4 shrink-0 text-ink-soft'])
            <b class="min-w-0 truncate font-semibold">{{ __('shell.todo_session.title') }}</b>
            <span class="ml-auto shrink-0 font-mono text-xs text-ink-faint tabular-nums" data-todo-session-meta>{{ __('shell.todo_session.meta', ['count' => $todoSession->tasks->count(), 'minutes' => $todoSession->minutes()]) }}</span>
        </div>
        <ul class="flex flex-col gap-1.5">
            @foreach ($todoSession->tasks as $task)
                @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'suggested' => $task->id === $suggestedId, 'locked' => $locked])
            @endforeach
        </ul>
        @if ($mode === 'board' && ! $locked)
            <x-shell.action
                key="start" :layout="$layout" mode="board"
                @click="foldInto($el, () => $wire.start({{ $first->id }}))" wire:loading.attr="disabled"
                class="inline-flex min-h-11 w-full items-center justify-center gap-2 whitespace-nowrap rounded-card bg-accent px-3 font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
            >
                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                {{ __('shell.todo_session.start', ['minutes' => $todoSession->minutes()]) }}
            </x-shell.action>
        @endif
        @if ($todoSession->waiting > 0)
            <p class="px-1 text-xs text-ink-faint">{{ trans_choice('shell.todo_session.waiting', $todoSession->waiting, ['count' => $todoSession->waiting]) }}</p>
        @endif
        @if ($todoSession->block && ! $todoSession->block->isEmpty())
            @php($plan = $todoSession->block)
            @php($planId = 'block-plan-'.$layout.'-'.$mode)
            <div data-block-plan x-data="{ open: true }" class="mt-0.5 flex flex-col gap-1 border-t pt-1" style="border-color: var(--t-line)">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="{{ $planId }}" data-block-plan-toggle
                    class="flex min-h-11 w-full min-w-0 items-center justify-between gap-x-2 gap-y-0 rounded-card px-1 text-left text-xs text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    <span class="min-w-0 flex-1 font-semibold">{{ __('shell.todo_session.block.title', ['name' => $plan->name]) }}<span class="block font-mono font-normal tabular-nums text-ink-faint">{{ $plan->from->format('H:i') }}–{{ $plan->to->format('H:i') }}</span></span>
                    <span class="shrink-0 transition-transform motion-reduce:transition-none" :class="open ? 'rotate-90' : ''">@include('shell.icon', ['name' => 'chevron', 'class' => 'size-4 text-ink-faint'])</span>
                </button>
                <div id="{{ $planId }}" x-show="open" class="flex flex-col gap-1.5 px-1 pb-1">
                    <p class="text-xs text-ink-faint">{{ trans_choice('shell.todo_session.block.meta', $plan->tasks()->count(), ['count' => $plan->tasks()->count(), 'minutes' => $plan->minutes()]) }}</p>
                    <ol class="flex flex-col gap-1.5">
                        @foreach ($plan->sessions as $session)
                            <li data-block-plan-session class="flex items-baseline gap-2.5 text-sm text-ink">
                                <span class="w-10 shrink-0 font-mono text-xs tabular-nums text-ink-faint">{{ $session['start']->format('H:i') }}</span>
                                <ul class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    @foreach ($session['rows'] as $row)
                                        <li class="flex min-w-0 items-baseline gap-1.5">
                                            <span class="min-w-0 truncate">{{ $row['task']->title }}</span>
                                            <span class="shrink-0 font-mono text-xs tabular-nums text-ink-faint">{{ $row['minutes'] }} min</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ol>
                    @if ($plan->waiting > 0)
                        <p class="text-xs text-ink-faint">{{ trans_choice('shell.todo_session.block.waiting', $plan->waiting, ['count' => $plan->waiting]) }}</p>
                    @endif
                </div>
            </div>
        @endif
    </li>
@endif
