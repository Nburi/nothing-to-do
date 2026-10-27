{{--
    "Aufteilen" sheet — see App\Livewire\SplitTaskSheet. The permanent outer
    div is required (Livewire rejects a root view that can compile to
    nothing); everything inside only exists while a task is open.

    Signature moment ("Rückwärts planen"): the 14 days sit in a strip with
    the Arbeit-Block time of each day as a small bar and the deadline marked
    on it. Tapping a day drops a part onto it — its minutes appear as the
    forest-coloured share of that day's bar, or turn contour when the day
    would be overfull — so you see whether the whole task fits before you
    commit to anything. The strip is Alpine state (splitSheet in app.js).
--}}
<div>
@if ($this->taskId && $this->task && $this->sheetConfig)
    @php
        $task = $this->task;
        $context = $this->context;
    @endphp
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ $task->title }} aufteilen">
        <div class="absolute inset-0 bg-ink/40" wire:click="close"></div>

        <div
            wire:key="split-sheet-{{ $task->id }}"
            x-data="splitSheet(@js($this->sheetConfig))"
            @keydown.escape.window="$wire.close()"
            class="animate-rise relative max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card"
        >
            <div class="mb-1 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">{{ $context['isSplit'] ? 'Aufteilung ändern' : 'Aufteilen' }}</p>
                    <h2 class="truncate text-base font-medium text-ink">{{ $task->title }}</h2>
                </div>
                <button type="button" wire:click="close" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm leading-relaxed text-ink-soft">
                Tippe die Tage an, an denen du daran arbeiten willst. Die Balken zeigen, wie viel Arbeitsblock-Zeit der Tag noch hat.
                @if ($context['deadlineLabel'])
                    <span class="text-contour">Fällig {{ $context['deadlineLabel'] }}.</span>
                @endif
            </p>

            {{-- How much work, in how many parts --}}
            <div class="mb-4 grid grid-cols-2 gap-3">
                <div class="rounded-card border border-line bg-paper px-3 py-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Insgesamt noch</p>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <button type="button" @click="bump(-15)" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-line/60 active:scale-95" aria-label="15 Minuten weniger">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M3.5 8h9"/></svg>
                        </button>
                        <span class="tnum text-sm font-medium text-ink"><span x-text="total"></span> min</span>
                        <button type="button" @click="bump(15)" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-line/60 active:scale-95" aria-label="15 Minuten mehr">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9"/></svg>
                        </button>
                    </div>
                </div>
                <div class="rounded-card border border-line bg-paper px-3 py-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Teile</p>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <button type="button" @click="step(-1)" :disabled="busy || count <= 1" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-line/60 active:scale-95 disabled:opacity-40" aria-label="Einen Teil weniger">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M3.5 8h9"/></svg>
                        </button>
                        <span class="tnum text-sm font-medium text-ink" x-text="count"></span>
                        <button type="button" @click="step(1)" :disabled="busy" class="grid h-8 w-8 place-items-center rounded-card text-ink-soft transition hover:bg-line/60 active:scale-95 disabled:opacity-40" aria-label="Einen Teil mehr">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- The 14-day strip --}}
            <div class="grid grid-cols-7 gap-1.5" :class="busy ? 'opacity-60 transition-opacity' : 'transition-opacity'" role="group" aria-label="Tage wählen">
                <template x-for="day in days" :key="day.date">
                    <button
                        type="button"
                        @click="toggle(day)"
                        :disabled="day.locked"
                        :aria-pressed="isSelected(day.date) ? 'true' : 'false'"
                        :aria-label="`${day.label}${day.locked ? ', Teil bereits erledigt' : isSelected(day.date) ? ', Teil geplant, antippen zum Entfernen' : ', antippen für einen Teil'}${day.capacityTotal > 0 ? `, ${Math.max(0, free(day))} Minuten Arbeitsblock frei` : ', kein Arbeitsblock'}`"
                        class="relative flex min-h-[4.5rem] flex-col items-center gap-1 rounded-card border px-1 pb-1.5 pt-1.5 text-center transition active:scale-[0.97] disabled:cursor-not-allowed"
                        :class="[
                            isSelected(day.date) ? (overflows(day) ? 'border-contour bg-contour-soft' : 'border-forest bg-forest-soft') : 'border-line bg-paper hover:border-ink-faint/50',
                            isAfterDeadline(day) && ! isSelected(day.date) ? 'opacity-45' : '',
                            day.locked ? 'opacity-60' : '',
                        ]"
                    >
                        <span class="text-[10px] font-medium uppercase tracking-wide text-ink-faint" x-text="day.weekday"></span>
                        <span class="tnum text-sm font-medium" :class="isSelected(day.date) ? (overflows(day) ? 'text-contour' : 'text-forest') : 'text-ink'" x-text="day.dayNumber"></span>

                        <template x-if="day.capacityTotal > 0">
                            <span class="relative mt-auto block h-1.5 w-full overflow-hidden rounded-full bg-line">
                                <span class="absolute inset-y-0 left-0 rounded-full bg-ink-soft/60 transition-[width] duration-300" :style="`width: ${Math.min(100, Math.round(day.capacityUsed / day.capacityTotal * 100))}%`"></span>
                                <span class="absolute inset-y-0 rounded-full transition-all duration-300" :class="overflows(day) ? 'bg-contour' : 'bg-forest'" :style="`left: ${Math.min(100, Math.round(day.capacityUsed / day.capacityTotal * 100))}%; width: ${Math.min(100 - Math.min(100, Math.round(day.capacityUsed / day.capacityTotal * 100)), partPct(day))}%`"></span>
                            </span>
                        </template>
                        <template x-if="day.capacityTotal === 0">
                            <span class="mt-auto text-[9px] leading-none text-ink-faint">kein Block</span>
                        </template>

                        <template x-if="isSelected(day.date)">
                            <span class="split-pop absolute -right-1 -top-1 grid min-w-[1.25rem] place-items-center rounded-full px-1 text-[10px] font-medium leading-[1.1rem] text-white tnum" :class="overflows(day) ? 'bg-contour' : 'bg-forest'" x-text="per"></span>
                        </template>
                        <template x-if="day.locked">
                            <span class="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-forest text-white" aria-hidden="true">
                                <svg class="h-2.5 w-2.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="m3.5 8.5 3 3 6-7"/></svg>
                            </span>
                        </template>
                        <template x-if="isDeadlineDay(day)">
                            <span class="absolute inset-x-0 -bottom-2 mx-auto w-fit rounded-full bg-contour px-1.5 text-[9px] font-medium leading-4 text-white">Frist</span>
                        </template>
                    </button>
                </template>
            </div>

            {{-- Live verdict: does it fit? --}}
            <div class="mt-5 min-h-[2.5rem] text-sm" aria-live="polite">
                <p class="text-ink" x-show="count > 0">
                    <span class="tnum font-medium" x-text="count"></span> <span x-text="count === 1 ? 'Teil' : 'Teile'"></span> · je <span class="tnum font-medium" x-text="per"></span> min
                    <template x-if="doneCount > 0"><span class="text-ink-faint">· <span x-text="doneCount"></span> schon erledigt</span></template>
                </p>
                <p class="text-ink-faint" x-show="count === 0">Noch kein Tag gewählt.</p>
                <p class="text-contour" x-show="lateCount > 0" x-text="lateCount === 1 ? '1 Teil liegt nach der Frist.' : lateCount + ' Teile liegen nach der Frist.'"></p>
                <p class="text-contour" x-show="tightCount > 0" x-text="tightCount === 1 ? '1 Tag ist damit voll belegt.' : tightCount + ' Tage sind damit voll belegt.'"></p>
                <p class="text-ink-faint" x-show="blocklessCount > 0 && lateCount === 0 && tightCount === 0" x-text="blocklessCount === 1 ? '1 Teil liegt auf einem Tag ohne Arbeitsblock.' : blocklessCount + ' Teile liegen auf Tagen ohne Arbeitsblock.'"></p>
            </div>

            @error('split')
                <p class="mt-2 rounded-card bg-signal-soft px-3 py-2 text-sm text-signal" role="alert">{{ $message }}</p>
            @enderror

            {{-- Parts already in place: tick off or reopen --}}
            @if ($context['isSplit'])
                <div class="mt-4 border-t border-line pt-3">
                    <p class="mb-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Bisherige Teile</p>
                    <ul class="divide-y divide-line/70">
                        @foreach ($context['parts'] as $part)
                            @php $partDate = \Illuminate\Support\Carbon::parse($part['date']); @endphp
                            <li class="flex items-center gap-3 py-1.5" wire:key="split-part-{{ $part['id'] }}">
                                <button
                                    type="button"
                                    wire:click="togglePart({{ $part['id'] }})"
                                    class="grid h-7 w-7 flex-none place-items-center rounded-full border transition active:scale-95 {{ $part['done'] ? 'border-forest bg-forest text-white' : 'border-line text-transparent hover:border-forest hover:text-forest' }}"
                                    aria-label="Teil {{ $part['position'] }} {{ $part['done'] ? 'wieder öffnen' : 'als erledigt markieren' }}"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                                </button>
                                <span class="min-w-0 flex-1 text-sm {{ $part['done'] ? 'text-ink-faint line-through' : 'text-ink' }}">Teil {{ $part['position'] }} · {{ $partDate->isoFormat('dd D.M.') }}</span>
                                <span class="tnum text-xs text-ink-faint">{{ $part['minutes'] }} min</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-5 flex items-center gap-2">
                @if ($context['isSplit'])
                    <button
                        type="button"
                        x-data="{ armed: false, _t: null }"
                        @click="if (armed) { $wire.unsplit(); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                        @click.outside="armed = false; clearTimeout(_t)"
                        @keydown.escape.window="armed = false; clearTimeout(_t)"
                        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
                        class="rounded-card px-3 py-2 text-sm transition"
                        x-text="armed ? 'Wirklich aufheben?' : 'Aufteilung aufheben'"
                    ></button>
                @endif
                <span class="flex-1"></span>
                <button type="button" wire:click="close" class="rounded-card px-3 py-2 text-sm text-ink-soft transition hover:bg-paper hover:text-ink">Abbrechen</button>
                <button
                    type="button"
                    @click="save()"
                    :disabled="! canSave"
                    class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <span x-show="! saving">{{ $context['isSplit'] ? 'Speichern' : 'Aufteilen' }}</span>
                    <span x-show="saving" x-cloak>Speichere …</span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
