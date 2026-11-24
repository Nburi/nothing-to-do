{{-- Step 4.3 / 4.3c: one open step on a project or group page. The next two are marked, waiting ones are
     quiet. Left the grip (drag with the mouse, press and hold on a touch screen, Space on the keyboard:
     resources/js/step-drag.js; it has no `touch-action: none`, so a swipe over it still scrolls), then
     ▶ and "Erledigt". Moving without dragging, the stage and "wartet
     auf" (projects only) sit behind the ⋯ menu so the list stays calm.
     A waiting step is dimmed part by part (title, buttons, the ⋯ trigger), never as one block: the open
     menu is a child of this row and would inherit a fade on the row, so it stayed unreadable (C30). The
     grip is never dimmed: a waiting step stays draggable.
     The data attributes are what the script reads: `data-step` (id), `data-title`, `data-list`,
     `data-waits-on` (the open step it waits on, if any).
     @param Task $task, string $lane ("none" or "stage-ID", the `data-lane` of its list), bool $next,
            bool $waiting, bool $first, bool $last, bool $locked,
            ?Collection $stages (project), ?Collection $others (open steps of the project) --}}
@php
    $dim = $waiting ? 'opacity-60' : '';
    $icon = 'grid size-9 shrink-0 place-items-center rounded-full text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-30 disabled:hover:bg-transparent '.$dim;
    $canStart = in_array($task->list, \App\Models\Task::WORK_LISTS, true);
    $meta = \App\Shell\TaskFace::meta($task);
    $blocker = $task->waitsOn && ! $task->waitsOn->is_completed ? $task->waitsOn : null;
@endphp
<li wire:key="step-{{ $task->id }}" data-step="{{ $task->id }}" data-title="{{ $task->title }}" data-list="{{ $task->list }}"
    @if ($blocker) data-waits-on="{{ $blocker->id }}" @endif @if ($waiting) data-waiting @endif
    @class(['flex flex-wrap items-start gap-x-2 gap-y-1 rounded-card px-3 py-2 text-sm', 'bg-surface ring-3 ring-accent-soft' => $next, 'bg-surface' => ! $next])
    style="border: var(--t-bw) solid {{ $next ? 'var(--t-accent)' : 'var(--t-line)' }}">
    <button type="button" data-grip aria-pressed="false" aria-describedby="step-drag-hint"
        aria-label="{{ __('projects.move.grip', ['title' => $task->title]) }}" title="{{ __('projects.move.grip_title') }}"
        class="-ml-1.5 grid size-9 shrink-0 cursor-grab place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
        @include('shell.icon', ['name' => 'grip', 'class' => 'size-4'])
    </button>
    <div class="min-w-0 flex-1 basis-40 py-1 {{ $dim }}">
        <p class="font-medium leading-snug wrap-break-word text-ink">{{ $task->title }}</p>
        <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-faint">
            <span>{{ __('projects.lists.'.$task->list) }}</span>
            @if ($meta)<span aria-hidden="true">·</span><span>{{ $meta }}</span>@endif
            @if ($blocker)
                <span class="inline-flex items-center rounded-full border border-warn px-2 text-[11px] text-warn">{{ __('projects.waits_on_chip', ['title' => \Illuminate\Support\Str::limit($blocker->title, 40)]) }}</span>
            @endif
        </p>
        @if ($next)
            <p class="mt-1 font-mono text-[10.5px] font-medium uppercase tracking-wider text-accent-ink">{{ __('projects.next') }}</p>
        @endif
    </div>
    <div class="ml-auto flex items-center gap-0.5">
        <button type="button" wire:click="done({{ $task->id }})" wire:loading.attr="disabled" class="{{ $icon }}" aria-label="{{ __('projects.done') }}: {{ $task->title }}" title="{{ __('projects.done') }}">
            @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
        </button>
        @include('shell.step-menu', ['task' => $task, 'lane' => $lane, 'first' => $first, 'last' => $last, 'icon' => $icon, 'blocker' => $blocker, 'stages' => $stages ?? null, 'others' => $others ?? null])
        @if ($canStart)
            <button type="button" wire:click="start({{ $task->id }})" wire:loading.attr="disabled" @disabled($locked)
                title="{{ $locked ? __('projects.not_now') : __('projects.start') }}" aria-label="{{ __('projects.start') }}: {{ $task->title }}"
                class="ml-1 grid size-9 shrink-0 place-items-center rounded-full bg-accent text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-40 {{ $dim }}">
                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
            </button>
        @endif
    </div>
</li>
