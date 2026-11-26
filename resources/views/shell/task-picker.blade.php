{{-- Step 4.3d: pull an existing board task into a project (optionally straight into a stage) or into a
     group (only tasks of its own list). A search field narrows the list; the first 30 are shown and the
     rest is reached by searching. The task keeps its list; "Rückgängig" in the toast takes it out again.
     States: loading (search), nothing to add at all, nothing found, a short list.
     @param Collection $pickable, int $total, bool $any, string $search, ?Collection $stages (project),
            ?string $groupList (group) --}}
@php
    $input = 'min-h-11 wb:min-h-10 rounded-card border border-line bg-paper px-3 text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
@endphp
<section id="task-picker" class="flex flex-col gap-3 rounded-panel bg-surface p-4" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="picker-title" data-task-picker>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h2 id="picker-title" class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">{{ __('projects.adopt.title') }}</h2>
            <p class="mt-0.5 text-xs text-ink-soft">{{ $groupList ? __('projects.adopt.hint_group', ['list' => __('projects.lists.'.$groupList)]) : __('projects.adopt.hint_project') }}</p>
        </div>
        <button type="button" wire:click="closePicker" class="shrink-0 rounded-full p-2 text-ink-soft hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('projects.adopt.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
    </div>

    @if (! $any)
        <p class="rounded-card border border-dashed border-line px-3 py-3 text-center text-sm text-ink-faint" data-picker-empty>
            {{ $groupList ? __('projects.adopt.empty_group', ['list' => __('projects.lists.'.$groupList)]) : __('projects.adopt.empty') }}
        </p>
    @else
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                <label for="pick-search" class="text-xs font-semibold text-ink-soft">{{ __('projects.adopt.search') }}</label>
                <input id="pick-search" type="search" wire:model.live.debounce.300ms="pickSearch" maxlength="100" autocomplete="off" class="{{ $input }} w-full">
            </div>
            @if ($stages !== null && $stages->isNotEmpty())
                <div class="flex flex-col gap-1">
                    <label for="pick-stage" class="text-xs font-semibold text-ink-soft">{{ __('projects.adopt.stage') }}</label>
                    <select id="pick-stage" wire:model="pickStage" class="{{ $input }} pr-8">
                        <option value="none">{{ __('projects.no_stage') }}</option>
                        @foreach ($stages as $i => $s)
                            <option value="stage-{{ $s->id }}">{{ ($i + 1).' · '.\Illuminate\Support\Str::limit($s->name, 40) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <p wire:loading wire:target="pickSearch" class="text-xs text-ink-faint" role="status">{{ __('projects.adopt.loading') }}</p>

        @if ($pickable->isEmpty())
            <p class="rounded-card border border-dashed border-line px-3 py-3 text-center text-sm text-ink-faint" data-picker-nomatch>{{ __('projects.adopt.no_match', ['search' => $search]) }}</p>
        @else
            <ul class="flex max-h-96 flex-col gap-1.5 overflow-y-auto" wire:loading.class="opacity-60" wire:target="pickSearch">
                @foreach ($pickable as $task)
                    @php $meta = \App\Shell\TaskFace::meta($task); @endphp
                    <li wire:key="pick-{{ $task->id }}" data-pick="{{ $task->id }}" class="flex items-center gap-2 rounded-card bg-paper px-3 py-2 text-sm" style="border: var(--t-bw) solid var(--t-line)">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium leading-snug wrap-break-word text-ink">{{ $task->title }}</p>
                            <p class="mt-0.5 text-xs text-ink-faint">{{ __('projects.lists.'.$task->list) }}@if ($meta) · {{ $meta }}@endif</p>
                        </div>
                        <button type="button" wire:click="adoptTask({{ $task->id }})" wire:loading.attr="disabled" wire:target="adoptTask({{ $task->id }})"
                            aria-label="{{ __('projects.adopt.add_title', ['title' => $task->title]) }}"
                            class="inline-flex min-h-11 wb:min-h-10 shrink-0 items-center gap-1 rounded-card border border-line bg-surface px-3 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50">
                            @include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.adopt.add') }}
                        </button>
                    </li>
                @endforeach
            </ul>
            @if ($total > $pickable->count())
                <p class="text-xs text-ink-faint">{{ __('projects.adopt.more', ['count' => $total - $pickable->count()]) }}</p>
            @endif
        @endif
    @endif
</section>
