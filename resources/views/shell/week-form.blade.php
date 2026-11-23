{{-- The block form of the week plan editor (step 4.1), create and edit in one (CONVENTIONS 6). A popover placed by
     weekEditor.place(): beside the block, centred when it does not fit, a bottom sheet below 720 px.
     place() writes left / top / data-placement onto the section. wire:ignore.self keeps Livewire's morph from
     resetting them on every re-render (a day chip, a category): without it the popover fell to the bottom of the
     page and looked closed. The children still update; a new mode or block gets a new wire:key, so a new element.
     @param $form, $categories, $dates, $view, $btn, $chip, $short --}}
@php
    $mode = $form['mode'];
    $days = $form['days'] ?? [];
    $isTermin = ($form['kind'] ?? 'cat') === 'termin';
    $weekdayOf = fn (string $d) => \Carbon\CarbonImmutable::parse($d)->dayOfWeekIso;
    $field = 'h-10 rounded-card border border-line bg-paper px-3 text-sm text-ink focus:border-accent focus:outline-hidden aria-invalid:border-bad';
    $err = fn (string $k) => $errors->has('form.'.$k);
    $title = match ($mode) {
        'new', 'tnew' => __('week.form.new'),
        'tpl' => __('week.form.edit_plan'),
        default => __('week.form.edit'),
    };
    $sub = match ($mode) {
        'new' => __('week.form.sub_new', ['days' => implode(', ', array_map(fn ($d) => __('week.days.'.$d).' '.$short($dates[$d - 1]), $days)) ?: '–']),
        'tnew', 'tpl' => __('week.form.sub_plan'),
        'occ' => __('week.days_long.'.$weekdayOf($form['date'])).' '.$short($form['date']).' · '.__('week.form.sub_occ'),
        default => __('week.days_long.'.$weekdayOf($form['date'])).' '.$short($form['date']).' · '.__('week.form.sub_once'),
    };
