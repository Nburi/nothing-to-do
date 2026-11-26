{{-- Step 4.3: one project or group on the board (Werkbank column, Stapel list sheet, session peek).
     The old app's group card (Niels, 2026-11-17): header with name, count and arrow to its page,
     a thin progress bar, at most two steps inside, "+N weitere". Projects add their current stage;
     their bar is the stage's, groups' the whole group's; a project without stages has no bar.
     Step 4.8: the card is one slot of the board order (`data-entry`), draggable like a loose task.
     @param Bundle $bundle, string $layout, string $mode, ?int $suggestedId, bool $locked --}}
@php
    /** @var \App\Services\Projects\Bundle $bundle */
    $isProject = $bundle->isProject();
    $stage = $bundle->stage;
    $count = $isProject
        ? ($bundle->stageCount > 0 && $stage ? __('projects.card.stage', ['number' => $bundle->stageNumber, 'count' => $bundle->stageCount]) : null)
        : $bundle->progress['done'].'/'.$bundle->progress['total'];
@endphp
<li wire:key="{{ $layout }}-{{ $mode }}-bundle-{{ $bundle->kind }}-{{ $bundle->id }}" data-bundle="{{ $bundle->kind }}-{{ $bundle->id }}"
    data-entry="{{ $bundle->ref() }}" data-title="{{ $bundle->name }}"
    class="group/card relative flex flex-col gap-2 rounded-panel p-2.5"
    style="background: color-mix(in srgb, var(--t-ink) 3%, var(--t-surface)); border: var(--t-bw) solid var(--t-line); border-left: 3px solid {{ $isProject ? 'var(--t-accent)' : 'var(--t-faint)' }}">
    <x-shell.action key="reorder" :layout="$layout" :mode="$mode" data-grip aria-pressed="false"
        :aria-label="__('shell.order.grip_named', ['title' => $bundle->name])" :title="__('shell.order.grip')"
        class="absolute left-0 top-3 z-10 grid h-8 w-3.5 place-items-center rounded-full text-ink-faint opacity-0 transition hover:text-ink focus:outline-hidden focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-accent wb:cursor-grab wb:group-hover/card:opacity-100">@include('shell.icon', ['name' => 'grip', 'class' => 'size-3.5'])</x-shell.action>
    <a href="{{ $bundle->url() }}" wire:navigate
        class="group/head flex min-w-0 items-center gap-2 rounded-card px-1 py-0.5 text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
        aria-label="{{ __('projects.card.open', ['name' => $bundle->name]) }}">
        @include('shell.icon', ['name' => $isProject ? 'project' : 'group', 'class' => 'size-4 shrink-0 text-ink-soft'])
        <b class="min-w-0 truncate font-semibold underline decoration-ink-faint decoration-dotted underline-offset-4 group-hover/head:decoration-solid">{{ $bundle->name }}</b>
        @if ($count)
            <span class="shrink-0 font-mono text-xs text-ink-faint tabular-nums">{{ $count }}</span>
        @endif
        @include('shell.icon', ['name' => 'chevron', 'class' => 'ml-auto size-4 shrink-0 text-ink-faint transition group-hover/head:translate-x-0.5'])
    </a>
    @if ($isProject && $stage)
        <p class="-mt-1.5 truncate pl-7 text-xs text-ink-soft">
            {{ $stage->target_date ? __('projects.card.stage_until', ['name' => $stage->name, 'date' => \App\Support\Dates::padded($stage->target_date)]) : $stage->name }}
        </p>
    @endif
    @if ($bundle->progress !== null)
        <div class="mx-1 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $bundle->progress['total'] }}" aria-valuenow="{{ $bundle->progress['done'] }}"
            aria-label="{{ __('projects.card.progress', $bundle->progress) }}">
            <span class="block h-full rounded-full bg-accent" style="width: {{ $bundle->percent() }}%"></span>
        </div>
    @endif
    <ul class="flex flex-col gap-1.5">
        @foreach ($bundle->visible as $task)
            @include('shell.item', ['task' => $task, 'layout' => $layout, 'mode' => $mode, 'suggested' => $task->id === $suggestedId, 'locked' => $locked])
        @endforeach
    </ul>
    @if ($bundle->more > 0)
        <a href="{{ $bundle->url() }}" wire:navigate class="px-1 text-xs text-ink-faint hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            {{ trans_choice($isProject ? 'projects.card.more_project' : 'projects.card.more_group', $bundle->more, ['count' => $bundle->more]) }}
        </a>
    @endif
</li>
