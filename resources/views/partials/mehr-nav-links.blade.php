{{--
    The "Mehr" feature-navigation links — Zeitplan/Wochenplan/
    Planer/Bastelideen. Shared by two places: the standalone
    desktop "Mehr" dropdown panel (layouts/app.blade.php, sm and up) and the
    mobile-only section folded into the avatar menu (same file, below sm) —
    extracted here so the two can never drift apart, the same "shared partial"
    lesson this app has already learned a few times (e.g.
    partials/announcement-toast-card.blade.php, partials/list-concept-preview.blade.php).

    Deliberately recomputes its own show-nav flags from auth()->user() rather
    than trusting variables passed in from the including view — a partial's
    own inline-PHP block silently overwriting (not falling back to) a
    caller-passed variable is a documented trap in this codebase — see
    CLAUDE.md's "Blade partial variable overwrite" known issue; the safe
    direction is to never depend on caller scope for these in the first
    place.

    $stagger (bool, default false) — only true from the mobile merged menu,
    where each link's arrival is meant to *prove* nothing vanished when
    "Mehr" folded into the avatar button (see the header-menu-fan-in
    keyframe in app.css). The desktop panel opens the same content instantly,
    no flourish — the moment belongs to the mobile redesign specifically.
--}}
@php
    $stagger = $stagger ?? false;
    $mehrShowScheduleNav = \App\Services\AppModules::isVisible(auth()->user(), 'schedule');
    $mehrShowWeekplanNav = \App\Services\AppModules::isVisible(auth()->user(), 'weekplan');
    $mehrShowCraftsNav = \App\Services\AppModules::isVisible(auth()->user(), 'crafts');
    $mehrLinkIndex = 0;
    // An arrow function (fn) captures $mehrLinkIndex by value, not by
    // reference — ++ inside one would mutate only the closure's own copy,
    // so every call would keep seeing the same value it started with and
    // the stagger would never advance past 0ms. A regular closure with an
    // explicit `use (&...)` reference is required for the increment to
    // actually persist across calls.
    $mehrDelayStyle = function () use (&$mehrLinkIndex, $stagger) {
        return $stagger ? 'style="animation-delay: '.($mehrLinkIndex++ * 35).'ms"' : '';
    };
@endphp
@if ($mehrShowScheduleNav)
<a href="{{ route('schedule') }}" wire:navigate {!! $mehrDelayStyle() !!} @class([
    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
    'header-menu-fan-in' => $stagger,
    'bg-paper font-medium text-ink' => request()->routeIs('schedule'),
    'text-ink-soft hover:text-ink' => !request()->routeIs('schedule'),
]) @if(request()->routeIs('schedule')) aria-current="page" @endif>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
    Zeitplan
</a>
@endif
@if ($mehrShowWeekplanNav)
<a href="{{ route('weekplan') }}" wire:navigate {!! $mehrDelayStyle() !!} @class([
    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
    'header-menu-fan-in' => $stagger,
    'bg-paper font-medium text-ink' => request()->routeIs('weekplan'),
    'text-ink-soft hover:text-ink' => !request()->routeIs('weekplan'),
]) @if(request()->routeIs('weekplan')) aria-current="page" @endif>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
    Wochenplan &amp; Ferien
</a>
@endif
{{-- Off by default (users.planner_enabled) — see the same comment in
     layouts/app.blade.php's history: not part of AppModules::CATALOG. --}}
@if (auth()->user()->planner_enabled)
    <a href="{{ route('planner') }}" wire:navigate {!! $mehrDelayStyle() !!} @class([
        'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
        'header-menu-fan-in' => $stagger,
        'bg-paper font-medium text-ink' => request()->routeIs('planner'),
        'text-ink-soft hover:text-ink' => !request()->routeIs('planner'),
    ]) @if(request()->routeIs('planner')) aria-current="page" @endif>
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l2.5 1.5"/></svg>
        Planer
    </a>
@endif
@if ($mehrShowCraftsNav)
<a href="{{ route('crafts') }}" wire:navigate {!! $mehrDelayStyle() !!} @class([
    'flex items-center gap-2 px-4 py-2 text-sm transition hover:bg-paper',
    'header-menu-fan-in' => $stagger,
    'bg-paper font-medium text-ink' => request()->routeIs('crafts'),
    'text-ink-soft hover:text-ink' => !request()->routeIs('crafts'),
]) @if(request()->routeIs('crafts')) aria-current="page" @endif>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5a5 5 0 0 0-3 9v1.5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V11.5a5 5 0 0 0-3-9Z"/><path d="M8 17h4"/><path d="M8.5 14.5h3"/></svg>
    Bastelideen
</a>
@endif
