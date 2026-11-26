<div class="flex flex-col gap-4">
    <div>
        <h3 class="mb-1 text-base font-medium text-ink">{{ __('onboarding.language.title') }}</h3>
        <p class="text-sm leading-relaxed text-ink-soft">{{ __('onboarding.language.intro') }}</p>
    </div>

    <div class="inline-flex w-fit max-w-full rounded-full border border-line bg-paper p-0.5" role="radiogroup" aria-label="{{ __('onboarding.language.title') }}">
        @foreach ($options as $option)
            <button
                type="button"
                role="radio"
                lang="{{ $option }}"
                aria-checked="{{ $option === $locale ? 'true' : 'false' }}"
                wire:key="locale-{{ $option }}"
                wire:click="select('{{ $option }}')"
                wire:loading.attr="disabled"
                @class([
                    'min-h-11 rounded-full px-4 py-1.5 text-sm font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent',
                    'bg-surface text-ink shadow-card' => $option === $locale,
                    'text-ink-soft hover:text-ink' => $option !== $locale,
                ])
            >{{ __("onboarding.language.names.{$option}") }}</button>
        @endforeach
    </div>
    <span wire:loading wire:target="select" class="text-xs text-ink-faint" role="status">{{ __('onboarding.language.applying') }}</span>
    @error('locale')
        <p data-field-error role="alert" class="text-xs text-signal">{{ $message }}</p>
    @enderror
</div>
