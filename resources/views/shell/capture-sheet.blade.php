{{-- The capture sheet (step 2.3): + on mobile, and C or the rail's + on desktop when no top-bar
     field is on screen (session mode). Lives in the layout, outside every Livewire component.
     Also the toast with "Rückgängig" for every capture. --}}
@php
    $captureConfig = [
        'url' => route('shell.capture'),
        'messages' => ['empty' => __('capture.empty'), 'offline' => __('capture.line.offline')],
    ];
@endphp
<script type="application/json" id="capture-config">{!! json_encode($captureConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

<div x-data x-show="$store.capture.sheetOpen" x-cloak class="fixed inset-0 z-[55] flex items-end justify-center wb:items-start wb:pt-[12vh]" role="dialog" aria-modal="true" aria-label="{{ __('capture.line.label') }}">
    <div class="absolute inset-0 bg-black/30" @click="$store.capture.sheetOpen = false"></div>
    <div
        x-data="captureLine('sheet')"
        x-effect="if ($store.capture.sheetOpen) $nextTick(() => $refs.input?.focus())"
        class="relative flex w-full flex-col gap-2 rounded-t-panel bg-surface px-4 pt-2.5 pb-[max(16px,env(safe-area-inset-bottom))] shadow-card wb:w-[min(520px,calc(100vw-32px))] wb:rounded-panel wb:p-4"
        style="border: var(--t-bw) solid var(--t-line)"
    >
        <span class="h-1 w-10 self-center rounded-full bg-line wb:hidden" aria-hidden="true"></span>
        <form @submit.prevent="save()">
            <label class="flex h-12 items-center gap-2 rounded-card border-2 bg-paper px-3 text-ink" :class="error ? 'border-bad' : 'border-accent'">
                <span class="sr-only">{{ __('capture.line.label') }}</span>
                @include('shell.icon', ['name' => 'plus', 'class' => 'size-5 text-ink-faint'])
                <input x-ref="input" x-model="text" type="text" maxlength="{{ \App\Services\Planning\Capture::MAX_LENGTH }}"
                    placeholder="{{ __('capture.line.placeholder') }}" autocomplete="off" enterkeyhint="done"
                    @keydown.escape.prevent="close()" :aria-invalid="error ? 'true' : 'false'"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-faint focus:ring-0">
                <button type="submit" class="grid size-9 place-items-center rounded-full bg-accent text-accent-fg disabled:opacity-60" :disabled="saving" aria-label="{{ __('capture.line.label') }}">
                    @include('shell.icon', ['name' => 'check', 'class' => 'size-5'])
                </button>
            </label>
        </form>
        <p class="flex items-center gap-2 text-sm" role="status">
            <span x-show="saving" class="text-ink-faint">{{ __('capture.line.saving') }}</span>
            <span x-show="! saving && error" class="font-semibold text-bad" x-text="error"></span>
            <button type="button" x-show="! saving && offline" @click="save()" class="font-semibold text-accent-ink underline underline-offset-2">{{ __('capture.line.retry') }}</button>
            <span x-show="! saving && ! error" class="text-ink-faint">{{ __('capture.line.hint') }}</span>
            <button type="button" @click="close()" class="ml-auto rounded-card px-2 py-1 font-semibold text-ink-soft hover:text-ink">{{ __('capture.line.close') }}</button>
        </p>
    </div>
</div>

<div x-data x-show="$store.capture.toast" x-cloak x-transition.opacity
    class="pointer-events-none fixed inset-x-0 top-[max(12px,env(safe-area-inset-top))] z-[60] flex justify-center px-4 wb:top-16" role="status">
    <p class="pointer-events-auto flex items-center gap-3 rounded-card bg-deep py-2 pr-2 pl-4 text-sm font-semibold text-on-deep shadow-card">
        @include('shell.icon', ['name' => 'check', 'class' => 'size-4'])
        <span x-text="$store.capture.toast"></span>
        <button type="button" x-show="$store.capture.lastId" @click="$store.capture.undo()" class="rounded-card px-2 py-1 underline underline-offset-2 hover:bg-white/10">{{ __('capture.line.undo') }}</button>
    </p>
</div>
