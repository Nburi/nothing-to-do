{{-- The Werkbank top bar's capture line (C). wire:ignore: a re-render never eats typed text.
     @param $mode (board) --}}
<div wire:ignore class="relative" x-data="captureLine('inline')" data-capture-inline @capture-focus="focus()">
    <x-shell.action key="capture" layout="werkbank" :mode="$mode ?? 'board'"
        x-show="! active" @click="focus()"
        class="flex h-9 w-64 items-center gap-2 rounded-card border border-line bg-paper px-3 text-sm text-ink-faint transition hover:text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent lg:w-72">
        @include('shell.icon', ['name' => 'plus', 'class' => 'size-4'])
        <span>{{ __('capture.line.label') }}</span>
        <kbd class="ml-auto rounded border border-line px-1.5 font-mono text-[11px] text-ink-faint">{{ __('capture.line.shortcut') }}</kbd>
    </x-shell.action>
    <form x-show="active" x-cloak @submit.prevent="save()" class="relative">
        <label class="flex h-9 w-[min(440px,40vw)] items-center gap-2 rounded-card border-2 bg-surface px-3 text-sm text-ink"
            :class="error ? 'border-bad' : 'border-accent'">
            <span class="sr-only">{{ __('capture.line.label') }}</span>
            @include('shell.icon', ['name' => 'plus', 'class' => 'size-4 text-ink-faint'])
            <input x-ref="input" x-model="text" type="text" maxlength="{{ \App\Services\Planning\Capture::MAX_LENGTH }}"
                placeholder="{{ __('capture.line.placeholder') }}" autocomplete="off" enterkeyhint="done"
                @keydown.escape.prevent="close()" @blur="if (text.trim() === '' && ! error) active = false"
                :aria-invalid="error ? 'true' : 'false'" aria-describedby="capture-inline-msg"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:ring-0">
            <span x-show="saving" class="text-xs text-ink-faint">{{ __('capture.line.saving') }}</span>
            <kbd x-show="! saving" class="rounded border border-line px-1.5 font-mono text-[11px] text-ink-faint">Enter</kbd>
        </label>
        <p id="capture-inline-msg" x-show="error" x-cloak class="absolute top-full right-0 left-0 z-40 mt-1.5 flex items-center gap-2 rounded-card bg-surface px-3 py-2 text-xs font-semibold text-bad shadow-card" style="border: var(--t-bw) solid var(--t-line)" role="alert">
            <span x-text="error"></span>
            <button type="submit" x-show="offline" class="ml-auto font-semibold text-accent-ink underline underline-offset-2">{{ __('capture.line.retry') }}</button>
        </p>
    </form>
</div>
