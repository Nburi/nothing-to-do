{{-- The palette swatches (ScheduleEvent::EVENT_COLORS, the five keys the categories use: tokens, never free hex, so
     every theme, light and dark, supplies the actual colour). One radio group, one tap per colour.
     @param string $model     Livewire property the choice is written to, e.g. "form.color"
     @param ?string $value    the chosen key, null = "Standard" (only when $standard)
     @param bool $standard    offer a first swatch for "no colour chosen" (the dark Termin block)
     @param string $label     accessible name of the group
     @param bool $disabled    shown but not changeable (read-only fields of the form look the same) --}}
@props(['model', 'value' => null, 'standard' => false, 'label', 'disabled' => false])
@php
    $options = ($standard ? ['' => 'var(--t-deep)'] : []) + collect(\App\Models\ScheduleEvent::EVENT_COLORS)->mapWithKeys(fn ($c) => [$c => 'var(--color-'.$c.')'])->all();
@endphp
<div {{ $attributes->class('flex flex-wrap items-center gap-1') }} role="radiogroup" aria-label="{{ $label }}" data-color-picker>
    @foreach ($options as $key => $tone)
        @php($on = (string) $value === (string) $key)
        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" @disabled($disabled)
            wire:click="$set('{{ $model }}', {{ $key === '' ? 'null' : "'".$key."'" }})" data-color="{{ $key === '' ? 'standard' : $key }}"
            class="grid size-11 wb:size-9 place-items-center rounded-full focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50"
            aria-label="{{ $key === '' ? __('week.colors.standard') : __('week.colors.'.$key) }}" title="{{ $key === '' ? __('week.colors.standard') : __('week.colors.'.$key) }}">
            {{-- "Standard" is a hollow ring, so it never reads as the solid ink swatch next to it. --}}
            <span class="size-5 rounded-full" style="{{ $key === '' ? 'background: transparent; box-shadow: inset 0 0 0 2px var(--t-deep)' : 'background: '.$tone.'; box-shadow: 0 0 0 1px var(--t-line)' }}{{ $on ? ($key === '' ? ', 0 0 0 2px var(--t-surface), 0 0 0 4px var(--t-ink)' : '; box-shadow: 0 0 0 2px var(--t-surface), 0 0 0 4px var(--t-ink)') : '' }}"></span>
        </button>
    @endforeach
</div>
