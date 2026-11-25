{{-- Step 4.3: "Projekte". Projects and groups with something open as cards (next two each), the
     rest as "ruht gerade" chips. Both layouts in one render, like the Ideen page. --}}
@php
    $bundles = $this->bundles;
    $locked = $this->locked;
    $active = $bundles->filter(fn ($b) => $b->open > 0);
    $projects = $active->filter(fn ($b) => $b->isProject());
    $groups = $active->reject(fn ($b) => $b->isProject());
    $resting = $bundles->filter(fn ($b) => $b->open === 0);
    $btn = 'inline-flex min-h-11 wb:min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50';
@endphp
<div id="content" class="min-h-dvh pb-[calc(88px+env(safe-area-inset-bottom))] wb:pb-0">
    @include('shell.projects-chrome', ['part' => 'head'])

    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status">
            @if ($notice)
            <p class="pointer-events-auto flex max-w-md items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card">
                <span class="min-w-0 wrap-break-word">{{ $notice }}</span>
                <button type="button" wire:click="$set('notice', null)" class="shrink-0 -my-2 -mr-2.5 grid size-11 place-items-center rounded-full opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="{{ __('shell.close') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
            </p>
            @endif
    </div>

    <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 pt-2 pb-10 wb:px-6 wb:pt-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ __('projects.title') }}</h1>
                <p class="text-sm text-ink-soft">{{ __('projects.intro') }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="open('project')" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">@include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.new_project') }}</button>
                <button type="button" wire:click="open('group')" class="{{ $btn }} border border-line bg-surface text-ink hover:bg-paper">@include('shell.icon', ['name' => 'plus', 'class' => 'size-4']) {{ __('projects.new_group') }}</button>
            </div>
        </div>

        @if ($creating)
            <form wire:submit="create" class="flex flex-col gap-3 rounded-panel bg-surface p-4" style="border: var(--t-bw) solid var(--t-line)" x-data x-init="$nextTick(() => $refs.name.focus())">
                <div class="flex flex-col gap-1">
                    <label for="new-name" class="text-sm font-semibold text-ink">{{ $creating === 'group' ? __('projects.new_group_label') : __('projects.new_project_label') }}</label>
                    <input id="new-name" x-ref="name" type="text" wire:model="newName" maxlength="120" autocomplete="off" @keydown.escape="$wire.open('')"
                        @class(['min-h-11 rounded-card border bg-paper px-3 text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-bad' => $errors->has('newName'), 'border-line' => ! $errors->has('newName')])
                        aria-invalid="{{ $errors->has('newName') ? 'true' : 'false' }}" @error('newName') aria-describedby="new-name-error" @enderror>
                    @error('newName')<p data-field-error role="alert" id="new-name-error" class="text-sm text-bad">{{ $message }}</p>@enderror
                </div>
                @if ($creating === 'group')
                    <fieldset class="flex flex-wrap items-center gap-2">
                        <legend class="mb-1 text-sm font-semibold text-ink">{{ __('projects.group_list') }}</legend>
                        @foreach (\App\Models\TaskGroup::LISTS as $list)
                            <label class="inline-flex min-h-11 wb:min-h-10 cursor-pointer items-center gap-2 rounded-full border px-3 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink border-line text-ink-soft">
                                <input type="radio" wire:model="newList" value="{{ $list }}" class="sr-only"> {{ __('projects.lists.'.$list) }}
                            </label>
                        @endforeach
                    </fieldset>
                @endif
                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="create" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">
                        <span wire:loading.remove wire:target="create">{{ __('projects.create') }}</span>
                        <span wire:loading wire:target="create">…</span>
                    </button>
                    <button type="button" wire:click="open('')" class="{{ $btn }} text-ink-soft hover:text-ink">{{ __('projects.cancel') }}</button>
                </div>
            </form>
        @endif

        @if ($bundles->isEmpty() && ! $creating)
            <div class="flex flex-col items-start gap-2 rounded-panel border border-dashed border-line p-6">
                <p class="font-semibold text-ink">{{ __('projects.none_yet') }}</p>
                <p class="text-sm text-ink-soft">{{ __('projects.none_yet_hint') }}</p>
            </div>
        @endif

        @foreach ([['projects', $projects], ['groups', $groups]] as [$label, $items])
            @if ($items->isNotEmpty())
                <section class="flex flex-col gap-3" aria-labelledby="sec-{{ $label }}">
                    <h2 id="sec-{{ $label }}" class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('projects.'.$label) }}</h2>
                    <div class="grid items-start gap-3 wb:grid-cols-2 lg:grid-cols-3">
                        @foreach ($items as $bundle)
                            @include('shell.project-card', ['bundle' => $bundle, 'locked' => $locked])
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if ($resting->isNotEmpty())
            <section class="flex flex-col gap-2" aria-labelledby="sec-resting">
                <h2 id="sec-resting" class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('projects.resting') }} · {{ $resting->count() }}</h2>
                <p class="text-xs text-ink-faint">{{ __('projects.resting_hint') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($resting as $bundle)
                        <a wire:key="rest-{{ $bundle->kind }}-{{ $bundle->id }}" href="{{ $bundle->url() }}" wire:navigate
                            class="inline-flex min-h-11 wb:min-h-9 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-sm text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            @include('shell.icon', ['name' => $bundle->isProject() ? 'project' : 'group', 'class' => 'size-3.5'])
                            {{ $bundle->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    @include('shell.projects-chrome', ['part' => 'tabs'])
</div>
