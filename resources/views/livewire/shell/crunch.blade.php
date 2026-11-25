{{-- Step 4.2: the crunch screen. Werkbank = a panel on the right, Stapel = a sheet from the bottom (one markup, CSS).
     Soft deadlines come first as the suggestion; hard ones change only when the user says so (R5, F5). --}}
<div x-data @keydown.escape.window="if ($wire.open) $wire.close()">
    @if ($open)
        @php
            $v = $this->view;
            $now = \App\Support\UserClock::now($this->user);
            $soft = array_values(array_filter($v->items, fn ($i) => ! $i->hard));
            $hard = array_values(array_filter($v->items, fn ($i) => $i->hard));
            $scale = max(25, ...array_map(fn ($d) => max($d['capacity'], $d['load']), $v->days ?: [['capacity' => 0, 'load' => 0]]));
            $btn = 'rounded-full px-3 py-1.5 text-[13px] font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-not-allowed disabled:opacity-50';
            $plain = $btn.' bg-surface text-ink hover:bg-paper';
            $field = 'rounded-card bg-paper px-3 py-2 font-mono text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
        @endphp
        <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="crunch-title" x-trap.noscroll.noautofocus="true">
            <div class="absolute inset-0 bg-black/40" wire:click="close"></div>

            <section
                class="absolute inset-x-0 bottom-0 flex max-h-[88dvh] flex-col rounded-t-panel bg-surface pb-[env(safe-area-inset-bottom)] shadow-card wb:inset-y-0 wb:left-auto wb:right-0 wb:max-h-none wb:w-[min(460px,100vw)] wb:rounded-none focus:outline-hidden"
                style="border: var(--t-bw) solid var(--t-line)"
                tabindex="-1" x-init="$nextTick(() => $el.focus({ preventScroll: true }))"
            >
                {{-- Head: until when, and how much is missing (live). --}}
                <header class="flex items-start gap-3 px-5 pb-3 pt-4" style="border-bottom: var(--t-bw) solid var(--t-line)">
                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.crunch.title') }}</span>
                        <h2 id="crunch-title" class="font-display text-xl text-ink" style="font-weight: var(--t-dw)">
                            {{ $v->hasCrunch() ? __('shell.crunch.heading', ['day' => \App\Services\Planning\DayLabel::for($v->windowEnd, $now)]) : __('shell.crunch.title') }}
                        </h2>
                        <p class="font-mono text-[13px] tabular-nums" role="status" aria-live="polite">
                            @if ($v->isHard())
                                <span class="font-semibold text-warn">{{ trans_choice('shell.crunch.missing', $v->missingSessions, ['count' => $v->missingSessions]) }}</span>
                                <span class="text-ink-faint">{{ __('shell.crunch.missing_minutes', ['minutes' => $v->missingMinutes]) }}</span>
                            @elseif ($v->hasCrunch())
                                <span class="text-ink-soft">{{ trans_choice('shell.crunch.missing', $v->missingSessions, ['count' => $v->missingSessions]) }} {{ __('shell.crunch.missing_minutes', ['minutes' => $v->missingMinutes]) }}</span>
                                <span class="block font-display text-sm text-ink-faint">{{ __('shell.crunch.soft_only') }}</span>
                            @elseif ($v->slackMinutes === null)
                                <span class="font-semibold text-good">{{ __('shell.crunch.fits_none') }}</span>
                            @elseif ($v->slackMinutes > 0)
                                <span class="font-semibold text-good">{{ __('shell.crunch.fits_slack', ['minutes' => $v->slackMinutes]) }}</span>
                            @else
                                <span class="font-semibold text-good">{{ __('shell.crunch.fits_tight') }}</span>
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="close" class="grid size-11 shrink-0 place-items-center rounded-full text-ink-soft hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.crunch.close') }}">
                        @include('shell.icon', ['name' => 'close', 'class' => 'size-5'])
                    </button>
                </header>

                <div class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain" wire:loading.class="opacity-70" wire:target="shrink,moveSoft,moveHard,drop,addTime,undoAt">
                    @if ($notice)
                        <p class="mx-5 mt-3 rounded-card bg-accent-soft px-3 py-2 text-sm text-ink" role="status">{{ $notice }}</p>
                    @endif

                    {{-- Day bars: sessions free against the work planned, the overflow hatched. --}}
                    @if ($v->days !== [])
                        <div class="px-5 pt-3" role="img" aria-label="{{ __('shell.crunch.days') }}">
                            <div class="grid gap-1.5" style="grid-template-columns: repeat({{ count($v->days) }}, minmax(0, 1fr))">
                                @foreach ($v->days as $day)
                                    @php
                                        $fill = min($day['load'], $day['capacity']) / $scale * 100;
                                        $over = max(0, $day['load'] - $day['capacity']) / $scale * 100;
                                        $room = $day['capacity'] / $scale * 100;
                                    @endphp
                                    <div class="flex min-w-0 flex-col gap-1" title="{{ __('shell.crunch.day_bar', ['day' => $day['label'], 'sessions' => $day['sessions'], 'load' => $day['load']]) }}">
                                        <div class="relative h-12 overflow-hidden rounded-[6px] bg-paper" style="border: var(--t-bw) solid var(--t-line)">
                                            <span class="absolute inset-x-0 bottom-0" style="height: {{ $room }}%; background: color-mix(in srgb, var(--t-accent) 12%, var(--t-surface))"></span>
                                            <span class="absolute inset-x-0 bottom-0" style="height: {{ $fill }}%; background: color-mix(in srgb, var(--t-accent) 40%, var(--t-surface))"></span>
                                            @if ($over > 0)
                                                <span class="absolute inset-x-0" style="bottom: {{ $fill }}%; height: {{ min($over, 100 - $fill) }}%; background: repeating-linear-gradient(135deg, color-mix(in srgb, var(--t-warn) 55%, transparent) 0 4px, transparent 4px 8px)"></span>
                                            @endif
                                        </div>
                                        <span @class(['truncate font-mono text-[10.5px] tabular-nums', $day['overloaded'] ? 'text-warn' : 'text-ink-faint'])>{{ $day['label'] }} · {{ __('shell.crunch.day_sessions', ['count' => $day['sessions']]) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @foreach ([['soft_group', $soft], ['hard_group', $hard]] as [$group, $items])
                        @if ($items !== [])
                            <h3 class="px-5 pb-1 pt-4 font-mono text-[10.5px] font-medium uppercase tracking-wider text-ink-faint">{{ __('shell.crunch.'.$group) }}</h3>
                            <ul class="flex flex-col">
                                @foreach ($items as $item)
                                    @php($t = $item->task)
                                    <li class="flex flex-col gap-2 px-5 py-3" style="border-top: var(--t-bw) solid color-mix(in srgb, var(--t-line) 70%, transparent)" wire:key="crunch-item-{{ $t->id }}">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                            <span class="min-w-0 font-semibold wrap-break-word text-ink">{{ $t->title }}</span>
                                            <span @class(['rounded-full px-2 py-0.5 font-mono text-[11px]', 'text-warn' => $item->hard, 'text-ink-soft' => ! $item->hard])
                                                style="border: var(--t-bw) solid {{ $item->hard ? 'color-mix(in srgb, var(--t-warn) 40%, var(--t-line))' : 'var(--t-line)' }}">
                                                {{ __($item->hard ? 'shell.crunch.hard_pill' : 'shell.crunch.soft_pill', ['day' => $item->deadlineLabel]) }}
                                            </span>
                                        </div>
                                        <span class="-mt-1 text-xs text-ink-soft">{{ __('shell.crunch.minutes', ['size' => __('shell.sizes.'.$t->effectiveSize()->value), 'minutes' => $item->minutes]) }}</span>

                                        @if ($moving === $t->id)
                                            <form wire:submit="moveHard" class="flex flex-col gap-2 rounded-card bg-paper p-3">
                                                <label for="crunch-move-date" class="text-sm font-medium text-ink">{{ __('shell.crunch.move_date', ['task' => $t->title]) }}</label>
                                                <input id="crunch-move-date" type="date" wire:model="moveDate" min="{{ $now->addDay()->toDateString() }}"
                                                    @class([$field, 'bg-surface', 'ring-2 ring-bad' => $errors->has('moveDate')]) style="border: var(--t-bw) solid var(--t-line)"
                                                    aria-invalid="{{ $errors->has('moveDate') ? 'true' : 'false' }}" @error('moveDate') aria-describedby="crunch-move-date-error" @enderror>
                                                @error('moveDate') <p data-field-error role="alert" id="crunch-move-date-error" class="text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                                {{-- The engine's proposal: the first day this task fits. The date above starts out as that day; the user still confirms. --}}
                                                @if ($item->fitDate)
                                                    <p class="text-sm text-ink">{{ __('shell.crunch.fit_hint', ['date' => \App\Services\Planning\DayLabel::for($item->fitDate, $now), 'task' => $t->title]) }}</p>
                                                @elseif ($item->late)
                                                    <p class="text-sm text-ink-soft">{{ __('shell.crunch.fit_none', ['task' => $t->title]) }}</p>
                                                @endif
                                                <label class="flex items-start gap-2 text-sm text-ink">
                                                    <input type="checkbox" wire:model="moveSure" class="mt-0.5 size-4 accent-[var(--t-accent)]" @error('moveSure') aria-describedby="crunch-move-sure-error" @enderror>
                                                    <span>{{ __('shell.crunch.move_sure') }}<span class="block text-xs text-ink-faint">{{ __('shell.crunch.move_sure_hint') }}</span></span>
                                                </label>
                                                @error('moveSure') <p data-field-error role="alert" id="crunch-move-sure-error" class="text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="submit" class="{{ $btn }} bg-accent text-accent-fg" wire:loading.attr="disabled" wire:target="moveHard">
                                                        <span wire:loading.remove wire:target="moveHard">{{ __('shell.crunch.move_confirm') }}</span>
                                                        <span wire:loading wire:target="moveHard">{{ __('shell.crunch.saving') }}</span>
                                                    </button>
                                                    <button type="button" wire:click="cancelMove" class="{{ $plain }}" style="border: var(--t-bw) solid var(--t-line)">{{ __('shell.crunch.cancel') }}</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex flex-wrap gap-1.5">
                                                <button type="button" wire:click="shrink({{ $t->id }})" class="{{ $plain }}" style="border: var(--t-bw) solid var(--t-line)" wire:loading.attr="disabled" @disabled($item->smaller === null)>{{ __('shell.crunch.smaller') }}</button>
                                                @if ($item->hard)
                                                    {{-- "…" = asks first: the proposed day is only filled in, the move needs the clear yes. --}}
                                                    <button type="button" wire:click="startMove({{ $t->id }})" class="{{ $plain }}" style="border: var(--t-bw) solid var(--t-line)">{{ $item->fitDate ? __('shell.crunch.move_fit', ['date' => \App\Services\Planning\DayLabel::for($item->fitDate, $now)]) : __('shell.crunch.move_hard') }}</button>
                                                @elseif ($item->softMoveTo)
                                                    <button type="button" wire:click="moveSoft({{ $t->id }})" wire:loading.attr="disabled"
                                                        @class([$plain, 'font-semibold text-accent-ink' => $item->suggested])
                                                        style="border: var(--t-bw) solid {{ $item->suggested ? 'var(--t-accent)' : 'var(--t-line)' }}">{{ __('shell.crunch.move_soft', ['date' => \App\Services\Planning\DayLabel::for($item->softMoveTo, $now)]) }}</button>
                                                @endif
                                                <button type="button" wire:click="drop({{ $t->id }})" class="{{ $plain }}" style="border: var(--t-bw) solid var(--t-line)" wire:loading.attr="disabled">{{ __('shell.crunch.later') }}</button>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </div>

                {{-- Foot: "Mehr Zeit" for all (a one-off block this week only), and what can be undone. --}}
                <footer class="flex flex-col gap-3 px-5 py-4" style="border-top: var(--t-bw) solid var(--t-line); background: color-mix(in srgb, var(--t-ink) 3%, var(--t-surface))">
                    @if ($v->hasCrunch())
                        @if ($v->categories === [])
                            <p class="text-sm text-ink-soft">{{ __('shell.crunch.no_category') }}</p>
                        @elseif ($adding)
                            <form wire:submit="addTime" class="flex flex-col gap-2.5">
                                <span class="font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('shell.crunch.more_title') }}</span>
                                <div class="grid grid-cols-2 gap-2 wb:grid-cols-[1.3fr_1fr_1fr]">
                                    <label class="col-span-2 flex flex-col gap-1 text-xs text-ink-soft wb:col-span-1">{{ __('shell.crunch.add_day') }}
                                        <input type="date" wire:model="addDate" min="{{ $now->toDateString() }}" max="{{ $now->addDays(\App\Services\Planning\CrunchDecisions::MORE_TIME_DAYS - 1)->toDateString() }}"
                                            @class([$field, 'bg-surface', 'ring-2 ring-bad' => $errors->has('addDate')]) style="border: var(--t-bw) solid var(--t-line)">
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs text-ink-soft">{{ __('shell.crunch.add_from') }}
                                        <input type="time" wire:model="addStart" step="300" @class([$field, 'bg-surface', 'ring-2 ring-bad' => $errors->has('addEnd')]) style="border: var(--t-bw) solid var(--t-line)">
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs text-ink-soft">{{ __('shell.crunch.add_to') }}
                                        <input type="time" wire:model="addEnd" step="300" @class([$field, 'bg-surface', 'ring-2 ring-bad' => $errors->has('addEnd')]) style="border: var(--t-bw) solid var(--t-line)">
                                    </label>
                                </div>
                                @error('addDate') <p data-field-error role="alert" class="text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                @error('addEnd') <p data-field-error role="alert" class="text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                @if (count($v->categories) > 1)
                                    <label class="flex flex-col gap-1 text-xs text-ink-soft">{{ __('shell.crunch.add_category') }}
                                        <select wire:model="addCategory" @class([$field, 'bg-surface font-display', 'ring-2 ring-bad' => $errors->has('addCategory')]) style="border: var(--t-bw) solid var(--t-line)">
                                            @foreach ($v->categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                @error('addCategory') <p data-field-error role="alert" class="text-xs font-semibold text-bad">{{ $message }}</p> @enderror
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="{{ $btn }} bg-accent text-accent-fg" wire:loading.attr="disabled" wire:target="addTime">
                                        <span wire:loading.remove wire:target="addTime">{{ __('shell.crunch.add_confirm') }}</span>
                                        <span wire:loading wire:target="addTime">{{ __('shell.crunch.saving') }}</span>
                                    </button>
                                    <button type="button" wire:click="cancelAdd" class="{{ $plain }}" style="border: var(--t-bw) solid var(--t-line)">{{ __('shell.crunch.cancel') }}</button>
                                </div>
                                <p class="text-xs text-ink-faint">{{ __('shell.crunch.add_hint') }}</p>
                            </form>
                        @else
                            <button type="button" wire:click="startAdd" class="{{ $plain }} self-start" style="border: var(--t-bw) solid var(--t-line)">
                                @if ($v->moreTime)
                                    {{ trans_choice('shell.crunch.more', $v->moreTime['sessions'], ['count' => $v->moreTime['sessions'], 'day' => $v->moreTime['label'], 'start' => $v->moreTime['start'], 'end' => $v->moreTime['end']]) }}
                                @else
                                    {{ __('shell.crunch.more_plain') }}
                                @endif
                            </button>
                        @endif
                    @endif

                    @if ($undo !== [])
                        <ul class="flex flex-col gap-1.5 text-sm text-ink-soft" aria-label="{{ __('shell.crunch.undo') }}">
                            @foreach ($undo as $i => $entry)
                                <li class="flex flex-wrap items-baseline gap-x-2" wire:key="crunch-undo-{{ $i }}-{{ $entry['id'] }}">
                                    @include('shell.icon', ['name' => 'check', 'class' => 'size-3.5 self-center text-good'])
                                    <span class="min-w-0 wrap-break-word">{{ $entry['label'] }}</span>
                                    <button type="button" wire:click="undoAt({{ $i }})" class="font-semibold text-accent-ink underline decoration-1 underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.crunch.undo') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </footer>
            </section>
        </div>
    @endif
</div>
