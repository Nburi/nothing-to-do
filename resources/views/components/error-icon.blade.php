{{--
    A ring that doesn't quite close, with a small mark at the gap — the same
    economical, single-color line-art style as flame-icon.blade.php. Reused
    across every error page; only the 404 page adds the .error-icon-settle
    animation (see app.css) — the one-time "pulses once, then settles" moment
    for the page a real user hits most often. Deliberately abstract rather
    than tied to one specific error type, since 4xx/5xx reuse it unanimated.
--}}
<svg {{ $attributes->merge(['class' => 'h-10 w-10']) }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path d="M10 2.5a7.5 7.5 0 1 1-6.5 3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
    <circle cx="3.5" cy="6.25" r="1.15" fill="currentColor" />
</svg>
