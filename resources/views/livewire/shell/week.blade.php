{{--
    The week plan editor (step 4.1): Zeitplan (the real week) and Wochenplan (what repeats), both layouts in one
    render. Werkbank from 720 px: top bar, a 7-day time grid, the Wochenplan's summary on the right. Stapel below:
    header with the Zeitplan / Wochenplan switch, a day strip and the same grid showing one day, cards placed by
    time and as tall as they last. Drawing, moving, the popover form and the pause calendar: weekEditor (week-editor.js).
--}}
@php
    $isPlan = $view === 'plan';
    $columns = $this->columns;
    $categories = $this->categories;
    $dates = $this->dates;
    $today = $this->today;
    $todayIndex = array_search($today, $dates, true);
    $firstHour = 7;
    foreach ($columns as $c) {
        foreach ($c['blocks'] as $b) {
            $firstHour = min($firstHour, intdiv($b['top'], 60));
        }
    }
    $weekdays = array_map(fn ($c) => $c['weekday'], $columns);
    $btn = 'inline-flex min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-3.5 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-not-allowed disabled:opacity-50';
    $chip = 'inline-flex min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    $iconBtn = 'grid size-9 shrink-0 place-items-center rounded-full border border-line bg-surface text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    $seg = fn (bool $on) => 'rounded-full px-3.5 py-1.5 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent '.($on ? 'bg-accent text-accent-fg' : 'text-ink-soft hover:text-ink');
    $short = fn (string $d) => \Carbon\CarbonImmutable::parse($d)->format('j.n.');
    $months = array_map(fn ($m) => __('week.months.'.$m), range(1, 12));
