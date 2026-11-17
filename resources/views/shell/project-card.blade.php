{{-- Step 4.3: a project or group on the Projekte page: name, stage, bar, its next two steps with ▶,
     "+N weitere". The same Bundle as on the board. @param Bundle $bundle, bool $locked --}}
@php
    /** @var \App\Services\Projects\Bundle $bundle */
    $isProject = $bundle->isProject();
    $stage = $bundle->stage;
@endphp
<article wire:key="card-{{ $bundle->kind }}-{{ $bundle->id }}" class="flex flex-col gap-3 rounded-panel bg-surface p-4"
    style="border: var(--t-bw) solid var(--t-line); border-left: 3px solid {{ $isProject ? 'var(--t-accent)' : 'var(--t-faint)' }}">
    <a href="{{ $bundle->url() }}" wire:navigate class="group/head flex min-w-0 items-center gap-2 rounded-card text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
        @include('shell.icon', ['name' => $isProject ? 'project' : 'group', 'class' => 'size-4 shrink-0 text-ink-soft'])
        <h3 class="min-w-0 truncate font-display text-[15px] underline decoration-ink-faint decoration-dotted underline-offset-4 group-hover/head:decoration-solid" style="font-weight: var(--t-dw)">{{ $bundle->name }}</h3>
        <span class="shrink-0 font-mono text-xs text-ink-faint tabular-nums">
            @if ($isProject)
                @if ($bundle->stageCount > 0 && $stage){{ __('projects.card.stage', ['number' => $bundle->stageNumber, 'count' => $bundle->stageCount]) }}@endif
            @else
                {{ $bundle->progress['done'] }}/{{ $bundle->progress['total'] }}
            @endif
        </span>
        @include('shell.icon', ['name' => 'chevron', 'class' => 'ml-auto size-4 shrink-0 text-ink-faint'])
    </a>
    @if ($isProject && $stage)
        <p class="-mt-2 truncate pl-6 text-xs text-ink-soft">{{ $stage->target_date ? __('projects.card.stage_until', ['name' => $stage->name, 'date' => $stage->target_date->format('d.m.')]) : $stage->name }}</p>
    @endif
    @if ($bundle->progress !== null)
        <div class="h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $bundle->progress['total'] }}" aria-valuenow="{{ $bundle->progress['done'] }}" aria-label="{{ __('projects.card.progress', $bundle->progress) }}">
            <span class="block h-full rounded-full bg-accent" style="width: {{ $bundle->percent() }}%"></span>
        </div>
    @endif
    @if ($bundle->visible->isEmpty())
        <p class="rounded-card border border-dashed border-line px-3 py-2 text-[13px] text-ink-faint">{{ __('projects.waiting') }}</p>
    @else
        <ul class="flex flex-col gap-1.5">
            @foreach ($bundle->visible as $task)
                <li wire:key="card-step-{{ $task->id }}" class="flex items-start gap-2.5 rounded-card bg-paper px-3 py-2 text-sm" style="border: var(--t-bw) solid var(--t-line)">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium leading-snug wrap-break-word text-ink">{{ $task->title }}</p>
                        @if ($meta = \App\Shell\TaskFace::meta($task))<p class="mt-0.5 text-xs text-ink-faint">{{ __('projects.lists.'.$task->list) }} · {{ $meta }}</p>@endif
                    </div>
                    @if (in_array($task->list, \App\Models\Task::WORK_LISTS, true))
                        <button type="button" wire:click="start({{ $task->id }})" wire:loading.attr="disabled" @disabled($locked)
                            title="{{ $locked ? __('projects.not_now') : __('projects.start') }}" aria-label="{{ __('projects.start') }}: {{ $task->title }}"
                            class="-my-1 grid size-9 shrink-0 place-items-center rounded-full bg-accent text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-40">
                            @include('shell.icon', ['name' => 'play', 'class' => 'size-4'])
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($bundle->more > 0)
        <a href="{{ $bundle->url() }}" wire:navigate class="text-xs text-ink-faint hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            {{ trans_choice($isProject ? 'projects.card.more_project' : 'projects.card.more_group', $bundle->more, ['count' => $bundle->more]) }}
        </a>
    @endif
</article>
