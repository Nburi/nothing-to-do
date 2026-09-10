{{--
    A plain clock face — "we'll be back shortly", not "something is broken". Deliberately
    distinct from <x-error-icon> (used everywhere else in resources/views/errors/): planned
    maintenance is an expected state, not a failure, and shouldn't borrow the same visual
    language as a 404/500. Same economical single-color line-art style as flame-icon.blade.php.
--}}
<svg {{ $attributes->merge(['class' => 'h-10 w-10']) }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5" fill="currentColor" fill-opacity=".08" />
    <path d="M10 6v4.2l2.8 1.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
</svg>
