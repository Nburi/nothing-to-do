@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint shadow-sm transition focus:border-overprint focus:ring-0']) }}>
