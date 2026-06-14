{{-- Empty-list state: a faint contour hill with a control point. --}}
<div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-12 text-center">
    <svg class="h-10 w-10 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <path d="M24 8c8 0 14 5 14 11s-7 10-14 10S11 25 11 19 16 8 24 8Z" stroke="currentColor" stroke-width="1.5"/>
        <path d="M24 13.5c5 0 8.5 2.6 8.5 6S29 25 24 25s-8-2-8-5.5 3-6 8-6Z" stroke="currentColor" stroke-width="1.5"/>
        <circle cx="24" cy="19.5" r="1.8" fill="currentColor"/>
    </svg>
    <p class="max-w-[26ch] text-sm leading-relaxed text-ink-faint">{{ $slot }}</p>
</div>