@endphp
<div class="fixed inset-0 z-40 bg-black/30 wb:hidden" wire:click="closeForm" aria-hidden="true"></div>
<section x-init="place($el)" wire:ignore.self wire:key="week-form-{{ $mode }}-{{ $form['id'] ?? 'new' }}-{{ $form['date'] ?? '' }}"
    class="fixed z-50 flex w-[min(27rem,calc(100vw-24px))] flex-col gap-3 bg-surface p-4 shadow-card
        max-wb:inset-x-0 max-wb:bottom-0 max-wb:w-full max-wb:max-h-[88dvh] max-wb:overflow-y-auto max-wb:rounded-t-panel max-wb:pb-[max(18px,env(safe-area-inset-bottom))]
        wb:rounded-panel data-[placement=center]:left-1/2 data-[placement=center]:top-1/2 data-[placement=center]:max-h-[calc(100dvh-24px)] data-[placement=center]:-translate-x-1/2 data-[placement=center]:-translate-y-1/2 data-[placement=center]:overflow-y-auto"
    style="border: var(--t-bw) solid var(--t-line)" role="dialog" aria-modal="false" aria-labelledby="week-form-title" data-week-form="{{ $mode }}">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h2 id="week-form-title" class="font-display text-lg text-ink" style="font-weight: var(--t-dw)">{{ $title }}</h2>
            <p class="font-mono text-xs text-ink-faint">{{ $sub }}</p>
        </div>
        <button type="button" wire:click="closeForm" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-soft hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('week.cancel') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-5'])</button>
    </div>

    @if ($mode !== 'occ')
        <div class="flex gap-1.5" role="radiogroup" aria-label="{{ __('week.form.kind') }}">
            @foreach (['cat' => __('week.form.kind_category'), 'termin' => __('week.form.kind_termin')] as $kind => $label)
                @php($on = ($form['kind'] ?? 'cat') === $kind)
                <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('form.kind', '{{ $kind }}')"
                    class="{{ $chip }} {{ $on ? 'bg-ink text-surface' : 'text-ink-soft hover:text-ink' }}" style="border: var(--t-bw) solid {{ $on ? 'var(--t-ink)' : 'var(--t-line)' }}">{{ $label }}</button>
            @endforeach
        </div>
    @endif

    @if ($isTermin)
        <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
            {{ __('week.form.name') }}
            <input type="text" wire:model="form.title" maxlength="255" data-autofocus placeholder="{{ __('week.form.name_placeholder') }}" class="{{ $field }}" @if ($err('title')) aria-invalid="true" @endif @disabled($mode === 'occ')>
        </label>
        @error('form.title')<p class="-mt-2 text-sm text-bad">{{ $message }}</p>@enderror
    @else
        <div class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink-soft">{{ __('week.form.category') }}</span>
            @if ($categories->isEmpty())
                <p class="text-sm text-ink-soft">{{ __('week.form.no_categories') }} <a href="{{ route('shell.settings') }}#kategorien" wire:navigate class="font-semibold text-accent-ink underline underline-offset-4">{{ __('week.form.to_settings') }}</a></p>
            @else
                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('week.form.category') }}">
                    @foreach ($categories as $category)
                        @php($on = (int) ($form['categoryId'] ?? 0) === $category->id)
                        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('form.categoryId', {{ $category->id }})" @if ($loop->first) data-autofocus @endif
                            class="{{ $chip }} {{ $on ? 'text-ink' : 'text-ink-soft hover:text-ink' }}"
                            style="border: var(--t-bw) solid {{ $on ? 'var(--color-'.$category->color.')' : 'var(--t-line)' }}; background: {{ $on ? 'color-mix(in srgb, var(--color-'.$category->color.') 16%, var(--t-surface))' : 'var(--t-surface)' }}">
                            <span class="size-2.5 rounded-full" style="background: var(--color-{{ $category->color }})"></span>{{ $category->name }}
                            @if ($category->pomodoro_enabled)<span class="sr-only">({{ __('week.categories.pomodoro') }})</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif
            @error('form.categoryId')<p class="text-sm text-bad">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-2.5">
        @if ($mode === 'occ')
            <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
                {{ __('week.form.day') }}
                <select wire:model="form.newDate" class="{{ $field }} pr-8">
                    @foreach ($dates as $d)
                        <option value="{{ $d }}">{{ __('week.days.'.$weekdayOf($d)) }} {{ $short($d) }}</option>
                    @endforeach
                </select>
            </label>
        @elseif ($mode === 'once')
            <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
                {{ __('week.form.date') }}
                <input type="date" wire:model="form.newDate" class="{{ $field }}" @if ($err('newDate')) aria-invalid="true" @endif>
            </label>
        @endif
        <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
            {{ __('week.form.from') }}
            <input type="time" step="300" wire:model="form.start" class="{{ $field }} w-28 font-mono" @if ($err('start')) aria-invalid="true" @endif>
        </label>
        <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
            {{ __('week.form.to') }}
            <input type="time" step="300" wire:model="form.end" class="{{ $field }} w-28 font-mono" @if ($err('end')) aria-invalid="true" @endif>
        </label>
    </div>
    @foreach (['newDate', 'start', 'end'] as $k)
        @error('form.'.$k)<p class="-mt-2 text-sm text-bad">{{ $message }}</p>@enderror
    @endforeach

    @if (in_array($mode, ['new', 'tnew', 'tpl'], true))
        <div class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink-soft">{{ __('week.form.days') }}</span>
            <div class="flex flex-wrap gap-1" role="group" aria-label="{{ __('week.form.days') }}">
                @foreach (range(1, 7) as $d)
                    @php($on = in_array($d, $days, true))
                    <button type="button" wire:click="toggleDay({{ $d }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                        class="{{ $chip }} min-w-10 justify-center {{ $on ? 'bg-ink text-surface' : 'text-ink-soft hover:text-ink' }}" style="border: var(--t-bw) solid {{ $on ? 'var(--t-ink)' : 'var(--t-line)' }}">
                        {{ __('week.days.'.$d) }}@if ($mode === 'new')<span class="font-mono text-[10px] opacity-75">{{ $short($dates[$d - 1]) }}</span>@endif
                    </button>
                @endforeach
            </div>
            @error('form.days')<p class="text-sm text-bad">{{ $message }}</p>@enderror
        </div>
    @endif

    @if ($mode === 'occ' && ($form['changed'] ?? false))
        <p class="flex flex-wrap items-center gap-x-2 text-sm text-warn">
            {{ __('week.form.changed_note') }}
            <button type="button" wire:click="restoreOccurrence" class="font-semibold text-accent-ink underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('week.form.restore') }}</button>
        </p>
    @endif

    {{-- The footer: the scope question is two buttons, never a default (R4). --}}
    <div class="flex flex-wrap items-center gap-2 pt-3" style="border-top: var(--t-bw) solid var(--t-line)" wire:loading.class="opacity-60" wire:target="save,remove">
        @switch($mode)
            @case('new')
                @php($one = count($days) === 1)
                <span class="w-full text-xs font-medium text-ink-soft">{{ __('week.form.create_for') }}</span>
                <button type="button" wire:click="save('this')" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110" data-scope="this">
                    {{ $one ? __('week.form.only_date', ['day' => __('week.days.'.$days[0]), 'date' => $short($dates[$days[0] - 1])]) : __('week.form.only_week') }}
                </button>
                <button type="button" wire:click="save('every')" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-deep text-on-deep hover:brightness-110" data-scope="every">
                    {{ $one ? __('week.form.every_day', ['day' => __('week.days_long.'.$days[0])]) : __('week.form.every_week') }}
                </button>
                @break
            @case('occ')
                @php($day = __('week.days_long.'.$weekdayOf($form['date'])))
                @php($newDay = __('week.days_long.'.$weekdayOf($form['newDate'])))
                <span class="w-full text-xs font-medium text-ink-soft">{{ __('week.form.save_for') }}</span>
                <button type="button" wire:click="save('this')" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110" data-scope="this">
                    {{ __('week.form.only_this', ['date' => $short($form['date'])]) }}
                </button>
                <button type="button" wire:click="save('every')" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-deep text-on-deep hover:brightness-110" data-scope="every">
                    {{ $form['newDate'] !== $form['date'] ? __('week.form.every_moved', ['day' => $newDay]) : __('week.form.every_day', ['day' => $day]) }}
                </button>
                <div class="flex w-full flex-wrap gap-x-3 gap-y-1 pt-1">
                    <button type="button" wire:click="remove('this')" wire:loading.attr="disabled" wire:target="save,remove" class="text-sm font-semibold text-bad underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('week.form.remove_this') }}</button>
                    <button type="button" wire:click="remove('every')" wire:loading.attr="disabled" wire:target="save,remove" class="text-sm font-semibold text-bad underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('week.form.remove_every', ['day' => $day]) }}</button>
                </div>
                @break
            @case('once')
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">{{ __('week.form.save') }}</button>
                <button type="button" wire:click="remove" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} text-bad hover:bg-paper">{{ __('week.form.remove') }}</button>
                @break
            @case('tnew')
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">{{ __('week.form.add_plan') }}</button>
                @break
            @case('tpl')
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">{{ __('week.form.save') }}</button>
                <button type="button" wire:click="remove" wire:loading.attr="disabled" wire:target="save,remove" class="{{ $btn }} text-bad hover:bg-paper">{{ __('week.form.remove_plan') }}</button>
                @break
        @endswitch
        <button type="button" wire:click="closeForm" class="{{ $btn }} ml-auto text-ink-soft hover:text-ink">{{ __('week.cancel') }}</button>
    </div>
</section>
