{{-- One task row in a column (Werkbank) or a list sheet (Stapel).
     @param Task $task, string $layout, string $mode, bool $suggested, bool $locked (session runs),
     ?string $keyScope (the same task in a second place, e.g. the reminder, needs its own wire:key),
     bool $tagged (say „Wichtig" in words; false where the surrounding list already says it),
     bool $sortable (step 4.8: a slot of the board order; only loose rows of a column, a sheet or the band) --}}
@php
    $canStart = in_array($task->list, \App\Models\Task::WORK_LISTS, true);
    $meta = \App\Shell\TaskFace::meta($task);
    // Step 4.6 (R5): „Wichtig" is shown by shape and word as well as colour: a solid flag, the word, a thicker edge.
    $marked = (bool) $task->is_important;
    $key = $layout.'-'.$mode.'-'.(isset($keyScope) ? $keyScope.'-' : '').'item-'.$task->id;
@endphp
<li
    wire:key="{{ $key }}"
    data-fold-from
    @if ($sortable ?? false) data-entry="task:{{ $task->id }}" data-title="{{ $task->title }}" @endif
    @class([
        'group relative flex items-start gap-2.5 rounded-card bg-surface px-3 py-2.5 text-sm',
        'ring-3 ring-accent-soft' => $suggested ?? false,
        'shadow-[inset_4px_0_0_var(--t-warn)]' => $marked,
    ])
    style="border: var(--t-bw) solid {{ ($suggested ?? false) ? 'var(--t-accent)' : 'var(--t-line)' }}"
>
    @if ($sortable ?? false)
        <x-shell.action key="reorder" :layout="$layout" :mode="$mode" data-grip aria-pressed="false"
            :aria-label="__('shell.order.grip_named', ['title' => $task->title])" :title="__('shell.order.grip')"
            class="absolute left-0 top-1/2 z-10 grid h-9 w-4 -translate-y-1/2 place-items-center rounded-full text-ink-faint opacity-0 transition hover:text-ink focus:outline-hidden focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-accent wb:cursor-grab wb:group-hover:opacity-100">@include('shell.icon', ['name' => 'grip', 'class' => 'size-3.5'])</x-shell.action>
    @endif
    <span class="mt-0.5 size-3.5 shrink-0 rounded-full border-2 border-ink-faint" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">
        <p data-fold-title @class(['leading-snug wrap-break-word text-ink', 'font-medium' => ! $marked, 'font-semibold' => $marked])>{{ $task->title }}</p>
        @if ($task->area || $meta)
            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-ink-faint">
                @if ($task->area)
                    <span data-fold-area class="inline-flex items-center gap-1 font-medium text-ink-soft">
                        <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($task->area->color) }}"></span>{{ $task->area->name }}
                    </span>
                    @if ($meta)<span aria-hidden="true">·</span>@endif
                @endif
                @if ($meta)<span>{{ $meta }}</span>@endif
            </p>
        @endif
        @if ($marked && ($tagged ?? true))
            <p class="mt-1"><span data-important-tag class="inline-flex items-center gap-1 rounded-[5px] bg-warn-soft px-1.5 py-px font-mono text-[10.5px] font-medium uppercase tracking-wider text-warn">@include('shell.icon', ['name' => 'flag', 'class' => 'size-3', 'filled' => true]){{ __('shell.important.label') }}</span></p>
        @endif
        @if ($suggested ?? false)
            <p class="mt-1 font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('shell.start_here') }}</p>
        @endif
    </div>
    {{-- Step 4.6: one tap marks or unmarks. On the desktop it shows on hover and focus like Start; on touch it is always there. --}}
    @php
        $flagClass = implode(' ', array_filter([
            'task-action wb:bg-surface',
            $canStart ? '' : '-mr-1',
            $canStart ? 'wb:right-[3.125rem]' : 'wb:right-1.5',
            $marked ? 'bg-warn-soft text-warn hover:brightness-95' : 'text-ink-faint hover:bg-warn-soft hover:text-warn',
        ]));
    @endphp
    <x-shell.action key="important" :layout="$layout" :mode="$mode"
        wire:click="setImportant({{ $task->id }}, {{ $marked ? 'false' : 'true' }})" wire:loading.attr="disabled"
        :aria-pressed="$marked ? 'true' : 'false'" :title="__('shell.important.'.($marked ? 'unmark' : 'mark'))" :aria-label="__('shell.important.mark').': '.$task->title"
        class="{{ $flagClass }}">@include('shell.icon', ['name' => 'flag', 'class' => 'size-4', 'filled' => $marked])</x-shell.action>
    {{-- Step 4.9: ⋯ opens Bearbeiten, Verschieben, Löschen. Next to the flag on the desktop, last on touch. --}}
    @php
        $menuClass = implode(' ', [
            'grid size-11 wb:size-9 shrink-0 cursor-pointer place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent -my-1',
            $canStart ? '' : '-mr-1',
            'wb:absolute wb:top-1.5 wb:m-0 wb:bg-surface wb:opacity-0 wb:shadow-card wb:group-hover:opacity-100 wb:focus-visible:opacity-100',
            $canStart ? 'wb:right-[5.875rem]' : 'wb:right-[3.125rem]',
        ]);
    @endphp
    @include('shell.item-menu', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'trigger' => $menuClass])
    @if ($canStart)
        @php
            $startClass = 'task-action -mr-1 text-ink-faint hover:bg-accent-soft hover:text-accent-ink disabled:hover:bg-transparent wb:right-1.5 wb:bg-surface';
            $startTitle = ($locked ?? false) ? __('shell.session.not_now') : __('shell.start_this');
        @endphp
        @if ($mode === 'board')
            {{-- From the board, a start folds the board away (step 3.1). --}}
            <x-shell.action key="start" :layout="$layout" :mode="$mode"
                @click="foldInto($el, () => $wire.start({{ $task->id }}))" wire:loading.attr="disabled"
                :disabled="$locked ?? false" :title="$startTitle" :aria-label="__('shell.start_this').': '.$task->title"
                class="{{ $startClass }}">@include('shell.icon', ['name' => 'play', 'class' => 'size-4'])</x-shell.action>
        @else
            <x-shell.action key="start" :layout="$layout" :mode="$mode"
                wire:click="start({{ $task->id }})" wire:loading.attr="disabled"
                :disabled="$locked ?? false" :title="$startTitle" :aria-label="__('shell.start_this').': '.$task->title"
                class="{{ $startClass }}">@include('shell.icon', ['name' => 'play', 'class' => 'size-4'])</x-shell.action>
        @endif
    @endif
</li>
