@php
    $copy = match ($exception?->getStatusCode() ?? 400) {
        401, 403 => [
            'title' => __('errors.forbidden.title'),
            'heading' => __('errors.forbidden.heading'),
            'message' => __('errors.forbidden.message'),
        ],
        419 => [
            'title' => __('errors.expired.title'),
            'heading' => __('errors.expired.heading'),
            'message' => __('errors.expired.message'),
        ],
        429 => [
            'title' => __('errors.too_many.title'),
            'heading' => __('errors.too_many.heading'),
            'message' => __('errors.too_many.message'),
        ],
        default => [
            'title' => __('errors.failed.title'),
            'heading' => __('errors.failed.heading'),
            'message' => __('errors.failed.message'),
        ],
    };
@endphp
@include('errors.shell', $copy)
