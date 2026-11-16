<div class="flex flex-col gap-4" data-card="nudge-times">
    <div>
        <h2 class="text-base font-semibold text-ink">{{ __('shell.nudge.title') }}</h2>
        <p class="mt-0.5 text-sm text-ink-soft">{{ __('shell.nudge.intro') }}</p>
    </div>

    {{-- Does this device get the nudge? Checked in the browser; allowing reuses the old settings' subscription code. --}}
    <div
        x-data="{
            vapidPublicKey: @js(config('webpush.vapid.public_key')),
            state: 'checking',
            devices: {{ (int) $devices }},
            async check() {
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || typeof Notification === 'undefined') { this.state = 'unsupported'; return; }
                if (Notification.permission === 'denied') { this.state = 'denied'; return; }
                try {
                    this.state = (await window.currentPushSubscription()) ? 'on' : 'off';
                } catch (e) {
                    this.state = 'off';
                }
            },
            async enable() {
                this.state = 'enabling';
                try {
                    const sub = await window.subscribeToPush(this.vapidPublicKey);
                    if (!sub) { this.state = Notification.permission === 'denied' ? 'denied' : 'off'; return; }
                    await $wire.subscribeToPush(sub.endpoint, sub.p256dh, sub.auth);
                    this.devices++;
                    this.state = 'on';
                } catch (e) {
                    this.state = 'failed';
                }
            },
            get others() { return Math.max(0, this.devices - (this.state === 'on' ? 1 : 0)); },
        }"
        x-init="check()"
        class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-card bg-paper px-3 py-2.5"
        style="border: var(--t-bw) solid var(--t-line)"
        data-push-status
    >
        <p class="min-w-0 flex-1 basis-56 text-sm text-ink" role="status" aria-live="polite">
            <span x-show="state === 'checking'">{{ __('shell.nudge.push.checking') }}</span>
            <span x-show="state === 'on'" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-good">@include('shell.icon', ['name' => 'check', 'class' => 'size-4 shrink-0']){{ __('shell.nudge.push.on') }}</span>
            <span x-show="state === 'off'" x-cloak>{{ __('shell.nudge.push.off') }}</span>
            <span x-show="state === 'enabling'" x-cloak>{{ __('shell.nudge.push.enabling') }}</span>
            <span x-show="state === 'denied'" x-cloak>{{ __('shell.nudge.push.denied') }}</span>
            <span x-show="state === 'unsupported'" x-cloak>{{ __('shell.nudge.push.unsupported') }}</span>
            <span x-show="state === 'failed'" x-cloak class="text-bad">{{ __('shell.nudge.push.failed') }}</span>
            <template x-if="others > 0 && state !== 'on'">
                <span class="mt-0.5 block text-xs text-ink-faint" x-text="others === 1 ? @js(trans_choice('shell.nudge.push.other_devices', 1)) : @js(trans_choice('shell.nudge.push.other_devices', 2, ['count' => '#'])).replace('#', others)"></span>
            </template>
        </p>
        <button type="button" x-show="state === 'off' || state === 'failed' || state === 'enabling'" x-cloak @click="enable()" :disabled="state === 'enabling'"
            class="inline-flex h-9 shrink-0 items-center rounded-card border border-line bg-surface px-3 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-wait disabled:opacity-60">
            {{ __('shell.nudge.push.enable') }}
        </button>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4" novalidate>
        @unless ($hasAny)
            <p class="rounded-card border border-dashed border-line px-4 py-3 text-sm text-ink-soft" data-nudge-empty>
                {{ $this->configured ? __('shell.nudge.empty_all') : __('shell.nudge.empty_never') }}
            </p>
        @endunless

        <ul class="flex flex-col">
            @foreach ($days as $day => $label)
                @php($time = $times[$day] ?? '')
                @php($errorId = "nudge-time-{$day}-error")
                <li wire:key="nudge-day-{{ $day }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-line py-2.5 first:border-t-0" data-nudge-day="{{ $day }}">
                    <span class="w-24 shrink-0 text-sm font-medium text-ink wb:w-28">{{ $label['long'] }}</span>

                    @if ($time !== '' || $errors->has("times.{$day}"))
                        <input type="time" step="60" wire:model="times.{{ $day }}" aria-label="{{ __('shell.nudge.time_label', ['day' => $label['long']]) }}"
                            @class(['h-10 w-32 rounded-card bg-paper px-3 font-mono text-sm tabular-nums text-ink focus:outline-hidden focus:ring-2 focus:ring-accent', 'border-bad' => $errors->has("times.{$day}"), 'border-line' => ! $errors->has("times.{$day}")])
                            style="border-width: var(--t-bw)" @error("times.{$day}") aria-invalid="true" aria-describedby="{{ $errorId }}" @enderror>
                        <button type="button" wire:click="clearTime({{ $day }})" aria-label="{{ __('shell.nudge.remove_label', ['day' => $label['long']]) }}"
                            class="grid size-9 place-items-center rounded-full text-ink-faint hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            @include('shell.icon', ['name' => 'close', 'class' => 'size-4'])
                        </button>
                        @if ($day === 1 && $time !== '')
                            <button type="button" wire:click="copyWeekdays" class="ml-auto text-sm font-semibold text-accent-ink underline decoration-line underline-offset-4 hover:decoration-current focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.nudge.copy_weekdays') }}</button>
                        @endif
                    @else
                        {{-- Sits in the input's slot: an empty day reads as an empty field you can fill. --}}
                        <button type="button" wire:click="addTime({{ $day }})" aria-label="{{ __('shell.nudge.add') }}: {{ $label['long'] }}"
                            class="inline-flex h-10 w-32 items-center justify-center gap-1.5 rounded-card border border-dashed border-line text-sm font-semibold text-ink-soft hover:border-ink-faint hover:bg-paper hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                            @include('shell.icon', ['name' => 'plus', 'class' => 'size-4'])
                            {{ __('shell.nudge.add') }}
                        </button>
                        <span class="text-xs text-ink-faint">{{ __('shell.nudge.none') }}</span>
                    @endif

                    @error("times.{$day}")
                        <span id="{{ $errorId }}" class="basis-full text-xs font-semibold text-bad wb:pl-31">{{ $message }}</span>
                    @enderror
                </li>
            @endforeach
        </ul>

        <p class="text-xs text-ink-faint">{{ __('shell.nudge.zone', ['zone' => $zone]) }}</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex h-10 items-center gap-2 rounded-card bg-accent px-4 text-sm font-semibold text-accent-fg hover:brightness-110 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ __('shell.nudge.save') }}</span>
                <span wire:loading wire:target="save">{{ __('shell.nudge.saving') }}</span>
            </button>
            <p role="status" aria-live="polite" class="text-sm">
                @if ($status === 'saved')
                    <span class="font-semibold text-good" data-nudge-saved>{{ $savedNote ?? __('shell.nudge.saved') }}</span>
                @elseif ($status === 'error')
                    <span class="font-semibold text-bad">{{ __('shell.nudge.errors.save') }}</span>
                @endif
            </p>
        </div>
    </form>
</div>
