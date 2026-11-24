@props(['key', 'layout' => null, 'mode' => null, 'as' => 'button', 'href' => null])
{{-- An entry point of a registered action (App\Shell\ActionRegistry). The data attributes are
     what ActionParityTest (A11) reads; an unknown key, layout or mode fails loudly.
     `as`: button (default), a (needs href), or section for a region that is the entry itself
     (the Later column on the desktop board). Without layout and mode (an entry that both layouts share, like
     the edit sheet, step 4.9) the key is still checked, but ActionParityTest does not count it. --}}
@php
    \App\Shell\ActionRegistry::get($key);
    $data = ['data-action' => $key];
    if ($layout !== null || $mode !== null) {
        if (! in_array($layout, \App\Shell\ActionRegistry::LAYOUTS, true) || ! in_array($mode, \App\Shell\ActionRegistry::MODES, true)) {
            throw new InvalidArgumentException("Shell action [{$key}] needs layout werkbank|stapel and mode board|session.");
        }
        $data += ['data-layout' => $layout, 'data-mode' => $mode];
    }
@endphp
@if ($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge($data) }}>{{ $slot }}</a>
@elseif ($as === 'section')
    <section {{ $attributes->merge($data) }}>{{ $slot }}</section>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->merge($data) }}>{{ $slot }}</button>
@endif
