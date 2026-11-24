{{-- The quiet Sunday review (step 4.4, variant A "two halves"): the reviewed week on the left, the next seven
     days on the right; stacked below 720 px (Stapel). Both layouts in one render, like "Ideen". Read-only. --}}
@php
    $r = $this->review;
    $past = $r['past'];
    $next = $r['next'];
    $locale = app()->getLocale();
    $day = fn (string $date, string $format) => \Illuminate\Support\Carbon::parse($date)->locale($locale)->isoFormat($format);
    $range = $day($r['monday'], 'D.').'–'.$day($r['sunday'], 'D. MMMM');
    $weekUrl = \App\Livewire\Shell\Review::weekUrl();
    $maxDone = max(4, ...array_column($past['days'], 'count'));
    $maxNext = max(4, ...array_column($next['days'], 'capacity'), ...array_column($next['days'], 'load'));
    $paused = array_values(array_filter($next['days'], fn ($d) => $d['paused']));
    $card = 'flex min-w-0 flex-col gap-4 rounded-panel bg-surface p-4 wb:p-5';
    $eyebrow = 'font-mono text-[10.5px] font-medium uppercase tracking-widest text-ink-faint';
    $btn = 'inline-flex min-h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-4 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    $chip = fn (array $row) => __($row['hard'] ? 'shell.review.hard_on' : 'shell.review.soft_on', ['date' => $day($row['date'], 'dd D.M.')]);
