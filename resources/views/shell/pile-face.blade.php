{{-- The look of a pile (used by shell.pile). --}}
<span class="relative isolate block h-[92px] w-full">
    @if ($count > 1)
        <span class="absolute inset-0 -z-10 rounded-card bg-surface" style="border: var(--t-bw) solid var(--t-line); transform: rotate(-2.5deg) translate(-4px, 6px)" aria-hidden="true"></span>
    @endif
    @if ($count > 2)
        <span class="absolute inset-0 -z-20 rounded-card bg-surface" style="border: var(--t-bw) solid var(--t-line); transform: rotate(2deg) translate(5px, 9px)" aria-hidden="true"></span>
    @endif
    <span
        @class([
            'absolute inset-0 flex flex-col gap-1 rounded-card px-2.5 py-2.5 transition group-focus-visible:ring-3 group-focus-visible:ring-accent group-active:scale-[0.98]',
            'bg-surface shadow-card' => $count > 0,
            'border-dashed' => $count === 0,
        ])
        style="border: var(--t-bw) {{ $count > 0 ? 'solid' : 'dashed' }} var(--t-line); border-top: 4px solid {{ $count > 0 ? $edge : 'var(--t-line)' }}"
    >
        @php($marked = $top && $list !== 'done' && $top->is_important)
        <b class="line-clamp-2 font-display text-[13px] leading-snug wrap-break-word hyphens-auto {{ $count > 0 ? 'text-ink' : 'font-medium text-ink-faint' }}" @if ($count > 0) style="font-weight: 600" @endif>
            {{ $top?->title ?? __('shell.empty.pile') }}
        </b>
        {{-- Step 4.6: a marked top card says „Wichtig" in words as well as with the flag. The flag sits here,
             not in front of the title, so a long word in the title keeps the full width of the narrow Stapel pile. --}}
        @if ($sub || $marked)
            <small class="flex items-center gap-1 text-xs text-ink-faint">
                @if ($marked)@include('shell.icon', ['name' => 'flag', 'class' => 'size-3 shrink-0 text-warn', 'filled' => true])@endif
                <span class="truncate">{{ $marked ? __('shell.important.label').($sub ? ' · '.$sub : '') : $sub }}</span>
            </small>
        @endif
    </span>
</span>
<span class="flex items-baseline gap-2 px-0.5">
    <b class="font-display text-sm text-ink" style="font-weight: var(--t-dw)">{{ $label }}</b>
    @if ($list !== 'done')<em class="font-mono text-xs not-italic text-ink-faint">{{ $count }}</em>@endif
</span>
