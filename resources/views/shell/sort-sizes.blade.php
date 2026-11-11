{{-- Size chips of the sort card (desktop) and the phone deck. Expects $size from the component. --}}
<div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('sort.size') }}">
    @foreach (\App\Planning\Size::cases() as $s)
        @php($on = ($size ?? 'short') === $s->value)
        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('size', '{{ $s->value }}')"
            @class(['rounded-full border px-3 py-1.5 text-[13px] transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>
            <b class="font-semibold">{{ __('shell.sizes.'.$s->value) }}</b> · {{ __('sort.size_hints.'.$s->value) }}
        </button>
    @endforeach
</div>
@error('size') <p class="mt-1 text-xs font-semibold text-bad">{{ $message }}</p> @enderror
