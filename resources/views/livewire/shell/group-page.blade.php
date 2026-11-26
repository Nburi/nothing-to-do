{{-- Step 4.3: one group. Its tasks in order; the first two are on the board. --}}
@php
    $group = $this->group;
    $steps = $this->steps;
    $doneCount = $this->doneCount;
    $total = $doneCount + $steps->count();
    $locked = $this->locked;
    $list = $group->list ?? 'tasks';
    $btn = 'inline-flex min-h-11 wb:min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50';
    $input = 'min-h-11 wb:min-h-10 rounded-card border bg-paper px-3 text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
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

    <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 lg:max-w-5xl px-4 pt-2 pb-12 wb:px-6 wb:pt-6">
        <a href="{{ route('shell.projects') }}" wire:navigate class="hidden items-center gap-1 self-start text-sm font-semibold text-ink-soft hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:inline-flex">
            @include('shell.icon', ['name' => 'back', 'class' => 'size-4']) {{ __('projects.title') }}
        </a>

        @if ($editingName)
            <form wire:submit="saveName" class="flex flex-wrap items-start gap-2" x-data x-init="$nextTick(() => $refs.n.focus())">
                <div class="flex min-w-0 flex-1 basis-60 flex-col gap-1">
                    <label for="group-name" class="sr-only">{{ __('projects.name_label') }}</label>
                    <input id="group-name" x-ref="n" type="text" wire:model="name" maxlength="120" @keydown.escape="$wire.set('editingName', false)"
                        @class([$input, 'w-full font-display text-xl', 'border-bad' => $errors->has('name'), 'border-line' => ! $errors->has('name')]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="{{ $btn }} bg-accent text-accent-fg">{{ __('projects.save') }}</button>
                <button type="button" wire:click="$set('editingName', false)" class="{{ $btn }} text-ink-soft">{{ __('projects.cancel') }}</button>
            </form>
        @else
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="min-w-0 wrap-break-word font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ $group->name }}</h1>
                <span class="text-sm text-ink-soft">{{ __('projects.group_holds', ['list' => __('projects.lists.'.$list)]) }} · {{ $doneCount }}/{{ $total }}</span>
                <button type="button" wire:click="editName" class="text-sm font-semibold text-accent-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('projects.rename') }}</button>
                @include('shell.owner-menu', ['kind' => 'group', 'name' => $group->name])
            </div>
        @endif

        @if ($total > 0)
            <div class="h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $doneCount }}" aria-label="{{ __('projects.card.progress', ['done' => $doneCount, 'total' => $total]) }}">
                <span class="block h-full rounded-full bg-accent" style="width: {{ round(100 * $doneCount / $total) }}%"></span>
            </div>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        {{-- Step 4.3c: the drag script owns this wrapper (resources/js/step-drag.js); a group has one list, no stages. --}}
        <div x-data="stepDrag(@js(['text' => __('projects.move.js')]))" class="contents">
        <section data-lane-box class="flex flex-col gap-2.5 rounded-panel bg-surface p-4" style="border: var(--t-bw) solid var(--t-line)">
            @if ($steps->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('projects.steps_empty') }}</p>
            @else
                <p id="step-drag-hint" class="sr-only">{{ __('projects.move.keys_hint_list') }}</p>
                <ul data-lane="none" data-index="-1" data-name="" class="flex flex-col gap-1.5">
                    @foreach ($steps as $task)
                        @include('shell.step-row', ['task' => $task, 'lane' => 'none', 'next' => $loop->index < $visible, 'waiting' => false, 'first' => $loop->first, 'last' => $loop->last, 'locked' => $locked])
                    @endforeach
                </ul>
            @endif
            <form wire:submit="addStep" class="flex flex-wrap items-start gap-2">
                <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                    <label for="new-step" class="sr-only">{{ __('projects.step_title') }}</label>
                    <input id="new-step" type="text" wire:model="newStep" maxlength="255" placeholder="+ {{ __('projects.step_title') }}" autocomplete="off"
                        @class([$input, 'w-full', 'border-bad' => $errors->has('newStep'), 'border-line border-dashed' => ! $errors->has('newStep')])>
                    @error('newStep')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="addStep" class="{{ $btn }} border border-line bg-surface text-ink hover:bg-paper">{{ __('projects.add_step') }}</button>
            </form>
            {{-- Step 4.3d: pull an existing task of this group's list in. --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="{{ $picking ? 'closePicker' : 'openPicker' }}" aria-expanded="{{ $picking ? 'true' : 'false' }}" aria-controls="task-picker" data-open-picker
                    class="inline-flex min-h-11 wb:min-h-9 items-center gap-1 rounded-full border border-line px-3 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">@include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.adopt.button') }}</button>
            </div>
            @if ($picking)
                @include('shell.task-picker', ['pickable' => $this->pickable, 'total' => $this->pickableCount, 'any' => $this->hasPickable, 'search' => $pickSearch, 'stages' => null, 'groupList' => $list])
            @endif
        </section>
        </div>
        {{-- Step 4.3b: the group's note cards (no stages). --}}
        @include('shell.notes-panel', ['notes' => $this->notes, 'stages' => null, 'userTags' => $this->userTags, 'noteTag' => $noteTag, 'editingNote' => $editingNote])
        </div>
    </main>

    @if ($confirmingDelete)
        @include('shell.confirm-delete', ['kind' => 'group', 'name' => $group->name, 'impact' => $this->impact, 'error' => $deleteError])
    @endif

    @include('shell.projects-chrome', ['part' => 'tabs'])
</div>
