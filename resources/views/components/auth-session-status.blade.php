@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-card bg-forest-soft px-3 py-2 text-sm font-medium text-forest']) }}>
        {{ $status }}
    </div>
@endif
