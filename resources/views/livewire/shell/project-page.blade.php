{{-- Step 4.3: one project. Roadmap (stages in order, done / now / waiting), then every stage with
     its steps, then the steps in no stage. The next two steps (what the board shows) are marked. --}}
@php
    $project = $this->project;
    $stages = $project->stages->values();
    $steps = $this->steps;
    $bundle = $this->bundle;
    $locked = $this->locked;
    $done = $this->doneByStage;
    $nextIds = $bundle->visible->pluck('id')->all();
    $current = $bundle->stage ? $stages->search(fn ($s) => $s->id === $bundle->stage->id) : -1;
    $stateOf = fn (int $i) => $current === -1 || $i < $current ? 'done' : ($i === $current ? 'now' : 'waits');
    $btn = 'inline-flex min-h-11 wb:min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50';
    $input = 'min-h-11 wb:min-h-10 rounded-card border bg-paper px-3 text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    $groups = $stages->map(fn ($s) => ['key' => 'stage-'.$s->id, 'stage' => $s, 'steps' => $steps->where('stage_id', $s->id)->values()]);
    $loose = $steps->filter(fn ($t) => $t->stage_id === null || ! $stages->contains('id', $t->stage_id))->values();
@endphp
<div id="content" class="min-h-dvh pb-[calc(88px+env(safe-area-inset-bottom))] wb:pb-0">
    @include('shell.projects-chrome', ['part' => 'head', 'back' => route('shell.projects'), 'backLabel' => __('projects.back')])

    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status" data-notice>
            @if ($notice)
            <p class="pointer-events-auto flex max-w-md items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card">
                <span class="min-w-0 wrap-break-word">{{ $notice }}</span>
                @if ($undo)
                    <button type="button" wire:click="undoLast" wire:loading.attr="disabled" class="shrink-0 font-semibold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('projects.undo') }}</button>
                @endif
                <button type="button" wire:click="dismiss" class="shrink-0 -my-2 -mr-2.5 grid size-11 place-items-center rounded-full opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="{{ __('shell.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
            </p>
            @endif
    </div>

    <main class="mx-auto flex w-full max-w-4xl flex-col gap-5 lg:max-w-6xl px-4 pt-2 pb-12 wb:px-6 wb:pt-6">
        <a href="{{ route('shell.projects') }}" wire:navigate class="hidden items-center gap-1 self-start text-sm font-semibold text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:inline-flex">
            @include('shell.icon', ['name' => 'back', 'class' => 'size-4']) {{ __('projects.title') }}
        </a>

        {{-- Name, renamed in place (CONVENTIONS 6). --}}
        @if ($editingName)
            <form wire:submit="saveName" class="flex flex-wrap items-start gap-2" x-data x-init="$nextTick(() => $refs.n.focus())">
                <div class="flex min-w-0 flex-1 basis-60 flex-col gap-1">
                    <label for="project-name" class="sr-only">{{ __('projects.name_label') }}</label>
                    <input id="project-name" x-ref="n" type="text" wire:model="name" maxlength="120" @keydown.escape="$wire.set('editingName', false)"
                        @class([$input, 'w-full font-display text-xl', 'border-bad' => $errors->has('name'), 'border-line' => ! $errors->has('name')]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="{{ $btn }} bg-accent text-accent-fg">{{ __('projects.save') }}</button>
                <button type="button" wire:click="$set('editingName', false)" class="{{ $btn }} text-ink-soft">{{ __('projects.cancel') }}</button>
            </form>
        @else
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="min-w-0 wrap-break-word font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ $project->name }}</h1>
                <span class="text-sm text-ink-soft">{{ __('projects.kind_project') }}@if ($stages->isNotEmpty() && $bundle->stage) · {{ __('projects.card.stage', ['number' => $bundle->stageNumber, 'count' => $bundle->stageCount]) }}@endif</span>
                <button type="button" wire:click="editName" class="text-sm font-semibold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('projects.rename') }}</button>
            </div>
        @endif

        {{-- Roadmap: stages in order. Done filled, now ringed, waiting faint. --}}
        <section class="flex flex-col gap-3 rounded-panel bg-surface p-4" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="roadmap">
            <div class="flex items-center justify-between gap-2">
                <h2 id="roadmap" class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('projects.roadmap') }}</h2>
                <button type="button" wire:click="openStage" class="inline-flex min-h-11 wb:min-h-9 items-center gap-1 rounded-full border border-line px-3 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">@include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.add_stage') }}</button>
            </div>
            @if ($stages->isEmpty())
                <p class="text-sm text-ink-soft">{{ __('projects.roadmap_empty') }}</p>
            @else
                @include('shell.roadmap', ['stages' => $stages, 'states' => $stages->keys()->map($stateOf)->all()])
            @endif

            @if ($addingStage)
                <form wire:submit="addStage" class="flex flex-wrap items-end gap-2 border-t border-line pt-3" x-data x-init="$nextTick(() => $refs.s.focus())">
                    <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                        <label for="stage-name" class="text-xs font-semibold text-ink-soft">{{ __('projects.stage_name') }}</label>
                        <input id="stage-name" x-ref="s" type="text" wire:model="stageName" maxlength="120" @keydown.escape="$wire.cancelStage()" @class([$input, 'border-bad' => $errors->has('stageName'), 'border-line' => ! $errors->has('stageName')])>
                        @error('stageName')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="stage-date" class="text-xs font-semibold text-ink-soft">{{ __('projects.stage_date') }}</label>
                        <input id="stage-date" type="date" wire:model="stageDate" @class([$input, 'border-bad' => $errors->has('stageDate'), 'border-line' => ! $errors->has('stageDate')])>
                        @error('stageDate')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                        <p class="max-w-64 text-xs text-ink-faint">{{ __('projects.stage_date_hint') }}</p>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="addStage" class="{{ $btn }} bg-accent text-accent-fg">{{ __('projects.create') }}</button>
                    <button type="button" wire:click="cancelStage" class="{{ $btn }} text-ink-soft">{{ __('projects.cancel') }}</button>
                </form>
            @endif
        </section>

        {{-- Step 4.3b: steps left, notes right (from 1024 px); on the phone a switch between them. --}}
        <div x-data="{ tab: 'steps' }" class="flex flex-col gap-4">
            <div class="inline-flex self-start rounded-full bg-surface p-1 wb:hidden" style="border: var(--t-bw) solid var(--t-line)" role="tablist">
                <button type="button" role="tab" @click="tab = 'steps'" :aria-selected="tab === 'steps'" :class="tab === 'steps' ? 'bg-ink text-paper' : 'text-ink-soft'" class="rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('projects.notes.tab_steps') }}</button>
                <button type="button" role="tab" @click="tab = 'notes'" :aria-selected="tab === 'notes'" :class="tab === 'notes' ? 'bg-ink text-paper' : 'text-ink-soft'" class="rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('projects.notes.title') }} <span class="font-mono text-xs opacity-70">{{ $this->notes->count() }}</span></button>
            </div>
            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
            <div :class="tab === 'steps' ? 'flex' : 'hidden'" class="flex-col gap-5 wb:flex">
        {{-- Step 4.3d: pull an existing board task in. --}}
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="{{ $picking ? 'closePicker' : 'openPicker' }}" aria-expanded="{{ $picking ? 'true' : 'false' }}" aria-controls="task-picker" data-open-picker
                class="inline-flex min-h-11 wb:min-h-9 items-center gap-1 rounded-full border border-line px-3 text-sm font-semibold text-ink hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">@include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.adopt.button') }}</button>
        </div>
        @if ($picking)
            @include('shell.task-picker', ['pickable' => $this->pickable, 'total' => $this->pickableCount, 'any' => $this->hasPickable, 'search' => $pickSearch, 'stages' => $stages, 'groupList' => null])
        @endif
        {{-- Step 4.3c: the script of dragging, keyboard and announcements (resources/js/step-drag.js) owns this
             wrapper; `contents` keeps the lanes the flex children they were. --}}
        <div x-data="stepDrag(@js(['text' => __('projects.move.js')]))" class="contents">
        <p id="step-drag-hint" class="sr-only">{{ $stages->isNotEmpty() ? __('projects.move.keys_hint') : __('projects.move.keys_hint_list') }}</p>
        {{-- Stages with their steps, then the steps in no stage. --}}
        @foreach ([...$groups, ['key' => 'none', 'stage' => null, 'steps' => $loose]] as $g)
            @php
                $s = $g['stage'];
                $i = $s ? $stages->search(fn ($x) => $x->id === $s->id) : null;
                $state = $s ? $stateOf($i) : null;
                $doneCount = $s ? (int) ($done[$s->id] ?? 0) : 0;
            @endphp
            <section wire:key="sec-{{ $g['key'] }}" aria-labelledby="h-{{ $g['key'] }}" data-lane-box
                @class(['flex flex-col gap-2.5 rounded-panel p-4', 'bg-surface' => $state !== 'waits', 'bg-paper' => $state === 'waits'])
                style="border: var(--t-bw) solid {{ $state === 'now' ? 'var(--t-accent)' : 'var(--t-line)' }}">
                @if ($s && $editingStage === $s->id)
                    <form wire:submit="saveStage" class="flex flex-wrap items-end gap-2">
                        <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                            <label for="edit-stage-name" class="text-xs font-semibold text-ink-soft">{{ __('projects.stage_name') }}</label>
                            <input id="edit-stage-name" type="text" wire:model="editStageName" maxlength="120" @class([$input, 'border-bad' => $errors->has('editStageName'), 'border-line' => ! $errors->has('editStageName')])>
                            @error('editStageName')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="edit-stage-date" class="text-xs font-semibold text-ink-soft">{{ __('projects.stage_date') }}</label>
                            <input id="edit-stage-date" type="date" wire:model="editStageDate" @class([$input, 'border-bad' => $errors->has('editStageDate'), 'border-line' => ! $errors->has('editStageDate')])>
                            @error('editStageDate')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                            <p class="max-w-64 text-xs text-ink-faint">{{ __('projects.stage_date_hint') }}</p>
                        </div>
                        <button type="submit" class="{{ $btn }} bg-accent text-accent-fg">{{ __('projects.save') }}</button>
                        <button type="button" wire:click="cancelStage" class="{{ $btn }} text-ink-soft">{{ __('projects.cancel') }}</button>
                        <button type="button" wire:click="removeStage({{ $s->id }})" class="{{ $btn }} ml-auto text-bad hover:bg-bad-soft">{{ __('projects.remove_stage') }}</button>
                    </form>
                @else
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <h2 id="h-{{ $g['key'] }}" class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">
                            {{ $s ? ($i + 1).' · '.$s->name : ($stages->isEmpty() ? __('projects.then') : __('projects.no_stage')) }}
                        </h2>
                        @if ($s)
                            <span class="text-xs text-ink-faint" @if ($s->target_date) title="{{ __('projects.stage_date_hint') }}" @endif>{{ $s->target_date ? __('projects.until', ['date' => \App\Support\Dates::paddedFull($s->target_date)]) : __('projects.no_date') }}</span>
                            <span @class(['ml-auto font-mono text-[10.5px] uppercase tracking-wider', 'text-accent-ink' => $state !== 'waits', 'text-ink-faint' => $state === 'waits'])>
                                {{ $state === 'done' ? __('projects.stage_done') : ($state === 'now' ? __('projects.stage_now') : __('projects.stage_waits', ['number' => $i])) }}
                            </span>
                            <button type="button" wire:click="editStage({{ $s->id }})" class="text-xs font-semibold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('projects.rename') }}</button>
                            {{-- Step 4.3d: the stage's place in the roadmap. Plain buttons, so keyboard and touch both reach them. --}}
                            @if ($stages->count() > 1)
                                <span class="-my-1 inline-flex items-center" data-stage-order>
                                    @foreach ([[-1, 'earlier', 'arrow-up', $i === 0], [1, 'later', 'arrow-down', $i === $stages->count() - 1]] as [$by, $key, $icon, $edge])
                                        <button type="button" wire:click="shiftStage({{ $s->id }}, {{ $by }})" wire:loading.attr="disabled" wire:target="shiftStage" @disabled($edge)
                                            data-stage-{{ $key }} aria-label="{{ __('projects.stage_move.'.$key, ['name' => $s->name]) }}" title="{{ __('projects.stage_move.'.$key, ['name' => $s->name]) }}"
                                            class="grid size-8 place-items-center rounded-full text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-30 disabled:hover:bg-transparent">
                                            @include('shell.icon', ['name' => $icon, 'class' => 'size-4'])
                                        </button>
                                    @endforeach
                                </span>
                            @endif
                        @elseif ($stages->isNotEmpty())
                            <span class="text-xs text-ink-faint">{{ __('projects.no_stage_hint') }}</span>
                        @endif
                    </div>
                    @if ($state === 'now')
                        <div class="h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $doneCount + $g['steps']->count() }}" aria-valuenow="{{ $doneCount }}" aria-label="{{ __('projects.card.progress', ['done' => $doneCount, 'total' => $doneCount + $g['steps']->count()]) }}">
                            <span class="block h-full rounded-full bg-accent" style="width: {{ $doneCount + $g['steps']->count() > 0 ? round(100 * $doneCount / ($doneCount + $g['steps']->count())) : 0 }}%"></span>
                        </div>
                    @endif
                @endif

                {{-- Always rendered, even empty: a lane is a place a step can be dropped on (4.3c). --}}
                <ul data-lane="{{ $g['key'] }}" data-index="{{ $i ?? -1 }}" data-name="{{ $s?->name }}" class="flex flex-col gap-1.5">
                    @foreach ($g['steps'] as $task)
                        @php $waiting = ! in_array($task->id, $nextIds, true) && ($state === 'waits' || ($task->waitsOn && ! $task->waitsOn->is_completed)); @endphp
                        @include('shell.step-row', ['task' => $task, 'lane' => $g['key'], 'next' => in_array($task->id, $nextIds, true), 'waiting' => $waiting, 'first' => $loop->first, 'last' => $loop->last, 'locked' => $locked, 'stages' => $stages, 'others' => $steps])
                    @endforeach
                    @if ($g['steps']->isEmpty())
                        <li data-lane-empty class="rounded-card border border-dashed border-line px-3 py-2.5 text-center text-xs text-ink-faint">{{ __('projects.move.empty_lane') }}</li>
                    @endif
                </ul>

                <form wire:submit="addStep('{{ $g['key'] }}')" class="flex flex-wrap items-start gap-2">
                    <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                        <label for="new-step-{{ $g['key'] }}" class="sr-only">{{ __('projects.step_title') }}</label>
                        <input id="new-step-{{ $g['key'] }}" type="text" wire:model="newStep.{{ $g['key'] }}" maxlength="255" placeholder="+ {{ __('projects.step_title') }}" autocomplete="off"
                            @class([$input, 'w-full', 'border-bad' => $errors->has('newStep.'.$g['key']), 'border-line border-dashed' => ! $errors->has('newStep.'.$g['key'])])>
                        @error('newStep.'.$g['key'])<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                    </div>
                    <label class="sr-only" for="new-step-list-{{ $g['key'] }}">{{ __('projects.group_list') }}</label>
                    <select id="new-step-list-{{ $g['key'] }}" wire:model="newStepList" class="{{ $input }} border-line pr-8">
                        @foreach (\App\Models\Task::WORK_LISTS as $list)<option value="{{ $list }}">{{ __('projects.lists.'.$list) }}</option>@endforeach
                    </select>
                    <button type="submit" wire:loading.attr="disabled" wire:target="addStep('{{ $g['key'] }}')" class="{{ $btn }} border border-line bg-surface text-ink hover:bg-paper">{{ __('projects.add_step') }}</button>
                </form>
            </section>
        @endforeach
        </div>
            </div>
            <div :class="tab === 'notes' ? 'block' : 'hidden'" class="wb:block lg:sticky lg:top-20">
                @include('shell.notes-panel', ['notes' => $this->notes, 'stages' => $stages, 'userTags' => $this->userTags, 'noteTag' => $noteTag, 'editingNote' => $editingNote])
            </div>
            </div>
        </div>

        {{-- Step 4.3d: deleting is the quietest thing on the page: last, plain text, behind a confirm. --}}
        <div class="mt-2 flex border-t border-line pt-4">
            <button type="button" wire:click="askDelete" data-delete-owner class="inline-flex min-h-11 wb:min-h-10 items-center rounded-card px-3 text-sm font-semibold text-bad hover:bg-bad-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('projects.delete.project') }}</button>
        </div>
    </main>

    @if ($confirmingDelete)
        @include('shell.confirm-delete', ['kind' => 'project', 'name' => $project->name, 'impact' => $this->impact, 'error' => $deleteError])
    @endif

    @include('shell.projects-chrome', ['part' => 'tabs'])
</div>
