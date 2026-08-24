{{--
    One header badge — an ambient shortcut (icon + short text) that links to
    the page it's about. $badge comes from App\Services\HeaderBadges::visibleFor()
    and is only ever passed here once its resolver found something to show, so
    this partial never has to render an empty/zero state itself.

    Colour: every badge except 'streak' uses its catalog 'tone' at a flat
    "soft" strength ('ink' = neutral border, 'signal' = the Notfall/warning
    tint used everywhere else that badge appears). 'streak' keeps its
    pre-existing tiered escalation (capped at forest) via $badge['tier'].
--}}
<a
    href="{{ $badge['href'] }}"
    wire:navigate
    @class([
        'flex flex-none items-center gap-1 rounded-full px-2 py-1 text-xs font-medium transition',
        'border border-line text-ink-faint hover:text-ink' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) <= 1,
        'bg-contour-soft text-contour hover:brightness-95' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 2,
        'bg-forest-soft text-forest hover:brightness-95' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 3,
        'bg-forest text-white hover:brightness-110' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 4,
        'border border-line text-ink-faint hover:text-ink' => $badge['key'] !== 'streak' && $badge['tone'] === 'ink',
        'bg-signal-soft text-signal hover:brightness-95' => $badge['key'] !== 'streak' && $badge['tone'] === 'signal',
    ])
    title="{{ $badge['title'] }}"
>
    @switch($badge['icon'])
        @case('streak')
            <x-flame-icon class="h-3.5 w-3.5" />
            @break

        @case('agenda')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M12 3v2h2"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
            @break

        @case('today')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M7 10.25l2 2 4-4.5"/></svg>
            @break

        @case('schedule')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
            @break

        @case('goal')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><circle cx="10" cy="10" r="3.5"/><circle cx="10" cy="10" r="0.75" fill="currentColor" stroke="none"/></svg>
            @break

        @case('emergency')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="1" fill="currentColor"/></svg>
            @break
    @endswitch
    <span class="tnum">{{ $badge['text'] }}</span>
</a>
