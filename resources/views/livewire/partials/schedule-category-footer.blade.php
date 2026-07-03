@php
    // Literal class sets per token — kept literal so the Tailwind JIT scanner picks them up.
    // Active text stays a neutral high-contrast ink rather than the token color itself: the
    // token at full saturation on its own 20%-tint background falls short of WCAG AA in both
    // themes (as low as 3.5:1) — the tint + ring already carry the category color identity.
    $footerStyles = [
        'forest'    => ['active' => 'bg-forest/20 text-ink ring-2 ring-forest/50 focus-visible:ring-forest',       'idle' => 'text-ink-soft hover:bg-forest/10 hover:text-forest',    'dot' => 'bg-forest'],
        'overprint' => ['active' => 'bg-overprint/20 text-ink ring-2 ring-overprint/50 focus-visible:ring-overprint', 'idle' => 'text-ink-soft hover:bg-overprint/10 hover:text-overprint', 'dot' => 'bg-overprint'],
        'signal'    => ['active' => 'bg-signal/20 text-ink ring-2 ring-signal/50 focus-visible:ring-signal',       'idle' => 'text-ink-soft hover:bg-signal/10 hover:text-signal',    'dot' => 'bg-signal'],
        'ink'       => ['active' => 'bg-ink-faint/30 text-ink ring-2 ring-ink/30 focus-visible:ring-ink',          'idle' => 'text-ink-soft hover:bg-ink-faint/20 hover:text-ink',   'dot' => 'bg-ink-faint'],
        'contour'   => ['active' => 'bg-contour/20 text-ink ring-2 ring-contour/50 focus-visible:ring-contour',    'idle' => 'text-ink-soft hover:bg-contour/10 hover:text-contour', 'dot' => 'bg-contour'],
    ];
@endphp

<div class="flex items-center gap-2 overflow-x-auto border-t border-line/60 px-3 py-2">
    <span class="flex-none select-none text-[11px] text-ink-faint">Zeichnen:</span>
    @foreach ($this->categories as $cat)
        @php $s = $footerStyles[$cat->color] ?? $footerStyles['contour']; @endphp
        <button
            type="button"
            @click="$store.draw.cat === {{ $cat->id }}
                ? ($store.draw.cat = null, $store.draw.color = null)
                : ($store.draw.cat = {{ $cat->id }}, $store.draw.color = '{{ $cat->color }}')"
            :class="$store.draw.cat === {{ $cat->id }}
                ? '{{ $s['active'] }}'
                : '{{ $s['idle'] }}'"
            class="inline-flex flex-none items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            aria-label="{{ $cat->name }} zeichnen"
        >
            <span class="h-2 w-2 flex-none rounded-full {{ $s['dot'] }}"></span>
            {{ $cat->name }}
        </button>
    @endforeach

    {{-- Deselect hint when a category is active --}}
    <button
        type="button"
        x-show="$store.draw.cat !== null"
        @click="$store.draw.cat = null; $store.draw.color = null"
        class="ml-auto grid h-6 w-6 flex-none place-items-center rounded-full text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
        style="display: none"
        aria-label="Zeichnen abbrechen"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 3 10 10M13 3 3 13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
    </button>
</div>
