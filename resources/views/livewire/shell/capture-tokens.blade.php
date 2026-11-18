<div class="flex flex-col gap-4">
    <div>
        <h2 class="text-base font-semibold text-ink">{{ __('capture.tokens.title') }}</h2>
        <p class="mt-0.5 text-sm text-ink-soft">{{ __('capture.tokens.intro') }}</p>
    </div>

    @if ($notice)
        <p class="rounded-card bg-accent-soft px-3 py-2 text-sm text-ink" role="status">{{ $notice }}</p>
    @endif

    @if ($plain)
        <div class="flex flex-col gap-3" x-data="{ copied: false }">
            <p class="rounded-card bg-good-soft px-3 py-2 text-sm font-semibold text-good" role="status">{{ __('capture.tokens.created', ['name' => $plainName]) }}</p>
            <div class="flex items-center gap-2 overflow-hidden rounded-card border border-dashed border-ink-faint bg-paper px-3 py-2">
                <code x-ref="token" class="min-w-0 flex-1 truncate font-mono text-[13px] text-ink select-all">{{ $plain }}</code>
                <button type="button"
                    @click="navigator.clipboard?.writeText($refs.token.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000) }, () => { window.getSelection().selectAllChildren($refs.token) })"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-card border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                    <span x-text="copied ? @js(__('capture.tokens.copied')) : @js(__('capture.tokens.copy'))">{{ __('capture.tokens.copy') }}</span>
                </button>
            </div>
            <ol class="flex list-decimal flex-col gap-1 pl-5 text-sm text-ink-soft">
                @foreach (__('capture.tokens.steps') as $step)
                    <li class="wrap-break-word">{{ str_replace(':url', $captureUrl, $step) }}</li>
                @endforeach
            </ol>
            <button type="button" wire:click="dismiss" class="self-start text-sm font-semibold text-accent-ink underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('capture.tokens.done') }}</button>
        </div>
    @endif

    @if ($this->tokens->isEmpty() && ! $plain)
        <p class="rounded-card border border-dashed border-line px-4 py-3 text-sm text-ink-soft">{{ __('capture.tokens.empty') }}</p>
    @elseif ($this->tokens->isNotEmpty())
        <ul class="flex flex-col">
            @foreach ($this->tokens as $token)
                <li wire:key="token-{{ $token->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-line py-3 first:border-t-0">
                    <div class="min-w-40 flex-1">
                        <p class="font-semibold text-ink wrap-break-word">{{ $token->name }}</p>
                        <p class="text-xs text-ink-faint">
                            {{ __('capture.tokens.created_at', ['date' => $token->created_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}
                            ·
                            {{ $token->last_used_at ? __('capture.tokens.used', ['when' => $token->last_used_at->locale(app()->getLocale())->diffForHumans()]) : __('capture.tokens.never_used') }}
                        </p>
                    </div>
                    @if ($confirming === $token->id)
                        <p class="basis-full rounded-card bg-warn-soft px-3 py-2 text-sm font-semibold text-warn" role="alert">{{ __('capture.tokens.revoke_confirm', ['name' => $token->name]) }}</p>
                        <button type="button" wire:click="keep" class="rounded-card border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('capture.tokens.keep') }}</button>
                        <button type="button" wire:click="revoke({{ $token->id }})" class="rounded-card bg-bad px-3 py-1.5 text-sm font-semibold text-surface hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-bad">{{ __('capture.tokens.revoke') }}</button>
                    @else
                        <button type="button" wire:click="askRevoke({{ $token->id }})" class="rounded-card border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink-soft hover:text-bad focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('capture.tokens.revoke') }}</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="create" class="flex flex-wrap items-end gap-2">
        <label class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
            <span class="text-sm font-medium text-ink">{{ __('capture.tokens.name') }}</span>
            <input type="text" wire:model="name" maxlength="40" placeholder="{{ __('capture.tokens.name_placeholder') }}"
                @class(['h-10 rounded-card bg-paper px-3 text-sm text-ink placeholder:text-ink-faint focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has('name'), 'border-line' => ! $errors->has('name')])
                style="border-width: var(--t-bw)" @error('name') aria-invalid="true" aria-describedby="token-name-error" @enderror>
            @error('name')
                <span id="token-name-error" class="text-xs font-semibold text-bad">{{ $message }}</span>
            @else
                <span class="text-xs text-ink-faint">{{ __('capture.tokens.name_hint') }}</span>
            @enderror
        </label>
        <button type="submit" wire:loading.attr="disabled" wire:target="create"
            class="mb-5 inline-flex h-10 items-center gap-2 rounded-card bg-accent px-4 text-sm font-semibold text-accent-fg hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60">
            {{ $this->tokens->isEmpty() ? __('capture.tokens.create') : __('capture.tokens.another') }}
        </button>
    </form>
</div>
