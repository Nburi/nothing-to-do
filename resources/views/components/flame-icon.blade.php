{{--
    A streak flame. Inherits currentColor — the tier color comes from the wrapper, not this icon.
    Two layered paths (body + core), classed .flame-icon/.flame-icon__core, so the
    .flame-ignite keyframes in app.css have two independent things to flicker/pulse
    between instead of one flat shape.
--}}
<svg {{ $attributes->merge(['class' => 'h-4 w-4 flame-icon']) }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path
        class="flame-icon__body"
        d="M10 2c1.8 2.6 4.5 5 4.5 8.5a4.5 4.5 0 1 1-9 0c0-1.4.5-2.4 1.2-3.3.2 1.1.9 1.8 1.7 1.9-.5-2.7.3-4.8 1.6-7.1Z"
        fill="currentColor" fill-opacity=".15" stroke="currentColor" stroke-width="1.5"
        stroke-linejoin="round" stroke-linecap="round"
    />
    <circle class="flame-icon__core" cx="8.6" cy="11" r="1.7" fill="currentColor" />
</svg>