@endphp
<div id="content" class="min-h-dvh pb-[calc(88px+env(safe-area-inset-bottom))] wb:pb-0">
    <div class="hidden wb:block">@include('shell.topbar', ['active' => 'review'])</div>
    <header class="sticky top-0 z-20 flex items-center gap-2 bg-paper px-4 pb-2 pt-[max(12px,env(safe-area-inset-top))] wb:hidden">
        <a href="{{ route('shell.board') }}" wire:navigate class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" aria-label="{{ __('shell.review.back') }}">@include('shell.icon', ['name' => 'back', 'class' => 'size-5'])</a>
        <span class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">nothing-to-do</span>
    </header>

    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-2 wb:px-6 wb:py-8" data-review>
        <div class="flex flex-col gap-3">
            <div>
                <h1 class="font-display text-2xl text-ink" style="font-weight: var(--t-dw)">{{ __($r['isSunday'] ? 'shell.review.title' : 'shell.review.title_last') }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ __('shell.review.subtitle', ['today' => $day($r['today'], 'dddd, D. MMMM'), 'range' => $range]) }}</p>
            </div>
            <p class="max-w-[46ch] text-[17px] font-medium leading-snug text-ink wb:text-[19px]" data-review-sentence style="text-wrap: pretty">
                {{ __('shell.review.sentence.'.$past['kind'], ['total' => $past['total'], 'average' => $past['average']]) }}
            </p>
        </div>

        <div class="grid grid-cols-1 items-start gap-5 wb:grid-cols-2">
            {{-- ── The reviewed week ── --}}
            <section class="{{ $card }}" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="review-past" data-review-past>
                <h2 id="review-past" class="flex items-baseline gap-3 font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">
                    {{ __($r['isSunday'] ? 'shell.review.this_week' : 'shell.review.last_week') }}
                    <span class="{{ $eyebrow }} ml-auto">{{ $range }}</span>
                </h2>

                <div class="grid h-24 grid-cols-7 items-end gap-2" role="img"
                    aria-label="{{ __('shell.review.bars_label', ['days' => collect($past['days'])->map(fn ($d) => $day($d['date'], 'dd').' '.$d['count'])->join(', ')]) }}">
                    @foreach ($past['days'] as $d)
                        @php($isToday = $d['date'] === $r['today'])
                        <div class="grid h-full grid-rows-[1fr_auto] gap-1.5 text-center text-[11px] text-ink-faint" aria-hidden="true">
                            <div class="relative self-end rounded-t-[6px] rounded-b-[3px] {{ $d['count'] ? 'bg-accent' : 'bg-line' }}" style="height: {{ $d['count'] ? round($d['count'] / $maxDone * 100) : 3 }}%; min-height: 3px">
                                @if ($d['count'])<span class="absolute inset-x-0 -top-[17px] tabular-nums text-ink-soft">{{ $d['count'] }}</span>@endif
                            </div>
                            <span @class(['font-bold text-ink' => $isToday])>{{ $day($d['date'], 'dd') }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-ink-soft" data-review-moved>
                    <span><b class="mr-1 text-[15px] font-semibold tabular-nums text-ink">{{ $past['captured'] }}</b>{{ __('shell.review.captured') }}</span>
                    @if ($past['inboxNow'] > 0)
                        <span><b class="mr-1 text-[15px] font-semibold tabular-nums text-ink">{{ $past['inboxNow'] }}</b>{{ __('shell.review.inbox') }}</span>
                    @endif
                    <span><b class="mr-1 text-[15px] font-semibold tabular-nums text-ink">{{ $past['resets'] }}</b>{{ trans_choice('shell.review.resets', $past['resets']) }}</span>
                    @if ($past['ideas'] > 0)
                        <span><b class="mr-1 text-[15px] font-semibold tabular-nums text-ink">{{ $past['ideas'] }}</b>{{ trans_choice('shell.review.ideas', $past['ideas']) }}</span>
                    @endif
                </p>

                @if ($past['done'] === [])
                    <div class="flex flex-col items-center gap-1 py-3 text-center text-sm text-ink-soft" data-review-none-done>
                        <b class="text-[15px] text-ink">{{ __('shell.review.none_done') }}</b>
                        <span class="max-w-[40ch]">{{ __('shell.review.none_done_body') }}</span>
                    </div>
                @else
                    <div class="flex flex-col gap-2">
                        <span class="{{ $eyebrow }}">{{ __('shell.review.done') }}</span>
                        <ul class="flex flex-col" data-review-done>
                            @foreach ($past['done'] as $row)
                                <li class="flex min-w-0 items-baseline gap-2.5 py-2 text-sm first:pt-0" @if (! $loop->first) style="border-top: var(--t-bw) solid var(--t-line)" @endif>
                                    <span class="size-2 shrink-0 -translate-y-px rounded-full" style="background: {{ \App\Shell\TaskFace::areaColor($row['color']) }}" @if ($row['area']) title="{{ $row['area'] }}" @endif></span>
                                    <span class="min-w-0 flex-1 wrap-break-word text-ink">{{ $row['title'] }}</span>
                                    <span class="shrink-0 font-mono text-[11px] text-ink-faint">{{ $day($row['date'], 'dd') }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($past['doneMore'] > 0)
                            <span class="text-[12.5px] text-ink-faint">{{ __('shell.review.done_more', ['count' => $past['doneMore']]) }}</span>
                        @endif
                    </div>
                @endif

                @if ($past['carried'] !== [])
                    <div class="flex flex-col gap-2" data-review-carried>
                        <span class="{{ $eyebrow }}">{{ __('shell.review.carried') }}</span>
                        @include('shell.review-dated', ['rows' => $past['carried'], 'more' => $past['carriedMore'], 'chip' => $chip])
                    </div>
                @endif
            </section>

            {{-- ── The next seven days ── --}}
            <section class="{{ $card }}" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="review-next" data-review-next>
                <h2 id="review-next" class="flex items-baseline gap-3 font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">
                    {{ __($r['isSunday'] ? 'shell.review.next_week' : 'shell.review.this_week') }}
                    <span class="{{ $eyebrow }} ml-auto">{{ $day($next['days'][0]['date'], 'D.').'–'.$day($next['days'][6]['date'], 'D. MMMM') }}</span>
                </h2>

                @if (! $next['hasBlocks'] && $next['load'] === 0)
                    <div class="flex flex-col items-start gap-2 rounded-card border border-dashed border-line p-4 text-sm text-ink-soft" data-review-no-blocks>
                        <b class="text-[15px] text-ink">{{ __('shell.review.no_blocks') }}</b>
                        <span class="max-w-[46ch]">{{ __('shell.review.no_blocks_body') }}</span>
                        <a href="{{ $weekUrl }}" class="{{ $btn }} mt-1 bg-accent text-accent-fg hover:brightness-110">{{ __('shell.review.plan_week') }}</a>
                    </div>
                @else
                    @if ($next['tight'])
                        <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-card px-3.5 py-2.5 text-[13.5px] text-ink" style="background: color-mix(in srgb, var(--t-warn) 12%, var(--t-surface))" data-review-tight>
                            <span class="min-w-0 flex-1">{{ __('shell.review.'.($next['tight']['over'] === 1 ? 'tight_one' : 'tight'), ['day' => $day($next['tight']['date'], 'dddd'), 'count' => $next['tight']['over']]) }}</span>
                            <a href="{{ route('shell.board', ['crunch' => 1]) }}" wire:navigate class="font-semibold text-warn underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{{ __('shell.review.tight_look') }}</a>
                        </p>
                    @else
                        <p class="text-[13.5px] text-ink-soft" data-review-calm>
                            {{ __('shell.review.planned', ['load' => $next['load'], 'capacity' => $next['capacity']]) }}
                            @if ($paused !== []) {{ __('shell.review.paused', ['days' => collect($paused)->map(fn ($d) => $day($d['date'], 'dd'))->join(', ')]) }} @endif
                            @if ($next['load'] < $next['capacity']) {{ __('shell.review.room') }} @endif
                        </p>
                    @endif

                    <div class="grid h-28 grid-cols-7 items-end gap-2" role="img"
                        aria-label="{{ __('shell.review.room_label', ['days' => collect($next['days'])->map(fn ($d) => $day($d['date'], 'dd').' '.($d['paused'] ? __('shell.review.day_paused') : ($d['past'] ? __('shell.review.day_past') : $d['load'].'/'.$d['capacity'])))->join(', ')]) }}">
                        @foreach ($next['days'] as $d)
                            @php($top = max($d['capacity'], $d['load']))
                            <div class="grid h-full grid-rows-[1fr_auto_auto] gap-1 text-center text-[11px] text-ink-faint" aria-hidden="true">
                                @if ($d['paused'])
                                    <div class="grid h-[22px] place-items-center self-end rounded-[6px] border-[1.5px] border-dashed border-line font-mono text-[9.5px]">{{ __('shell.review.day_paused') }}</div>
                                @elseif ($d['past'] || $top === 0)
                                    <div class="h-1 self-end rounded-[2px] bg-line"></div>
                                @else
                                    <div class="flex flex-col-reverse self-end overflow-hidden rounded-[6px] bg-surface"
                                        style="height: {{ round($top / $maxNext * 100) }}%; border: 1.5px solid {{ $d['over'] > 0 ? 'var(--t-warn)' : 'var(--t-line)' }}">
                                        <div style="height: {{ round($d['load'] / $top * 100) }}%; background: color-mix(in srgb, {{ $d['over'] > 0 ? 'var(--t-warn)' : 'var(--t-accent)' }} 55%, transparent)"></div>
                                    </div>
                                @endif
                                <span class="text-[10.5px] tabular-nums text-ink-soft">
                                    @if ($d['paused'])–@elseif ($d['past']){{ __('shell.review.day_past') }}@elseif ($top === 0){{ __('shell.review.day_free') }}@else{{ $d['load'] }}/{{ $d['capacity'] }}@endif
                                </span>
                                <span>{{ $day($d['date'], 'dd') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="flex flex-wrap gap-x-4 gap-y-1 text-[11.5px] text-ink-faint">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-surface" style="border: 1.5px solid var(--t-line)"></span>{{ __('shell.review.legend_room') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px]" style="background: color-mix(in srgb, var(--t-accent) 55%, transparent)"></span>{{ __('shell.review.legend_load') }}</span>
                        <span class="font-mono">{{ __('shell.review.legend_numbers') }}</span>
                    </p>
                @endif

                @if ($next['due'] !== [])
                    <div class="flex flex-col gap-2" data-review-due>
                        <span class="{{ $eyebrow }}">{{ __('shell.review.due') }}</span>
                        @include('shell.review-dated', ['rows' => $next['due'], 'more' => $next['dueMore'], 'chip' => $chip])
                    </div>
                @endif
            </section>
        </div>

        <div class="flex flex-wrap gap-2.5">
            <a href="{{ $weekUrl }}" class="{{ $btn }} bg-accent text-accent-fg hover:brightness-110">{{ __('shell.review.adjust_week') }}</a>
            <a href="{{ route('shell.board') }}" wire:navigate class="{{ $btn }} border border-line bg-surface text-ink-soft hover:bg-paper hover:text-ink">{{ __('shell.review.back') }}</a>
        </div>
    </main>

    {{-- Stapel: the bottom tabs (no tab is this page). --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-surface px-2 pt-2 pb-[max(14px,env(safe-area-inset-bottom))] wb:hidden" style="border-top: var(--t-bw) solid var(--t-line)" aria-label="{{ __('shell.nav.board') }}">
        @php($tab = 'flex min-w-16 flex-col items-center gap-0.5 rounded-card px-2 py-1 text-[11px] font-semibold text-ink-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent')
        <a href="{{ route('shell.board') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'desk'])<span>{{ __('shell.tabs.desk') }}</span></a>
        <a href="{{ $weekUrl }}" class="{{ $tab }}">@include('shell.icon', ['name' => 'calendar'])<span>{{ __('shell.tabs.calendar') }}</span></a>
        <a href="{{ route('shell.ideas') }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'later'])<span>{{ __('shell.tabs.later') }}</span></a>
        <a href="{{ route('shell.board', ['open' => 'inbox']) }}" wire:navigate class="{{ $tab }}">@include('shell.icon', ['name' => 'inbox'])<span>{{ __('shell.tabs.inbox') }}</span></a>
    </nav>
</div>
