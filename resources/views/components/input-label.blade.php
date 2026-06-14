@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-ink-soft']) }}>
    {{ $value ?? $slot }}
</label>
