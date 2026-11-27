{{-- Categories of the week plan (step 4.1), a section of the shell settings. Rename on blur / Enter, colour from
     five swatches, the pomodoro switch, linked areas with "+ Bereich", delete with "Rückgängig". --}}
@php
    $categories = $this->categories;
    $areas = $this->areas;
    $colors = \App\Models\ScheduleEvent::EVENT_COLORS;
    $btn = 'inline-flex min-h-11 wb:min-h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60';
    $projects = $this->projects;
    $groups = $this->groups;
    $chip = 'inline-flex min-h-11 wb:min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
@endphp
<div class="flex flex-col gap-4" data-card="categories">
    <div>
        <h2 class="text-base font-semibold text-ink">{{ __('week.categories.title') }}</h2>
        <p class="mt-0.5 max-w-[62ch] text-sm text-ink-soft">{{ __('week.categories.intro') }}</p>
    </div>

    @if ($notice)
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-card bg-deep px-3 py-2 text-sm font-medium text-on-deep" role="status">
            <span class="min-w-0 flex-1 wrap-break-word">{{ $notice }}</span>
            @if ($undoId)
                <button type="button" wire:click="undo" wire:loading.attr="disabled" class="font-semibold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('week.undo') }}</button>
            @endif
            <button type="button" wire:click="dismiss" class="-my-2 -mr-2.5 grid size-11 place-items-center rounded-full opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="{{ __('shell.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
        </p>
    @endif

    @if ($categories->isEmpty())
        <p class="rounded-card border border-dashed border-line px-4 py-3 text-sm text-ink-soft" data-categories-empty>{{ __('week.categories.empty') }}</p>
    @endif

    <ul class="flex flex-col gap-2.5">
        @foreach ($categories as $category)
            @php($linked = $category->areas->pluck('id')->all())
            <li wire:key="cat-{{ $category->id }}" class="flex flex-col gap-3 rounded-card bg-paper p-3" style="border: var(--t-bw) solid var(--t-line)" data-category="{{ $category->id }}">
                <div class="flex items-center gap-2" x-data="{ open: false }">
                    <div class="relative">
                        <button type="button" @click="open = !open" @keydown.escape="open = false" :aria-expanded="open"
                            class="grid size-11 wb:size-8 place-items-center rounded-full focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                            aria-label="{{ __('week.categories.color', ['name' => $category->name]) }}">
                            <span class="size-5 rounded-full" style="background: var(--color-{{ $category->color }}); box-shadow: 0 0 0 2px var(--t-surface), 0 0 0 3px var(--t-line)"></span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
                            class="absolute left-0 top-10 z-20 flex gap-1.5 rounded-card bg-surface p-2 shadow-card" style="border: var(--t-bw) solid var(--t-line)" role="group" aria-label="{{ __('week.categories.colors') }}">
                            @foreach ($colors as $color)
                                <button type="button" wire:click="setColor({{ $category->id }}, '{{ $color }}')" @click="open = false"
                                    class="grid size-11 wb:size-8 place-items-center rounded-full focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                                    aria-label="{{ __('week.colors.'.$color) }}" aria-pressed="{{ $category->color === $color ? 'true' : 'false' }}">
                                    <span class="size-5 rounded-full" style="background: var(--color-{{ $color }}); {{ $category->color === $color ? 'box-shadow: 0 0 0 2px var(--t-surface), 0 0 0 4px var(--t-ink)' : '' }}"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <input type="text" value="{{ $category->name }}" maxlength="255"
                        x-data x-on:keydown.enter.prevent="$el.blur()" x-on:blur="if ($el.value.trim() !== @js($category->name)) $wire.rename({{ $category->id }}, $el.value)"
                        class="min-h-11 wb:min-h-0 min-w-0 flex-1 rounded-card border border-transparent bg-transparent px-2 py-1 text-[15px] font-semibold text-ink hover:border-line focus:border-line focus:bg-surface focus:outline-hidden"
                        aria-label="{{ __('week.categories.name') }}" @error('rename.'.$category->id) aria-invalid="true" @enderror>
                    <button type="button" wire:click="delete({{ $category->id }})" wire:loading.attr="disabled" wire:target="delete({{ $category->id }})"
                        class="grid size-11 wb:size-9 shrink-0 place-items-center rounded-full text-ink-faint hover:bg-surface hover:text-bad focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                        aria-label="{{ __('week.categories.delete', ['name' => $category->name]) }}">
                        @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                    </button>
                </div>
                @error('rename.'.$category->id)<p data-field-error role="alert" class="-mt-2 text-sm text-bad">{{ $message }}</p>@enderror

                <button type="button" role="switch" aria-checked="{{ $category->pomodoro_enabled ? 'true' : 'false' }}" wire:click="togglePomodoro({{ $category->id }})"
                    class="inline-flex items-center gap-2.5 self-start rounded-card text-sm text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    <span class="relative h-5 w-9 rounded-full transition {{ $category->pomodoro_enabled ? 'bg-accent' : 'bg-line' }}">
                        <span class="absolute top-0.5 size-4 rounded-full bg-surface shadow-sm transition {{ $category->pomodoro_enabled ? 'left-[18px]' : 'left-0.5' }}"></span>
                    </span>
                    <span><span class="font-semibold text-ink">{{ __('week.categories.pomodoro') }}</span> · {{ $category->pomodoro_enabled ? __('week.categories.pomodoro_on') : __('week.categories.pomodoro_off') }}</span>
                </button>

                @if ($category->pomodoro_enabled)
                    <div class="flex flex-col gap-1.5">
                        <span class="text-xs font-medium text-ink-faint">{{ __('week.categories.suggest_from') }}</span>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach ($areas as $area)
                                @php($on = in_array($area->id, $linked, true))
                                <button type="button" wire:click="toggleArea({{ $category->id }}, {{ $area->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    class="{{ $chip }} {{ $on ? 'text-accent-ink' : 'text-ink-soft hover:text-ink' }}"
                                    style="border: var(--t-bw) solid {{ $on ? 'var(--t-accent)' : 'var(--t-line)' }}; background: {{ $on ? 'color-mix(in srgb, var(--t-accent) 10%, var(--t-surface))' : 'var(--t-surface)' }}">
                                    <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($area->color) }}"></span>{{ $area->name }}
                                </button>
                            @endforeach
                            @if ($areaFor === $category->id)
                                <form wire:submit="addArea" class="inline-flex items-center gap-1" novalidate>
                                    <input type="text" wire:model="areaName" maxlength="60" x-init="$el.focus()" @keydown.escape="$wire.closeArea()"
                                        placeholder="{{ __('week.categories.area_placeholder') }}" aria-label="{{ __('week.categories.area_name') }}"
                                        class="h-8 w-40 rounded-full border border-line bg-surface px-3 text-xs text-ink focus:border-accent focus:outline-hidden" @error('areaName') aria-invalid="true" @enderror>
                                    <button type="submit" class="{{ $chip }} bg-accent text-accent-fg" wire:loading.attr="disabled" wire:target="addArea">{{ __('week.categories.area_add') }}</button>
                                    <button type="button" wire:click="closeArea" class="{{ $chip }} text-ink-soft" aria-label="{{ __('week.cancel') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-3.5'])</button>
                                </form>
                            @else
                                <button type="button" wire:click="openArea({{ $category->id }})" class="{{ $chip }} text-ink-soft hover:text-ink" style="border: var(--t-bw) dashed var(--t-line)">
                                    @include('shell.icon', ['name' => 'plus', 'class' => 'size-3.5']){{ __('week.categories.new_area') }}
                                </button>
                            @endif
                        </div>
                        @if ($areaFor === $category->id)
                            @error('areaName')<p data-field-error role="alert" class="text-sm text-bad">{{ $message }}</p>@enderror
                        @endif
                        @if ($linked === [])
                            <span class="text-xs text-ink-faint">{{ __('week.categories.no_areas') }}</span>
                        @endif
                    </div>

                    {{-- Step 5.4 (category links): "suggest this project's or group's next steps in this block". --}}
                    @if ($projects->isNotEmpty() || $groups->isNotEmpty() || $category->stepsLinkValue() !== '')
                        <div class="flex flex-col gap-1.5" data-category-link>
                            <label for="link-{{ $category->id }}" class="text-xs font-medium text-ink-faint">{{ __('week.categories.link_label') }}</label>
                            <select id="link-{{ $category->id }}" wire:change="setLink({{ $category->id }}, $event.target.value)" wire:loading.attr="disabled" wire:target="setLink"
                                class="min-h-11 wb:min-h-9 w-full max-w-sm rounded-card border border-line bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                                <option value="" @selected($category->stepsLinkValue() === '')>{{ __('week.categories.link_none') }}</option>
                                @if ($projects->isNotEmpty())
                                    <optgroup label="{{ __('week.categories.link_projects') }}">
                                        @foreach ($projects as $project)
                                            <option value="project:{{ $project->id }}" @selected($category->stepsLinkValue() === 'project:'.$project->id)>{{ $project->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($groups->isNotEmpty())
                                    <optgroup label="{{ __('week.categories.link_groups') }}">
                                        @foreach ($groups as $group)
                                            <option value="group:{{ $group->id }}" @selected($category->stepsLinkValue() === 'group:'.$group->id)>{{ $group->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <span class="text-xs text-ink-faint">{{ $category->stepsLinkValue() !== '' ? __('week.categories.link_hint') : __('week.categories.link_hint_off') }}</span>
                            @if ($category->task_source === 'text' && filled($category->linked_text))
                                <span class="text-xs text-ink-faint" data-category-link-text>{{ __('week.categories.link_text', ['text' => $category->linked_text]) }}</span>
                            @endif
                        </div>
                    @endif
                @endif
            </li>
        @endforeach
    </ul>

    <form wire:submit="add" class="flex flex-wrap items-end gap-2" novalidate data-category-add>
        <label class="flex min-w-0 flex-1 basis-52 flex-col gap-1 text-xs font-medium text-ink-soft">
            {{ __('week.categories.new') }}
            <input type="text" wire:model="newName" maxlength="255" placeholder="{{ __('week.categories.new_placeholder') }}"
                class="h-11 wb:h-10 rounded-card border border-line bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-hidden" @error('newName') aria-invalid="true" @enderror>
        </label>
        <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ __('week.categories.colors') }}">
            @foreach ($colors as $color)
                <button type="button" wire:click="$set('newColor', '{{ $color }}')" role="radio" aria-checked="{{ $newColor === $color ? 'true' : 'false' }}" aria-label="{{ __('week.colors.'.$color) }}"
                    class="grid size-11 wb:size-8 place-items-center rounded-full focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    <span class="size-5 rounded-full" style="background: var(--color-{{ $color }}); {{ $newColor === $color ? 'box-shadow: 0 0 0 2px var(--t-surface), 0 0 0 4px var(--t-ink)' : '' }}"></span>
                </button>
            @endforeach
        </div>
        <label class="inline-flex h-11 wb:h-10 items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" wire:model="newPomodoro" class="size-5 accent-accent wb:size-4">{{ __('week.categories.pomodoro') }}
        </label>
        <button type="submit" class="{{ $btn }} h-11 wb:h-10 bg-accent text-accent-fg hover:brightness-110" wire:loading.attr="disabled" wire:target="add">
            @include('shell.icon', ['name' => 'plus', 'class' => 'size-4']){{ __('week.categories.add') }}
        </button>
        @error('newName')<p data-field-error role="alert" class="w-full text-sm text-bad">{{ $message }}</p>@enderror
    </form>
</div>
