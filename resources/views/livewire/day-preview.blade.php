{{-- Tagesüberblick — read-only, no wire: mutations on this page at all. Every
     number here is a fresh live read (App\Services\DayPreviewData), same
     "never a frozen snapshot" convention Fortschritt's own numbers follow. --}}
<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[12px] font-semibold uppercase tracking-wide text-ink-faint">
                {{ auth()->user()->localToday()->translatedFormat('l, j. F') }}
            </p>
            <h1 class="mt-1 text-[22px] font-semibold leading-snug text-ink">{{ $greeting }}</h1>
        </div>
        @if ($this->streakDays > 0)
            <div class="day-preview-arrive mt-0.5 flex flex-none items-center gap-1.5 rounded-full bg-overprint-soft px-3 py-1.5 text-[13px] font-semibold text-overprint">
                <x-flame-icon class="h-3.5 w-3.5" />
                <span class="tnum">{{ $this->streakDays }}</span>
            </div>
        @endif
    </div>

    @if ($this->emergency)
        {{-- Notfallmodus outranks everything else here too, exactly as it
             already does in the focus-timer suggestion and the header nav. --}}
        <div class="mt-6 rounded-card border border-signal bg-surface p-4 shadow-map">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-signal">Notfallmodus aktiv</p>
            <p class="mt-1 text-[16px] font-semibold text-ink">{{ $this->emergency['projectName'] }}</p>
            <p class="text-[12.5px] text-ink-soft">{{ $this->emergency['done'] }} von {{ $this->emergency['total'] }} Aufgaben erledigt</p>

            <div class="mt-4 flex items-center gap-1">
                @foreach ($this->emergency['nodes'] as $node)
                    @if ($node['kind'] === 'overflow')
                        <span class="tnum flex-none rounded-full bg-line px-1.5 py-0.5 text-[10px] font-semibold text-ink-soft" title="{{ $node['count'] }} weitere erledigte Schritte">+{{ $node['count'] }}</span>
                    @else
                        <span
                            @class([
                                'flex h-4 w-4 flex-none items-center justify-center rounded-full border-2',
                                'border-forest bg-forest' => $node['kind'] === 'done',
                                'border-overprint bg-surface' => $node['kind'] === 'current',
                                'border-line bg-surface' => $node['kind'] === 'upcoming',
                            ])
                            title="{{ $node['title'] }}"
                        >
                            @if ($node['kind'] === 'done')
                                <svg class="h-2 w-2 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </span>
                    @endif
                    @unless ($loop->last)
                        <span @class(['h-0.5 flex-1', 'bg-forest' => $node['kind'] === 'done', 'bg-line' => $node['kind'] !== 'done'])></span>
                    @endunless
                @endforeach
            </div>

            <div class="mt-3 space-y-1">
                @php $labels = collect($this->emergency['nodes'])->filter(fn ($n) => in_array($n['kind'], ['current', 'upcoming'], true))->take(3); @endphp
                @foreach ($labels as $label)
                    <p class="text-[12.5px] {{ $loop->first ? 'font-semibold text-ink' : 'text-ink-soft' }}">
                        {{ $loop->first ? 'Jetzt:' : 'Danach:' }} {{ $label['title'] }}
                    </p>
                @endforeach
            </div>
        </div>
    @else
        @if ($this->schedule['visible'] && count($this->schedule['blocks']) > 0)
            <div class="mt-6 rounded-card border border-line bg-surface p-4 shadow-map">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Zeitplan heute</p>
                    <a href="{{ route('schedule') }}" wire:navigate class="hit-area text-[11px] font-medium text-ink-soft transition hover:text-ink">Öffnen →</a>
                </div>
                <div class="mt-2">
                    @foreach ($this->schedule['blocks'] as $block)
                        @php
                            $barClass = match ($block['token']) {
                                'forest' => 'bg-forest',
                                'overprint' => 'bg-overprint',
                                'signal' => 'bg-signal',
                                'ink-faint' => 'bg-ink-faint/50',
                                'ink' => 'bg-ink-faint',
                                default => 'bg-contour',
                            };
                        @endphp
                        <div class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0">
                            <span class="{{ $barClass }} h-8 w-[3px] flex-none rounded-full"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13.5px] font-medium text-ink">{{ $block['title'] }}</span>
                                <span class="block text-[11.5px] text-ink-soft">{{ $block['time'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
                @if ($this->schedule['hasMore'])
                    <p class="mt-2 text-[11.5px] text-ink-soft">+{{ $this->schedule['moreCount'] }} weitere</p>
                @endif
            </div>
        @endif

        <div class="mt-4 grid grid-cols-2 gap-3">

            <a href="{{ route('app') }}" wire:navigate class="block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Fällig</p>
                <p @class([
                    'tnum mt-0.5 text-[26px] font-semibold',
                    'text-signal' => $this->due['count'] > 0,
                    'text-ink' => $this->due['count'] === 0,
                ])>{{ $this->due['count'] }}</p>

                @if (count($this->due['items']) > 0)
                    <div class="mt-1">
                        @foreach ($this->due['items'] as $item)
                            <div class="flex items-center gap-2 border-b border-line py-1.5 last:border-b-0">
                                <span @class([
                                    'flex h-5 w-5 flex-none items-center justify-center rounded-[7px]',
                                    'bg-signal' => $item['bucket'] === 'overdue',
                                    'bg-overprint' => $item['bucket'] === 'today',
                                    'bg-ink-faint' => $item['bucket'] === 'soon',
                                ])>
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[11.5px] font-medium text-ink">{{ $item['title'] }}</span>
                                    <span @class([
                                        'block text-[10px] font-semibold',
                                        'text-signal' => $item['bucket'] === 'overdue',
                                        'text-overprint' => $item['bucket'] === 'today',
                                        'text-ink-soft' => $item['bucket'] === 'soon',
                                    ])>{{ $item['tag'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    @if ($this->due['hasMore'])
                        <p class="mt-1 text-[11px] text-ink-soft">+{{ $this->due['moreCount'] }} weitere</p>
                    @endif
                @endif
            </a>

            @if ($this->todayIsEmpty)
                <a href="{{ route('prepare') }}" wire:navigate class="flex flex-col justify-center gap-1 rounded-card border border-dashed border-forest bg-forest-soft p-3.5 transition hover:opacity-90">
                    <span class="text-[13px] font-semibold text-forest">Jetzt vorbereiten →</span>
                    <span class="text-[11.5px] leading-snug text-ink-soft">Für heute ist noch nichts geplant.</span>
                </a>
            @else
                <a href="{{ route('app') }}" wire:navigate class="block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Für heute</p>
                    <p class="tnum mt-0.5 text-[26px] font-semibold text-ink">{{ $this->today['count'] }}</p>
                    <div class="mt-1">
                        @foreach ($this->today['items'] as $item)
                            <div class="flex items-center gap-2 border-b border-line py-1.5 last:border-b-0">
                                <svg class="h-4 w-4 flex-none text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
                                <span class="truncate text-[11.5px] font-medium text-ink">{{ $item['title'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($this->today['hasMore'])
                        <p class="mt-1 text-[11px] text-ink-soft">+{{ $this->today['moreCount'] }} weitere</p>
                    @endif
                </a>
            @endif

            @if ($this->agenda)
                <a href="{{ route('agenda') }}" wire:navigate class="block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Agenda</p>
                    <div class="mt-1.5">
                        @foreach ($this->agenda['items'] as $item)
                            <div class="flex items-center gap-2 border-b border-line py-1.5 last:border-b-0">
                                <span class="flex h-5 w-5 flex-none items-center justify-center rounded-[7px] bg-forest">
                                    <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5c3-1 5-1 8 0v13c-3-1-5-1-8 0V5z"/><path d="M20 5c-3-1-5-1-8 0v13c3-1 5-1 8 0V5z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[11.5px] font-medium text-ink">{{ $item['title'] }}</span>
                                    <span class="block text-[10px] text-ink-soft">{{ $item['subject'] }} · {{ $item['dateLabel'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    @if ($this->agenda['hasMore'])
                        <p class="mt-1 text-[11px] text-ink-soft">+{{ $this->agenda['moreCount'] }} weitere</p>
                    @endif
                </a>
            @endif

            @if ($this->craftIdea)
                <a href="{{ route('crafts') }}" wire:navigate class="block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Bastelidee</p>
                    <p class="mt-1.5 text-[13px] font-semibold text-ink">{{ $this->craftIdea['title'] }}</p>
                    @if ($this->craftIdea['note'])
                        <p class="mt-1 text-[11px] leading-snug text-ink-soft">{{ $this->craftIdea['note'] }}</p>
                    @endif
                </a>
            @endif

            <a href="{{ route('progress') }}" wire:navigate class="block rounded-card border border-line bg-surface p-3.5 shadow-map transition hover:border-ink-faint/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Tagesziel</p>
                @php
                    $goalDone = $this->goal['today'];
                    $goalTotal = max(1, $this->goal['goal']);
                    $pct = min(100, round(($goalDone / $goalTotal) * 100));
                    $circumference = round(2 * pi() * 21, 1);
                    $dash = round($circumference * $pct / 100, 1);
                @endphp
                <svg class="mt-1.5 h-11 w-11 -rotate-90" viewBox="0 0 52 52" aria-hidden="true">
                    <circle cx="26" cy="26" r="21" fill="none" stroke-width="6" class="stroke-line"/>
                    <circle cx="26" cy="26" r="21" fill="none" stroke-width="6" stroke-linecap="round"
                        class="stroke-forest transition-[stroke-dasharray] duration-500"
                        stroke-dasharray="{{ $dash }} {{ $circumference }}"
                    />
                </svg>
                <p class="mt-0.5 text-[11.5px] text-ink-soft">{{ $goalDone }} / {{ $this->goal['goal'] }} erledigt</p>
            </a>

        </div>
    @endif

    <a href="{{ route('app') }}" wire:navigate class="mt-6 block rounded-card bg-forest px-4 py-3 text-center text-[15px] font-semibold text-white shadow-map transition hover:opacity-90">
        Los geht's
    </a>

</div>
