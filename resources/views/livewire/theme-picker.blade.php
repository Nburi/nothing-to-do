<div class="flex flex-col gap-5">
    <div>
        <h3 class="mb-1 text-base font-medium text-ink">{{ __('themes.title') }}</h3>
        <p class="text-sm leading-relaxed text-ink-soft">{{ __('themes.intro') }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" role="radiogroup" aria-label="{{ __('themes.title') }}">
        @foreach ($themes as $item)
            @php($on = $item['id'] === $theme)
            <button
                type="button"
                role="radio"
                aria-checked="{{ $on ? 'true' : 'false' }}"
                wire:key="theme-{{ $item['id'] }}"
                wire:click="selectTheme('{{ $item['id'] }}')"
                wire:loading.attr="disabled"
                @class([
                    'group flex min-w-0 flex-col overflow-hidden rounded-card border-2 bg-surface text-left transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-wait',
                    'border-accent ring-3 ring-accent-soft' => $on,
                    'border-line hover:border-ink-faint' => ! $on,
                ])
            >
                {{-- Each half wears the theme itself (data-theme-scope), light and dark side by side. --}}
                <span class="grid h-20 grid-cols-2" aria-hidden="true">
                    @foreach (['light', 'dark'] as $half)
                        <span data-theme-scope style="{{ $item[$half] }}" class="flex flex-col gap-1.5 bg-paper p-2">
                            <span class="block h-1.5 w-3/4 rounded-full bg-ink opacity-15"></span>
                            <span class="flex h-7 items-center gap-1.5 rounded-card bg-surface px-1.5 shadow-card" style="border: var(--t-bw) solid var(--t-line)">
                                <span class="block h-3 w-6 rounded-card bg-accent"></span>
                                <span class="block h-1 flex-1 rounded-full bg-ink opacity-50"></span>
                            </span>
                            <span class="block h-1.5 w-1/2 rounded-full bg-ink opacity-15"></span>
                        </span>
                    @endforeach
                </span>
                <span class="flex flex-col gap-0.5 border-t border-line px-3 py-2">
                    <span class="flex flex-wrap items-baseline justify-between gap-x-2">
                        <span class="text-sm text-ink" style="font-family: {{ $item['font'] }}; font-weight: {{ $item['weight'] }}">{{ $item['name'] }}</span>
                        @if ($item['id'] === \App\Support\Theme::default())
                            <span class="font-mono text-[10px] uppercase tracking-wider text-ink-faint">{{ __('themes.default') }}</span>
                        @endif
                    </span>
                    <span class="line-clamp-2 text-xs text-ink-faint">{{ $item['description'] }}</span>
                </span>
            </button>
        @endforeach
    </div>
    @error('theme')
        <p class="text-xs text-signal">{{ $message }}</p>
    @enderror

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <span class="text-sm font-medium text-ink">{{ __('themes.mode_label') }}</span>
        <div class="inline-flex rounded-full border border-line bg-paper p-0.5" role="radiogroup" aria-label="{{ __('themes.mode_label') }}">
            @foreach (\App\Support\Theme::MODES as $m)
                <button
                    type="button"
                    role="radio"
                    aria-checked="{{ $m === $mode ? 'true' : 'false' }}"
                    wire:key="mode-{{ $m }}"
                    wire:click="selectMode('{{ $m }}')"
                    wire:loading.attr="disabled"
                    @class([
                        'rounded-full px-3.5 py-1.5 text-sm font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent',
                        'bg-surface text-ink shadow-card' => $m === $mode,
                        'text-ink-soft hover:text-ink' => $m !== $mode,
                    ])
                >{{ __("themes.modes.{$m}") }}</button>
            @endforeach
        </div>
        <span wire:loading wire:target="selectTheme, selectMode" class="text-xs text-ink-faint">{{ __('themes.applying') }}</span>
    </div>
    @error('mode')
        <p class="text-xs text-signal">{{ $message }}</p>
    @enderror
</div>
