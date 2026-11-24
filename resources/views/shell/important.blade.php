{{-- Step 4.6, R5: the „Wichtig" reminder. Shown where the app offers no work suggestion (outside pomodoro
     blocks), so what the user marked stays in sight without turning into a "Start here" card. Starting one
     by hand stays possible. The rest of the marked tasks sits at the top of its list.
     @param string $layout, Collection<Task> $tasks (open marked tasks) --}}
@php
    $shown = $tasks->take(\App\Livewire\Shell\Board::IMPORTANT_LIMIT);
    $rest = $tasks->count() - $shown->count();
    $headingId = 'important-'.$layout;
@endphp
<section data-important-reminder class="fold-away flex min-w-0 flex-col gap-2 rounded-panel bg-surface p-3" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="{{ $headingId }}">
    <h3 id="{{ $headingId }}" class="flex items-center gap-2 font-display text-[13px] text-ink" style="font-weight: var(--t-dw)">
        @include('shell.icon', ['name' => 'flag', 'class' => 'size-4 text-warn', 'filled' => true])
        {{ __('shell.important.label') }}
        <em class="font-mono text-xs font-normal not-italic text-ink-faint">{{ $tasks->count() }}</em>
    </h3>
    <ul class="flex flex-col gap-1.5">
        @foreach ($shown as $task)
            @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => 'board', 'suggested' => false, 'locked' => false, 'keyScope' => 'reminder', 'tagged' => false])
        @endforeach
        @if ($rest > 0)
            <li class="px-1 text-xs text-ink-faint">
                @if ($layout === 'stapel')
                    <button type="button" @click="peek = 'important'" class="font-semibold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.more_items', ['count' => $rest]) }}</button>
                @else
                    {{ __('shell.more_items', ['count' => $rest]) }}
                @endif
            </li>
        @endif
    </ul>
</section>
