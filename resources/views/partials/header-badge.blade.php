{{--
    One header badge — an ambient shortcut (icon + short text) that links to
    the page it's about. $badge comes from App\Services\HeaderBadges::visibleFor()
    and is only ever passed here once its resolver found something to show, so
    this partial never has to render an empty/zero state itself.

    Colour: every badge except 'streak' uses its catalog 'tone' at a flat
    "soft" strength ('ink' = neutral border, 'signal' = the Notfall/warning
    tint used everywhere else that badge appears). 'streak' uses its own
    --ember-* fire gradient (app.css) instead — a deliberate exception to
    the four-tone Topografie palette, since a flame that never turns
    yellow/orange doesn't read as one — via $badge['tier']. data-badge="streak"
    is the JS target the 'celebrate' Livewire listener (app.js) re-triggers
    .flame-ignite on the instant today's streak is extended.

    Live updates (App\Livewire\HeaderBadgeRow): $badge['changed'] / ['appeared']
    are only ever true on a refresh, never on the first render. Signature
    moment "die Zahl rollt": the value span and the wash ring carry a
    wire:key that includes the text, so a new value is a genuinely new node
    that plays its one-shot animation from scratch on every change (a class
    on a surviving node would not restart if two changes land back to back).
    The value that was just replaced is rendered once more as an absolutely
    positioned ghost ($badge['previousText']) that slides out; a badge that
    just lost its content is rendered once more with $badge['leaving'] and
    collapses away. Both are server-rendered on purpose — a client morph hook
    can't do this, Livewire's morph removes keyed nodes without calling it.
    A badge that appears grows in. The wash is skipped on the streak — its
    own flame-ignite owns that pill's colour moment.
--}}
@php $leaving = $badge['leaving'] ?? false; @endphp
<a
    @unless ($leaving)
        href="{{ $badge['href'] }}"
        wire:navigate
        data-header-badge
    @else
        aria-hidden="true"
    @endunless
    wire:key="header-badge-{{ $badge['key'] }}"
    @if ($badge['key'] === 'streak') data-badge="streak" @endif
    @class([
        // py-1.5 matches the "Mehr"/avatar buttons' own vertical padding
        // (both px-2.5 py-1.5) — a badge used to sit shorter than its
        // header-row siblings, a small inconsistency in touch-target height
        // that got noticed while decluttering the row around it.
        'relative flex min-h-10 flex-none items-center gap-1 rounded-full px-2 py-1.5 text-xs font-medium transition sm:min-h-0',
        'badge-grow-in' => $badge['appeared'] ?? false,
        'badge-collapse-out' => $leaving,
        'border border-line text-[rgb(var(--ember-glow))] hover:text-ink' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) <= 1,
        'bg-[rgb(var(--ember-warm-soft))] text-[rgb(var(--ember-warm))] hover:brightness-95' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 2,
        'bg-[rgb(var(--ember-hot-soft))] text-[rgb(var(--ember-hot))] hover:brightness-95' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 3,
        'bg-[rgb(var(--ember-blaze))] text-white hover:brightness-110 streak-tier-4' => $badge['key'] === 'streak' && ($badge['tier'] ?? 0) === 4,
        'border border-line text-ink-faint hover:text-ink' => $badge['key'] !== 'streak' && $badge['tone'] === 'ink',
        'bg-signal-soft text-signal hover:brightness-95' => $badge['key'] !== 'streak' && $badge['tone'] === 'signal',
    ])
    @unless ($leaving) title="{{ $badge['title'] }}" @endunless
>
    @switch($badge['icon'])
        @case('streak')
            <span class="relative inline-flex flame-spark-anchor">
                <x-flame-icon class="h-3.5 w-3.5" />
            </span>
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

        @case('crafts')
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3a4.5 4.5 0 0 0-2.5 8.25c.4.28.6.7.6 1.15V13h3.8v-.6c0-.45.2-.87.6-1.15A4.5 4.5 0 0 0 10 3Z"/><path d="M8.25 15.5h3.5M8.75 17.5h2.5"/></svg>
            @break
    @endswitch
    @if (($badge['changed'] ?? false) && $badge['key'] !== 'streak')
        <span
            wire:key="header-badge-{{ $badge['key'] }}-wash-{{ $badge['text'] }}"
            @class(['badge-wash', 'badge-wash--signal' => $badge['tone'] === 'signal'])
            aria-hidden="true"
        ></span>
    @endif
    @if (! $leaving && ($badge['previousText'] ?? null) !== null)
        <span
            wire:key="header-badge-{{ $badge['key'] }}-value-out-{{ $badge['previousText'] }}"
            class="tnum badge-value-out"
            aria-hidden="true"
        >{{ $badge['previousText'] }}</span>
    @endif
    <span
        wire:key="header-badge-{{ $badge['key'] }}-value-{{ $badge['text'] }}"
        @class(['tnum', 'badge-value-in' => $badge['changed'] ?? false])
    >{{ $badge['text'] }}</span>
</a>
