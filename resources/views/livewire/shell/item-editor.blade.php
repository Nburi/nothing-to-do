{{-- Step 4.9: the edit sheet of one item and the toast with "Rückgängig" (both layouts, one render: a bottom
     sheet below 720 px, a centred card from there). The fields are the ones of the daily sort (CONVENTIONS 6). --}}
<div x-data @keydown.escape.window="if ($wire.open) $wire.close()">
    @if ($open)
        @php
            $isLater = $list === 'later';
            $label = 'font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint';
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center wb:items-center" data-item-editor>
            <div class="absolute inset-0 bg-black/45" wire:click="close" aria-hidden="true"></div>
            <form wire:submit="save" x-data x-trap.noscroll="true" role="dialog" aria-modal="true" aria-labelledby="item-editor-title"
                class="relative flex max-h-[92dvh] w-full flex-col gap-4 overflow-y-auto overscroll-contain rounded-t-panel bg-surface p-5 pb-[max(20px,env(safe-area-inset-bottom))] shadow-card wb:w-[min(560px,calc(100vw-32px))] wb:rounded-panel wb:p-6 wb:pb-6"
                style="border: var(--t-bw) solid var(--t-line)" novalidate>
                <div class="flex items-center justify-between gap-3">
                    <h2 id="item-editor-title" class="font-mono text-[11px] font-medium uppercase tracking-widest text-ink-faint">{{ __('items.sheet.title') }}</h2>
                    <button type="button" wire:click="close" class="-mr-2 grid size-9 place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('items.sheet.close') }}" title="{{ __('items.sheet.esc') }}">
                        @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                    </button>
                </div>

                <label class="flex min-w-0 flex-col gap-1">
                    <span class="{{ $label }}">{{ __('items.sheet.title_label') }}</span>
                    <input type="text" wire:model="title" maxlength="{{ \App\Services\Planning\Capture::MAX_LENGTH }}" autocomplete="off" autofocus
                        @class(['h-11 min-w-0 rounded-card bg-paper px-3 text-base text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('title'), 'border-line' => ! $errors->has('title')])
                        style="border-width: var(--t-bw)" @error('title') aria-invalid="true" aria-describedby="item-editor-title-error" @enderror>
                    @error('title') <span id="item-editor-title-error" class="text-xs font-semibold text-bad" role="alert">{{ $message }}</span> @enderror
                </label>

                <div>
                    <span class="mb-1.5 block {{ $label }}">{{ __('items.sheet.list') }}</span>
                    <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('items.sheet.list') }}">
                        @foreach (\App\Models\Task::LISTS as $target)
                            @php($on = $list === $target)
                            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="pick('{{ $target }}')"
                                @class(['rounded-full border px-3 py-1.5 text-[13px] font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>{{ __('shell.lists.'.$target) }}</button>
                        @endforeach
                    </div>
                    @error('list') <p class="mt-1 text-xs font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                </div>

                @if ($list !== 'inbox')
                    <div>
                        <span class="mb-1.5 block {{ $label }}">{{ __('items.sheet.size') }}</span>
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('items.sheet.size') }}">
                            @foreach (\App\Planning\Size::cases() as $s)
                                @php($on = $size === $s->value)
                                <button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" wire:click="pickSize('{{ $s->value }}')"
                                    @class(['rounded-full border px-3 py-1.5 text-[13px] transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', 'border-ink bg-ink text-paper' => $on, 'border-line bg-surface text-ink-soft hover:text-ink' => ! $on])>
                                    <b class="font-semibold">{{ __('shell.sizes.'.$s->value) }}</b> · {{ __('sort.size_hints.'.$s->value) }}
                                </button>
                            @endforeach
                        </div>
                        @error('size') <p class="mt-1 text-xs font-semibold text-bad" role="alert">{{ $message }}</p> @else <p class="mt-1 text-xs text-ink-faint">{{ __('items.sheet.size_hint') }}</p> @enderror
                    </div>
                @endif

                @include('shell.sort-details', ['isLater' => $isLater])

                <label class="flex min-w-0 flex-col gap-1">
                    <span class="{{ $label }}">{{ __('items.sheet.notes') }}</span>
                    <textarea wire:model="notes" rows="3" maxlength="5000" placeholder="{{ __('items.sheet.notes_placeholder') }}"
                        @class(['min-h-20 min-w-0 resize-y rounded-card bg-paper px-2.5 py-2 text-sm text-ink placeholder:text-ink-faint focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('notes'), 'border-line' => ! $errors->has('notes')])
                        style="border-width: var(--t-bw)"></textarea>
                    @error('notes') <span class="text-xs font-semibold text-bad" role="alert">{{ $message }}</span> @enderror
                </label>

                <div class="sticky bottom-0 z-10 -mx-5 -mb-5 flex flex-wrap items-center gap-2 border-t border-line bg-surface px-5 py-3 wb:-mx-6 wb:-mb-6 wb:px-6">
                    <x-shell.action key="delete" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-card px-3 text-sm font-semibold text-bad transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60">
                        @include('shell.icon', ['name' => 'trash', 'class' => 'size-4'])
                        {{ __('items.sheet.delete') }}
                    </x-shell.action>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="close" class="inline-flex min-h-11 items-center rounded-card px-3 text-sm font-semibold text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('items.sheet.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex min-h-11 items-center justify-center rounded-card bg-accent px-5 text-sm font-semibold text-accent-fg transition hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ __('items.sheet.save') }}</span>
                        <span wire:loading wire:target="save">{{ __('items.sheet.saving') }}</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($toast)
        <div wire:key="item-toast-{{ $toastSeq }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 10000)" x-show="show" x-transition.opacity data-item-toast
            class="fixed inset-x-0 top-[max(56px,calc(env(safe-area-inset-top)+44px))] z-[60] flex justify-center px-4 wb:top-auto wb:bottom-6" role="status">
            <p class="flex max-w-md items-center gap-3 rounded-card bg-deep py-2 pr-2 pl-4 text-sm font-semibold text-on-deep shadow-card">
                <span class="min-w-0 wrap-break-word">{{ $toast }}</span>
                @if ($undo)
                    <button type="button" wire:click="undoLast" wire:loading.attr="disabled" class="shrink-0 rounded-card px-2 py-1 underline underline-offset-2 hover:bg-white/10 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep">{{ __('items.toast.undo') }}</button>
                @endif
                <button type="button" wire:click="dismiss" class="shrink-0 rounded-full p-1 opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="{{ __('items.toast.dismiss') }}">
                    @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </p>
        </div>
    @endif
</div>