@endphp
<div id="content" class="min-h-dvh"
    x-data="weekEditor({ view: @js($view), snap: {{ \App\Livewire\Shell\Week::SNAP }}, day: {{ $isPlan ? max(0, \Carbon\CarbonImmutable::parse($today)->dayOfWeekIso - 1) : ($todayIndex === false ? 0 : $todayIndex) }}, firstHour: {{ $firstHour }}, month: @js(substr($isPlan ? $today : ($todayIndex === false ? $dates[0] : $today), 0, 7)), months: @js($months), daysLabel: @js(__('week.grid.days_label')) })"
    @pointermove.window="move($event)" @pointerup.window="drag && (drag.active ? up($event) : tap($event))" @pointercancel.window="cancel()"
    @keydown.escape.window="if ($wire.form) $wire.closeForm(); else if ($wire.pauseOpen) $wire.closePause()"
    data-week-view="{{ $view }}">

    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'week'])</div>

    {{-- Stapel header: back, title, Zeitplan / Wochenplan, week and day. --}}
    <header class="sticky top-0 z-20 flex flex-col gap-2.5 bg-paper px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <div class="flex items-center gap-2">
            <a href="{{ route('shell.board') }}" wire:navigate class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('week.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
            <h1 class="min-w-0 flex-1 truncate font-display text-xl text-ink" style="font-weight: var(--t-dw)">{{ $isPlan ? __('week.plan.title') : __('week.zeit.title') }}</h1>
            <nav class="flex rounded-full bg-surface p-0.5" style="border: var(--t-bw) solid var(--t-line)" aria-label="{{ __('week.switch') }}">
                <a href="{{ route('shell.week') }}" wire:navigate class="{{ $seg(! $isPlan) }} !px-2.5 !py-1 !text-xs" @if (! $isPlan) aria-current="page" @endif>{{ __('week.zeit.title') }}</a>
                <a href="{{ route('shell.week', ['view' => 'plan']) }}" wire:navigate class="{{ $seg($isPlan) }} !px-2.5 !py-1 !text-xs" @if ($isPlan) aria-current="page" @endif>{{ __('week.plan.title') }}</a>
            </nav>
        </div>
        @unless ($isPlan)
            <div class="flex items-center gap-2">
                <button type="button" wire:click="goWeek(-1)" class="{{ $iconBtn }}" aria-label="{{ __('week.zeit.previous') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4'])</button>
                <p class="flex-1 text-center font-mono text-[13px] text-ink">{{ $short($dates[0]) }} – {{ $short($dates[6]) }}@if ($todayIndex === false) <button type="button" wire:click="goWeek(0)" class="ml-1 text-xs font-semibold text-accent-ink underline underline-offset-4">{{ __('week.zeit.today') }}</button>@endif</p>
                <button type="button" wire:click="goWeek(1)" class="{{ $iconBtn }}" aria-label="{{ __('week.zeit.next') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4 rotate-180'])</button>
            </div>
        @endunless
        <div class="grid grid-cols-7 gap-1" role="tablist" aria-label="{{ __('week.grid.days') }}">
            @foreach ($columns as $i => $col)
                <button type="button" role="tab" @click="day = {{ $i }}" :aria-selected="day === {{ $i }}"
                    :class="day === {{ $i }} ? '!bg-ink !text-surface !border-ink' : ''"
                    class="flex flex-col items-center gap-px rounded-card bg-surface py-1.5 text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                    style="border: var(--t-bw) solid var(--t-line);{{ $col['paused'] ? ' background-image: repeating-linear-gradient(135deg, color-mix(in srgb, var(--t-warn) 16%, transparent) 0 5px, transparent 5px 10px)' : '' }}">
                    <span class="text-[10.5px] font-semibold opacity-75">{{ __('week.days.'.$col['weekday']) }}</span>
                    @unless ($isPlan)<span class="font-mono text-sm {{ $col['date'] === $today ? 'font-bold' : '' }}">{{ \Carbon\CarbonImmutable::parse($col['date'])->day }}</span>@endunless
                    <span class="font-mono text-[9.5px] opacity-75">{{ $col['paused'] ? __('week.grid.paused_short') : $col['sessions'].'×' }}</span>
                </button>
            @endforeach
        </div>
    </header>

    <div class="mx-auto flex max-w-[1500px] wb:px-6">
        <main class="flex min-w-0 flex-1 flex-col gap-3 px-4 pt-2 pb-[calc(96px+env(safe-area-inset-bottom))] wb:px-0 wb:py-5">
            {{-- Werkbank head row --}}
            <div class="hidden flex-wrap items-center gap-x-3 gap-y-2 wb:flex">
                <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ $isPlan ? __('week.plan.title') : __('week.zeit.title') }}</h1>
                <nav class="flex rounded-full bg-surface p-0.5" style="border: var(--t-bw) solid var(--t-line)" aria-label="{{ __('week.switch') }}">
                    <a href="{{ route('shell.week') }}" wire:navigate class="{{ $seg(! $isPlan) }}" @if (! $isPlan) aria-current="page" @endif>{{ __('week.zeit.title') }}</a>
                    <a href="{{ route('shell.week', ['view' => 'plan']) }}" wire:navigate class="{{ $seg($isPlan) }}" @if ($isPlan) aria-current="page" @endif>{{ __('week.plan.title') }}</a>
                </nav>
                @if ($isPlan)
                    <p class="text-sm text-ink-soft">{{ __('week.plan.intro') }}</p>
                @else
                    <div class="flex items-center gap-1.5">
                        <button type="button" wire:click="goWeek(-1)" class="{{ $iconBtn }}" aria-label="{{ __('week.zeit.previous') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4'])</button>
                        <span class="min-w-28 text-center font-mono text-sm text-ink">{{ $short($dates[0]) }} – {{ $short($dates[6]) }}</span>
                        <button type="button" wire:click="goWeek(1)" class="{{ $iconBtn }}" aria-label="{{ __('week.zeit.next') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4 rotate-180'])</button>
                        @if ($todayIndex === false)
                            <button type="button" wire:click="goWeek(0)" class="{{ $btn }} min-h-9 text-ink-soft hover:text-ink">{{ __('week.zeit.today') }}</button>
                        @endif
                    </div>
                @endif
                <span class="flex-1"></span>
                @unless ($isPlan)
                    <x-shell.action key="pause_days" layout="werkbank" mode="board" wire:click="openPause" class="{{ $btn }} border border-line bg-surface text-ink hover:bg-paper">
                        @include('shell.icon', ['name' => 'pause', 'class' => 'size-4']){{ __('week.pause.open') }}
                    </x-shell.action>
                @endunless
                <button type="button" @click="anchor = null; $wire.openNew([{{ $isPlan ? 1 : \Carbon\CarbonImmutable::parse($todayIndex === false ? $dates[0] : $today)->dayOfWeekIso }}], '18:00', '19:00')"
                    class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110" data-add-block>
                    @include('shell.icon', ['name' => 'plus', 'class' => 'size-4']){{ __('week.grid.add') }}
                </button>
            </div>

            @if ($this->blank)
                <section class="flex flex-col items-start gap-2 rounded-panel border border-dashed border-line bg-surface/60 p-5" data-week-empty>
                    <h2 class="font-display text-lg text-ink" style="font-weight: var(--t-dw)">{{ __('week.empty.title') }}</h2>
                    <p class="max-w-[64ch] text-sm text-ink-soft">{{ __('week.empty.body') }}</p>
                    <p class="text-sm text-ink-soft"><span class="hidden wb:inline">{{ __('week.empty.draw_desk') }}</span><span class="wb:hidden">{{ __('week.empty.draw_phone') }}</span></p>
                    @if ($categories->isEmpty())
                        <a href="{{ route('shell.settings') }}#kategorien" wire:navigate class="text-sm font-semibold text-accent-ink underline underline-offset-4">{{ __('week.empty.categories') }}</a>
                    @endif
                </section>
            @endif

            {{-- The time grid. --ph is one hour in pixels; blocks are placed with it, so both layouts share the markup. --}}
            <div x-ref="scroller" class="relative overflow-y-auto overscroll-contain rounded-panel bg-surface [--ph:48px] wb:[--ph:40px] max-wb:h-[calc(100dvh-230px-env(safe-area-inset-bottom))] wb:max-h-[calc(100dvh-190px)]"
                style="border: var(--t-bw) solid var(--t-line)" data-week-grid>
                <div class="sticky top-0 z-20 hidden grid-cols-[44px_repeat(7,minmax(0,1fr))] bg-surface wb:grid" style="border-bottom: var(--t-bw) solid var(--t-line)">
                    <span></span>
                    @foreach ($columns as $col)
                        @php($label = __('week.days.'.$col['weekday']).($isPlan ? '' : ' '.$short($col['date'])))
                        @php($cap = $col['paused'] ? __('week.grid.paused').($col['note'] ? ' · '.$col['note'] : '') : trans_choice('week.grid.sessions', $col['sessions'], ['count' => $col['sessions']]))
                        @if ($isPlan)
                            <div class="flex min-w-0 flex-col px-2 py-2" style="border-left: var(--t-bw) solid var(--t-line)">
                                <span class="text-[13px] font-semibold text-ink">{{ __('week.days_long.'.$col['weekday']) }}</span>
                                <span class="font-mono text-[10.5px] text-ink-faint">{{ $cap }}</span>
                            </div>
                        @else
                            <button type="button" wire:click="togglePause('{{ $col['date'] }}')" wire:loading.attr="disabled" wire:target="togglePause('{{ $col['date'] }}')"
                                title="{{ $col['paused'] ? __('week.grid.resume_day') : __('week.grid.pause_day') }}"
                                class="flex min-w-0 flex-col px-2 py-2 text-left transition hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent"
                                style="border-left: var(--t-bw) solid var(--t-line);{{ $col['paused'] ? ' background-image: repeating-linear-gradient(135deg, color-mix(in srgb, var(--t-warn) 12%, transparent) 0 6px, transparent 6px 12px)' : '' }}"
                                @if ($col['date'] === $today) aria-current="date" @endif>
                                <span class="text-[13px] font-semibold {{ $col['date'] === $today ? 'text-accent-ink' : 'text-ink' }}">{{ $label }}</span>
                                <span class="truncate font-mono text-[10.5px] {{ $col['paused'] ? 'text-warn' : 'text-ink-faint' }}">{{ $cap }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="grid grid-cols-[40px_minmax(0,1fr)] wb:grid-cols-[44px_repeat(7,minmax(0,1fr))]" @pointerdown="down($event)">
                    <div class="relative h-[calc(var(--ph)*24)]" aria-hidden="true">
                        @for ($h = 1; $h < 24; $h++)
                            <span class="absolute right-1.5 -translate-y-1/2 font-mono text-[10px] text-ink-faint" style="top: calc(var(--ph) * {{ $h }})">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</span>
                        @endfor
                    </div>
                    @foreach ($columns as $i => $col)
                        <div data-col data-key="{{ $col['key'] }}" data-weekday="{{ $col['weekday'] }}" data-paused="{{ $col['paused'] ? 1 : 0 }}" wire:key="col-{{ $view }}-{{ $col['key'] }}"
                            :class="day === {{ $i }} ? '' : 'max-wb:hidden'" class="relative h-[calc(var(--ph)*24)] cursor-crosshair touch-pan-y select-none"
                            style="border-left: var(--t-bw) solid var(--t-line); background-image: linear-gradient(var(--t-line) 1px, transparent 1px){{ $col['paused'] ? ', repeating-linear-gradient(135deg, color-mix(in srgb, var(--t-warn) 9%, transparent) 0 6px, transparent 6px 12px)' : '' }}; background-size: 100% var(--ph){{ $col['paused'] ? ', auto' : '' }}"
                            aria-label="{{ __('week.days_long.'.$col['weekday']) }}{{ $isPlan ? '' : ' '.$short($col['date']) }}">
                            @if ($col['paused'])
                                <span class="pointer-events-none absolute inset-x-1 top-[calc(var(--ph)*8)] text-center font-mono text-[10.5px] uppercase tracking-widest text-warn">{{ __('week.grid.paused') }}</span>
                            @endif
                            @if ($col['date'] === $today)
                                @php($nowMin = \App\Support\UserClock::now($this->user)->hour * 60 + \App\Support\UserClock::now($this->user)->minute)
                                <span class="pointer-events-none absolute inset-x-0 z-10 h-0.5 bg-warn" style="top: calc(var(--ph) * {{ round($nowMin / 60, 3) }})" aria-hidden="true"></span>
                            @endif
                            @foreach ($col['blocks'] as $block)
                                @php($termin = $block['category'] === null)
                                @php($w = 100 / $block['lanes'])
                                <button type="button" data-block data-kind="{{ $block['kind'] }}" data-id="{{ $block['id'] }}" data-top="{{ $block['top'] }}" data-length="{{ $block['length'] }}"
                                    wire:key="b-{{ $block['kind'] }}-{{ $block['id'] }}-{{ $col['key'] }}-{{ $block['top'] }}"
                                    @click="if ($event.detail === 0) { anchor = $el.getBoundingClientRect(); $wire.openBlock(@js($block['kind']), {{ $block['id'] }}, @js($col['key'])) }"
                                    @class([
                                        'absolute z-[1] flex flex-col gap-px overflow-hidden rounded-[7px] px-1.5 py-1 text-left text-[11.5px] leading-tight transition-opacity focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ink cursor-grab',
                                        'bg-deep text-on-deep' => $termin,
                                        'text-ink' => ! $termin,
                                        'ring-2 ring-ink ring-offset-1 ring-offset-surface' => $form && ($form['id'] ?? null) === $block['id'] && ($form['mode'] ?? null) === $block['kind'] && (($form['date'] ?? null) === $block['date'] || $block['kind'] === 'tpl'),
                                    ])
                                    style="top: calc(var(--ph) * {{ round($block['top'] / 60, 4) }}); height: max(18px, calc(var(--ph) * {{ round($block['length'] / 60, 4) }} - 2px)); left: calc({{ $block['lane'] * $w }}% + 3px); width: calc({{ $w }}% - 6px);{{ $termin ? '' : ' background: color-mix(in srgb, var(--color-'.$block['color'].') '.($block['pomodoro'] ? 20 : 13).'%, var(--t-surface));' }} border: 1px {{ $block['changed'] ? 'dashed' : 'solid' }} {{ $termin ? 'var(--t-deep)' : 'var(--color-'.$block['color'].')' }}"
                                    title="{{ $block['start'] }}–{{ $block['end'] }} {{ $block['title'] }}"
                                    aria-label="{{ $block['start'] }}–{{ $block['end'] }} {{ $block['title'] }}{{ $block['changed'] ? ', '.__('week.grid.changed') : '' }}">
                                    <span class="font-mono text-[10px] opacity-80">{{ $block['start'] }}–{{ $block['end'] }}@if ($block['changed']) · {{ __('week.grid.changed') }}@endif</span>
                                    <b class="line-clamp-2 font-semibold wrap-anywhere">{{ $block['title'] }}</b>
                                    @if ($block['pomodoro'] && $block['length'] >= 50)
                                        <span class="font-mono text-[9.5px] uppercase tracking-wider opacity-80">{{ trans_choice('week.grid.sessions', $block['sessions'], ['count' => $block['sessions']]) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="text-xs text-ink-faint">
                <span class="hidden wb:inline">{{ $isPlan ? __('week.grid.hint_plan_desk') : __('week.grid.hint_desk') }}</span>
                <span class="wb:hidden">{{ __('week.grid.hint_phone') }}</span>
            </p>

            {{-- Stapel: pause entry points under the grid. --}}
            @unless ($isPlan)
                <div class="flex flex-wrap gap-2 wb:hidden">
                    @foreach ($columns as $i => $col)
                        <button type="button" x-show="day === {{ $i }}" x-cloak wire:click="togglePause('{{ $col['date'] }}')" class="{{ $btn }} border border-line bg-surface text-ink">
                            {{ $col['paused'] ? __('week.grid.resume_named', ['day' => __('week.days.'.$col['weekday'])]) : __('week.grid.pause_named', ['day' => __('week.days.'.$col['weekday'])]) }}
                        </button>
                    @endforeach
                    <x-shell.action key="pause_days" layout="stapel" mode="board" wire:click="openPause" class="{{ $btn }} border border-line bg-surface text-ink">
                        @include('shell.icon', ['name' => 'pause', 'class' => 'size-4']){{ __('week.pause.holidays') }}
                    </x-shell.action>
                </div>
            @endunless
        </main>

        {{-- Wochenplan: the week in numbers (desktop). --}}
        @if ($isPlan)
            @php($summary = $this->summary)
            <aside class="ml-6 hidden w-64 shrink-0 flex-col gap-3 py-5 wb:flex" data-week-summary>
                <h2 class="font-display text-base text-ink" style="font-weight: var(--t-dw)">{{ __('week.plan.summary') }}</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($summary['categories'] as $row)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="size-3 shrink-0 rounded-full" style="background: var(--color-{{ $row['color'] }})"></span>
                            <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $row['name'] }}</span>
                            <span class="font-mono text-xs text-ink-soft tabular-nums">{{ str_replace('.', ',', (string) round($row['minutes'] / 60, 1)) }} h{{ $row['pomodoro'] ? ' · '.$row['sessions'].' S.' : '' }}</span>
                        </li>
                    @endforeach
                    @foreach ($summary['termine'] as $row)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="size-3 shrink-0 rounded-full bg-deep"></span>
                            <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $row['title'] }}</span>
                            <span class="font-mono text-xs text-ink-soft">{{ implode(' ', array_map(fn ($d) => __('week.days.'.$d), $row['days'])) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($summary['categories'] === [] && $summary['termine'] === [])
                    <p class="text-sm text-ink-faint">{{ __('week.plan.summary_empty') }}</p>
                @endif
                <p class="text-xs text-ink-faint">{{ __('week.plan.summary_hint') }}</p>
                <a href="{{ route('shell.settings') }}#kategorien" wire:navigate class="text-sm font-semibold text-accent-ink underline underline-offset-4">{{ __('week.plan.edit_categories') }}</a>
            </aside>
        @endif
    </div>

    {{-- The block form: a popover next to the block, centred when it does not fit, a bottom sheet on the phone. --}}
    @if ($form)
        @include('shell.week-form', ['form' => $form, 'categories' => $categories, 'dates' => $dates, 'view' => $view, 'btn' => $btn, 'chip' => $chip, 'short' => $short])
    @endif

    {{-- Pause days: a calendar range in two taps. --}}
    @if ($pauseOpen)
        <div class="fixed inset-0 z-40 bg-black/30" wire:click="closePause" aria-hidden="true"></div>
        <section class="fixed z-50 flex flex-col gap-3 bg-surface p-5 shadow-card max-wb:inset-x-0 max-wb:bottom-0 max-wb:max-h-[88dvh] max-wb:overflow-y-auto max-wb:rounded-t-panel max-wb:pb-[max(20px,env(safe-area-inset-bottom))] wb:right-6 wb:top-20 wb:w-96 wb:rounded-panel"
            style="border: var(--t-bw) solid var(--t-line)" role="dialog" aria-modal="true" aria-labelledby="pause-title" data-pause-sheet>
            <div class="flex items-center justify-between gap-2">
                <h2 id="pause-title" class="font-display text-lg text-ink" style="font-weight: var(--t-dw)">{{ __('week.pause.title') }}</h2>
                <button type="button" wire:click="closePause" class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('week.cancel') }}">@include('shell.icon', ['name' => 'close', 'class' => 'size-5'])</button>
            </div>
            <p class="text-sm text-ink-soft">{{ __('week.pause.intro') }}</p>
            <div class="flex items-center justify-between">
                <button type="button" @click="shiftMonth(-1)" class="{{ $iconBtn }}" aria-label="{{ __('week.pause.previous') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4'])</button>
                <b class="text-sm text-ink" x-text="monthLabel()"></b>
                <button type="button" @click="shiftMonth(1)" class="{{ $iconBtn }}" aria-label="{{ __('week.pause.next') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-4 rotate-180'])</button>
            </div>
            <div class="grid grid-cols-7 gap-0.5 text-center" role="grid">
                @foreach (range(1, 7) as $d)
                    <span class="py-0.5 font-mono text-[10px] text-ink-faint">{{ __('week.days.'.$d) }}</span>
                @endforeach
                <template x-for="c in cells()" :key="c.iso">
                    <button type="button" @click="pick(c.iso)" :aria-pressed="isEdge(c.iso) || inRange(c.iso)" :aria-label="c.iso"
                        class="rounded-[7px] py-2 font-mono text-[13px] transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                        :class="[c.out ? 'text-ink-faint opacity-50' : 'text-ink', isEdge(c.iso) ? '!bg-warn !text-surface !opacity-100' : (inRange(c.iso) ? 'rounded-none !opacity-100' : 'hover:bg-paper'), c.iso === @js($today) ? 'ring-1 ring-inset ring-accent' : '']"
                        :style="inRange(c.iso) && !isEdge(c.iso) ? 'background: color-mix(in srgb, var(--t-warn) 16%, var(--t-surface))' : ''"
                        x-text="c.n"></button>
                </template>
            </div>
            <p class="text-sm text-ink-soft" aria-live="polite">
                <span x-show="!pause.from">{{ __('week.pause.pick_first') }}</span>
                <span x-show="pause.from && !pause.to" x-cloak>{{ __('week.pause.pick_last') }}</span>
                <span x-show="pause.from && pause.to" x-cloak x-text="@js(__('week.pause.chosen')).replace(':count', dayCount())"></span>
            </p>
            @error('pause.from')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            @error('pause.to')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            <label class="flex flex-col gap-1 text-xs font-medium text-ink-soft">
                {{ __('week.pause.note') }}
                <input type="text" x-model="pause.note" maxlength="255" placeholder="{{ __('week.pause.note_placeholder') }}" class="h-10 rounded-card border border-line bg-paper px-3 text-sm text-ink focus:border-accent focus:outline-hidden">
            </label>
            @error('pause.note')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="savePause()" :disabled="!pause.from" wire:loading.attr="disabled" wire:target="pauseRange" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110" data-pause-save>
                    <span wire:loading.remove wire:target="pauseRange">{{ __('week.pause.save') }}</span>
                    <span wire:loading wire:target="pauseRange">{{ __('week.saving') }}</span>
                </button>
                <button type="button" wire:click="closePause" class="{{ $btn }} text-ink-soft hover:text-ink">{{ __('week.cancel') }}</button>
            </div>
        </section>
    @endif

    {{-- A calm line after every change, with "Rückgängig" (never a confirm). --}}
    @if ($notice)
        <div class="pointer-events-none fixed inset-x-0 z-50 flex justify-center px-4 bottom-[calc(96px+env(safe-area-inset-bottom))] wb:bottom-6" role="status" wire:key="notice-{{ md5($notice) }}">
            <p class="pointer-events-auto flex max-w-xl items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card">
                <span class="min-w-0 wrap-break-word">{{ $notice }}</span>
                @if ($undo)
                    <button type="button" wire:click="undoLast" wire:loading.attr="disabled" class="shrink-0 font-semibold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" data-undo>{{ __('week.undo') }}</button>
                @endif
                <button type="button" wire:click="dismiss" class="shrink-0 rounded-full p-1 opacity-80 hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep" aria-label="OK">@include('shell.icon', ['name' => 'close', 'class' => 'size-4'])</button>
            </p>
        </div>
    @endif

    {{-- Stapel: + adds a block on the shown day, bottom tabs ("Kalender" is this page). --}}
    <button type="button" x-show="!$wire.form" @click="anchor = null; $wire.openNew([@js($weekdays)[day]], '18:00', '19:00')" aria-label="{{ __('week.grid.add') }}"
        class="fixed right-4 bottom-[calc(84px+env(safe-area-inset-bottom))] z-30 grid size-14 place-items-center rounded-full bg-accent text-accent-fg shadow-card transition active:scale-95 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent wb:hidden">
        @include('shell.icon', ['name' => 'plus', 'class' => 'size-6'])
    </button>
    <nav class="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-2 pt-2 pb-[max(14px,env(safe-area-inset-bottom))] wb:hidden" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
        @php($tab = 'flex min-w-16 flex-col items-center gap-0.5 rounded-card px-2 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
        <a href="{{ route('shell.board') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></a>
        <a href="{{ route('shell.week') }}" wire:navigate class="{{ $tab }} text-accent-ink" aria-current="page">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></a>
        <a href="{{ route('shell.ideas') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></a>
        <a href="{{ route('shell.board', ['open' => 'inbox']) }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></a>
    </nav>
</div>
