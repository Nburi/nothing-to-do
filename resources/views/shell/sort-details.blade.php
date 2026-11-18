{{-- The folded fields of the sort: dates, area, first step. Shared by the desktop card and the
     phone deck. @param bool $isLater (Später chosen: no hard date), bool $deck (list not chosen yet) --}}
@php($today = auth()->user()->localToday()->toDateString())
<div class="flex flex-col gap-3 border-t border-dashed border-line pt-3">
    @unless ($isLater)
        <div class="grid gap-3 wb:grid-cols-2">
            <label class="flex min-w-0 flex-col gap-1">
                <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.hard') }}</span>
                <input type="date" wire:model.live="hardDeadline" min="{{ $today }}"
                    @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('hard_deadline'), 'border-line' => ! $errors->has('hard_deadline')])
                    style="border-width: var(--t-bw)" @error('hard_deadline') aria-invalid="true" @enderror>
                @error('hard_deadline')
                    <span class="text-xs font-semibold text-bad">{{ $message }}</span>
                @else
                    <span class="text-xs text-ink-faint">{{ ($deck ?? false) ? __('sort.hard_hint_deck') : __('sort.hard_hint') }}</span>
                @enderror
            </label>
            <label class="flex min-w-0 flex-col gap-1">
                <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.soft') }}</span>
                <input type="date" wire:model.live="softDeadline" min="{{ $today }}"
                    @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('soft_deadline'), 'border-line' => ! $errors->has('soft_deadline')])
                    style="border-width: var(--t-bw)" @error('soft_deadline') aria-invalid="true" @enderror>
                @error('soft_deadline')
                    <span class="text-xs font-semibold text-bad">{{ $message }}</span>
                @else
                    @if ($hardDeadline && $softDeadline && $softDeadline > $hardDeadline)
                        <span class="text-xs font-semibold text-warn">{{ __('sort.soft_after_hard') }}</span>
                    @else
                        <span class="text-xs text-ink-faint">{{ __('sort.soft_hint') }}</span>
                    @endif
                @enderror
            </label>
        </div>
    @else
        <label class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.soft') }}</span>
            <input type="date" wire:model.live="softDeadline" min="{{ $today }}" class="h-10 min-w-0 rounded-card border-line bg-paper px-2.5 text-sm text-ink focus:outline-hidden focus:ring-2 focus:ring-accent" style="border-width: var(--t-bw)">
            @error('soft_deadline') <span class="text-xs font-semibold text-bad">{{ $message }}</span> @enderror
        </label>
    @endunless

    @if ($this->areas->isNotEmpty())
        <div>
            <span class="mb-1.5 block font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('sort.area') }}</span>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($this->areas as $area)
                    @php($on = $areaId === $area->id)
                    <button type="button" wire:click="$set('areaId', {{ $area->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                        @class(['inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>
                        <span class="size-2 rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($area->color) }}"></span>{{ $area->name }}
                    </button>
                @endforeach
                <button type="button" wire:click="$set('areaId', null)" aria-pressed="{{ $areaId === null ? 'true' : 'false' }}"
                    @class(['rounded-full border px-3 py-1.5 text-[13px] font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $areaId === null, 'border-line bg-surface text-ink-soft hover:text-ink' => $areaId !== null])>{{ __('sort.no_area') }}</button>
            </div>
            @error('area_id') <p class="mt-1 text-xs font-semibold text-bad">{{ $message }}</p> @enderror
        </div>
    @endif

    <label class="flex min-w-0 flex-col gap-1">
        <span class="font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ $isLater ? __('sort.first_step_later') : __('sort.first_step') }}</span>
        <input type="text" wire:model="firstStep" maxlength="255"
            placeholder="{{ $isLater ? __('sort.first_step_placeholder_later') : __('sort.first_step_placeholder') }}"
            @class(['h-10 min-w-0 rounded-card bg-paper px-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('first_step'), 'border-line' => ! $errors->has('first_step')])
            style="border-width: var(--t-bw)">
        @error('first_step') <span class="text-xs font-semibold text-bad">{{ $message }}</span> @enderror
    </label>
</div>
