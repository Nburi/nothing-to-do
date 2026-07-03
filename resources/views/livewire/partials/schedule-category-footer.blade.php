@php
    // Literal class sets per token — kept literal so the Tailwind JIT scanner picks them up.
    $footerStyles = [
        'forest'    => ['active' => 'bg-forest/20 text-forest ring-2 ring-forest/50',    'idle' => 'text-ink-soft hover:bg-forest/10 hover:text-forest',    'dot' => 'bg-forest'],
        'overprint' => ['active' => 'bg-overprint/20 text-overprint ring-2 ring-overprint/50', 'idle' => 'text-ink-soft hover:bg-overprint/10 hover:text-overprint', 'dot' => 'bg-overprint'],
        'signal'    => ['active' => 'bg-signal/20 text-signal ring-2 ring-signal/50',    'idle' => 'text-ink-soft hover:bg-signal/10 hover:text-signal',    'dot' => 'bg-signal'],
        'ink'       => ['active' => 'bg-ink-faint/30 text-ink ring-2 ring-ink/30',       'idle' => 'text-ink-soft hover:bg-ink-faint/20 hover:text-ink',   'dot' => 'bg-ink-faint'],
        'contour'   => ['active' => 'bg-contour/20 text-contour ring-2 ring-contour/50', 'idle' => 'text-ink-soft hover:bg-contour/10 hover:text-contour', 'dot' => 'bg-contour'],
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
            class="inline-flex flex-none items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition active:scale-95"
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
        class="ml-auto flex-none text-[11px] text-ink-faint transition hover:text-ink"
        style="display: none"
        aria-label="Zeichnen abbrechen"
    >✕</button>
</div>
