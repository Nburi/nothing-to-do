{{--
    "Nicht vergessen" chips above the Zeitstrahl — see App\Livewire\ReminderBadges.
    One chip per reminder within reach (today … +2 days): quiet and neutral two
    days out, contour the day before, overprint on the day. The title opens the
    list of all reminders; "Hab ich" ticks it off.

    Signature moment ("Eingepackt"): ticking folds the chip into a check mark
    (the stroke draws itself) and a line says what that changed — "Morgen früh
    kommt nichts mehr." — for a few seconds, then it is gone. The key of the
    ticked variant differs from the open one, so Livewire swaps in a fresh node
    and the animation plays once; the leaving timer is client-side (Alpine).

    The permanent root div is required (Livewire rejects a root view that can
    compile to nothing); with no reminders it is an empty div with no height.
--}}
<div wire:poll.60s.visible>
    @php
        $today = auth()->user()->localToday();
    @endphp
    @if ($this->reminders->isNotEmpty() || $this->laterCount > 0)
        <div class="mb-2 flex flex-wrap items-center gap-1.5" role="list" aria-label="Nicht vergessen">
            @foreach ($this->reminders as $reminder)
                @if ($reminder->isAcknowledged())
                    <div
                        wire:key="reminder-{{ $reminder->id }}-done"
                        x-data="{ gone: false }"
                        x-init="setTimeout(() => gone = true, 3400)"
                        x-show="! gone"
                        x-transition.opacity.duration.300ms
                        role="listitem"
                        class="reminder-fold inline-flex items-center gap-2 rounded-full border border-forest/30 bg-forest-soft px-3 py-1.5 text-xs text-forest"
                    >
                        <svg class="h-3.5 w-3.5 flex-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                        <span class="font-medium">{{ \App\Livewire\ReminderBadges::acknowledgedMessage($reminder) }}</span>
                    </div>
                @else
                    @php
                        $days = (int) round($today->diffInDays($reminder->remind_on->copy()->startOfDay(), false));
                        $tone = match (true) {
                            $days <= 0 => 'border-overprint/40 bg-overprint-soft text-overprint',
                            $days === 1 => 'border-contour/40 bg-contour-soft text-contour',
                            default => 'border-line bg-surface text-ink-soft',
                        };
                    @endphp
                    <div wire:key="reminder-{{ $reminder->id }}-open" role="listitem" class="inline-flex max-w-full items-center gap-1 rounded-full border py-0.5 pl-3 pr-0.5 text-xs {{ $tone }}">
                        <button
                            type="button"
                            @click="Livewire.dispatch('open-reminders', { id: {{ $reminder->id }} })"
                            class="flex min-w-0 items-center gap-1.5 py-1 text-left focus:outline-none focus-visible:underline"
                            aria-label="Nicht vergessen {{ $reminder->dateLabel($today) }}: {{ $reminder->title }}. Antippen zum Bearbeiten"
                        >
                            <svg class="h-3.5 w-3.5 flex-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14V2.5M4 3h7.5l-1.5 2.75L11.5 8.5H4"/></svg>
                            <span class="truncate font-medium">{{ $reminder->title }}</span>
                            <span class="flex-none opacity-80">· {{ $reminder->dateLabel($today) }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="acknowledge({{ $reminder->id }})"
                            wire:loading.attr="disabled"
                            wire:target="acknowledge({{ $reminder->id }})"
                            class="flex-none rounded-full px-2.5 py-1.5 font-medium transition hover:bg-surface/60 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest disabled:opacity-50"
                            aria-label="{{ $reminder->title }}: hab ich"
                        >Hab ich</button>
                    </div>
                @endif
            @endforeach

            {{-- Further-out reminders: not worth a chip each, but the way into the list must exist. --}}
            @if ($this->laterCount > 0)
                <button
                    type="button"
                    @click="Livewire.dispatch('open-reminders')"
                    class="rounded-full px-2.5 py-1.5 text-xs text-ink-faint transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                >{{ $this->reminders->isEmpty() ? ($this->laterCount === 1 ? '1 Erinnerung geplant' : $this->laterCount.' Erinnerungen geplant') : '+'.$this->laterCount.' geplant' }}</button>
            @endif
        </div>
    @endif
</div>
