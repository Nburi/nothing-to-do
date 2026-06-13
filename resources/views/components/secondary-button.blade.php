<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-card border border-line bg-surface px-4 py-2 text-sm font-medium text-ink-soft transition hover:bg-paper hover:text-ink active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint disabled:opacity-40']) }}>
    {{ $slot }}
</button>
