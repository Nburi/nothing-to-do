@props(['key', 'layout', 'mode', 'as' => 'button', 'href' => null])
{{-- An entry point of a registered action (App\Shell\ActionRegistry). The data attributes are
     what ActionParityTest (A11) reads; an unknown key, layout or mode fails loudly.
     `as`: button (default), a (needs href), or section for a region that is the entry itself
     (the Later column on the desktop board). --}}
@php
    \App\Shell\ActionRegistry::get($key);
    if (! in_array($layout, \App\Shell\ActionRegistry::LAYOUTS, true) || ! in_array($mode, \App\Shell\ActionRegistry::MODES, true)) {
        throw new InvalidArgumentException("Shell action [{$key}] needs layout werkbank|stapel and mode board|session.");
    }
    $data = ['data-action' => $key, 'data-layout' => $layout, 'data-mode' => $mode];
@endphp
@if ($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge($data) }}>{{ $slot }}</a>
@elseif ($as === 'section')
    <section {{ $attributes->merge($data) }}>{{ $slot }}</section>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->merge($data) }}>{{ $slot }}</button>
@endif
