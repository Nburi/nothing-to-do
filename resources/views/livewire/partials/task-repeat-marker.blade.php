{{-- Inline marker for a recurring task, rendered inside a card's title span
     (all three card partials share it, so they can never disagree). A small
     "loop" icon; once the task is done, also where its next occurrence landed —
     the moment the user completes a repeating task is exactly when they
     wonder "and when does this come back?". The hint is inline-block on
     purpose: text-decoration (the completed title's line-through) does not
     propagate into an inline-block, so the hint stays readable. --}}
@if ($task->isRepeating())
    <svg class="-mt-0.5 mr-1 inline h-3 w-3 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Wiederholt sich: {{ \App\Models\Task::REPEAT_RULES[$task->repeat_rule] }}"><title>Wiederholt sich: {{ \App\Models\Task::REPEAT_RULES[$task->repeat_rule] }}</title><path d="M4 9a6 6 0 0 1 10.2-4.2L16 6.5"/><path d="M16 3v3.5h-3.5"/><path d="M16 11a6 6 0 0 1-10.2 4.2L4 13.5"/><path d="M4 17v-3.5h3.5"/></svg>
    @if ($task->is_completed && ($nextLabel = $task->repeatSuccessor?->effectiveDateLabel()))
        <span class="mr-1 inline-block text-[11px] font-normal text-ink-faint">nächste: {{ $nextLabel }} ·</span>
    @endif
@endif
