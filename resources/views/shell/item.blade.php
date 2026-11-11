{{-- One task row in a column (Werkbank) or a list sheet (Stapel).
     @param Task $task, string $layout, string $mode, bool $suggested, bool $locked (session runs) --}}
@php
    $canStart = in_array($task->list, \App\Models\Task::WORK_LISTS, true);
    $meta = \App\Shell\TaskFace::meta($task);
@endphp
<li
    wire:key="{{ $layout }}-{{ $mode }}-item-{{ $task->id }}"
    @class([
        'group relative flex items-start gap-2.5 rounded-card bg-surface px-3 py-2.5 text-sm',
        'ring-3 ring-accent-soft' => $suggested ?? false,
    ])
    style="border: var(--t-bw) solid {{ ($suggested ?? false) ? 'var(--t-accent)' : 'var(--t-line)' }}"
>
    <span class="mt-0.5 size-3.5 shrink-0 rounded-full border-2 border-ink-faint" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">
        <p class="font-medium leading-snug wrap-break-word text-ink">{{ $task->title }}</p>
        @if ($task->area || $meta)
            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-ink-faint">
                @if ($task->area)
                    <span class="inline-flex items-center gap-1 font-medium text-ink-soft">
                        <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
                    </span>
                    @if ($meta)<span aria-hidden="true">·</span>@endif
                @endif
                @if ($meta)<span>{{ $meta }}</span>@endif
            </p>
        @endif
        @if ($suggested ?? false)
            <p class="mt-1 font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('shell.start_here') }}</p>
        @endif
    </div>
    @if ($canStart)
        <x-shell.action
            key="start" :layout="$layout" :mode="$mode"
            wire:click="start({{ $task->id }})"
            wire:loading.attr="disabled"
            :disabled="$locked ?? false"
            :title="($locked ?? false) ? __('shell.session.not_now') : __('shell.start_this')"
            :aria-label="__('shell.start_this').': '.$task->title"
            class="-my-1 -mr-1 grid size-9 shrink-0 place-items-center rounded-full text-ink-faint transition hover:bg-accent-soft hover:text-accent-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent wb:absolute wb:top-1.5 wb:right-1.5 wb:m-0 wb:bg-surface wb:opacity-0 wb:shadow-card wb:group-hover:opacity-100 wb:focus-visible:opacity-100"
        >@include('shell.icon', ['name' => 'play', 'class' => 'size-4'])</x-shell.action>
    @endif
</li>
