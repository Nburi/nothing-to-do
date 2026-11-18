{{-- Step 4.3: one open step on a project or group page. The next two are marked, waiting ones are
     quiet. Order arrows, ▶ and "Erledigt" are always there; stage and "wartet auf" (projects only)
     sit behind the ⋯ menu so the list stays calm.
     @param Task $task, bool $next, bool $waiting, bool $first, bool $last, bool $locked,
            ?Collection $stages (project), ?Collection $others (open steps of the project) --}}
@php
    $icon = 'grid size-9 shrink-0 place-items-center rounded-full text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-30 disabled:hover:bg-transparent';
    $canStart = in_array($task->list, \App\Models\Task::WORK_LISTS, true);
    $meta = \App\Shell\TaskFace::meta($task);
    $blocker = $task->waitsOn && ! $task->waitsOn->is_completed ? $task->waitsOn : null;
@endphp
<li wire:key="step-{{ $task->id }}" data-step="{{ $task->id }}"
    @class(['flex flex-wrap items-start gap-x-2 gap-y-1 rounded-card px-3 py-2 text-sm', 'bg-surface ring-3 ring-accent-soft' => $next, 'bg-surface' => ! $next, 'opacity-60' => $waiting])
    style="border: var(--t-bw) solid {{ $next ? 'var(--t-accent)' : 'var(--t-line)' }}">
    <div class="min-w-0 flex-1 basis-48 py-1">
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
        <button type="button" wire:click="move({{ $task->id }}, -1)" @disabled($first) class="{{ $icon }}" aria-label="{{ __('projects.move_up') }}: {{ $task->title }}" title="{{ __('projects.move_up') }}">
            @include('shell.icon', ['name' => 'chevron', 'class' => 'size-4 -rotate-90'])
        </button>
        <button type="button" wire:click="move({{ $task->id }}, 1)" @disabled($last) class="{{ $icon }}" aria-label="{{ __('projects.move_down') }}: {{ $task->title }}" title="{{ __('projects.move_down') }}">
            @include('shell.icon', ['name' => 'chevron', 'class' => 'size-4 rotate-90'])
        </button>
        <button type="button" wire:click="done({{ $task->id }})" wire:loading.attr="disabled" class="{{ $icon }}" aria-label="{{ __('projects.done') }}: {{ $task->title }}" title="{{ __('projects.done') }}">
            @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
        </button>
        @isset($stages)
            <details class="relative" x-data @click.outside="$el.open = false">
                <summary class="{{ $icon }} cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="{{ __('projects.move_to_stage') }} / {{ __('projects.waits_on') }}: {{ $task->title }}">
                    @include('shell.icon', ['name' => 'dots', 'class' => 'size-4'])
                </summary>
                <div class="absolute right-0 z-20 mt-1 flex w-64 flex-col gap-3 rounded-card bg-surface p-3 shadow-card" style="border: var(--t-bw) solid var(--t-line)">
                    <label class="flex flex-col gap-1 text-xs font-semibold text-ink-soft">
                        {{ __('projects.move_to_stage') }}
                        <select wire:change="setStage({{ $task->id }}, $event.target.value)" class="min-h-10 rounded-card border border-line bg-paper px-2 text-sm font-normal text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            <option value="" @selected($task->stage_id === null)>{{ __('projects.no_stage') }}</option>
                            @foreach ($stages as $i => $s)
                                <option value="{{ $s->id }}" @selected($task->stage_id === $s->id)>{{ $i + 1 }} · {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold text-ink-soft">
                        {{ __('projects.waits_on') }}
                        <select wire:change="setWaitsOn({{ $task->id }}, $event.target.value)" class="min-h-10 rounded-card border border-line bg-paper px-2 text-sm font-normal text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            <option value="" @selected($blocker === null)>{{ __('projects.waits_on_none') }}</option>
                            @foreach ($others as $o)
                                @continue($o->id === $task->id)
                                <option value="{{ $o->id }}" @selected($task->waits_on_id === $o->id)>{{ \Illuminate\Support\Str::limit($o->title, 50) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </details>
        @endisset
        @if ($canStart)
            <button type="button" wire:click="start({{ $task->id }})" wire:loading.attr="disabled" @disabled($locked)
                title="{{ $locked ? __('projects.not_now') : __('projects.start') }}" aria-label="{{ __('projects.start') }}: {{ $task->title }}"
                class="ml-1 grid size-9 shrink-0 place-items-center rounded-full bg-accent text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-40">
                @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
            </button>
        @endif
    </div>
</li>
