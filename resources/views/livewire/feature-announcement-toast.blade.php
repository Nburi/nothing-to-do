{{-- The outer <div> is the component's required root tag and always exists,
     even with nothing to show — Livewire rejects a root view that can render
     to nothing at all. Everything visible lives in the @if below; an empty
     outer div has no size and no effect on layout either way. --}}
<div>
@if ($this->current)
    @php
        // Mobile app/group.show pages pin a bottom nav (h-16-ish) plus, on
        // 'app', the capture FAB right above it — same clearance the FAB
        // itself already reserves (layouts/app.blade.php's $showCaptureFab
        // block), just on the left so the two never overlap.
        $hasBottomNav = request()->routeIs(['app', 'group.show']);

        // Literal per-type classes, not a dynamically-built "bg-{$tone}-soft"
        // string — Tailwind's content scanner only finds class names that
        // appear as complete literal tokens in the source, so a built-up
        // string would silently purge out of the build (see CLAUDE.md's
        // Known Issues entry on JS-only classes for the same trap).
        $iconClasses = match ($this->current->type) {
            'maintenance' => 'bg-contour-soft text-contour',
            'warning' => 'bg-signal-soft text-signal',
            'release' => 'bg-forest-soft text-forest',
            default => 'bg-line text-ink-soft',
        };
        $labelClasses = match ($this->current->type) {
            'maintenance' => 'text-contour',
            'warning' => 'text-signal',
            'release' => 'text-forest',
            default => 'text-ink-soft',
        };
    @endphp
    <div
        wire:key="announcement-toast-{{ $this->current->id }}"
        x-data="{ remaining: {{ $this->remainingAfterCurrent }}, dismissing: false }"
        x-show="! dismissing"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="fixed inset-x-4 z-40 sm:inset-x-auto sm:left-5 sm:max-w-sm {{ $hasBottomNav ? 'bottom-[84px] sm:bottom-5' : 'bottom-5' }}"
        role="status"
    >
        <div class="rounded-card border border-line bg-surface p-4 shadow-map sm:p-5">
            <div class="flex items-start gap-3">
                <span @class(['mt-0.5 grid h-7 w-7 flex-none place-items-center rounded-full', $iconClasses]) aria-hidden="true">
                    @switch($this->current->type)
                        @case('maintenance')
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 3.3a3.5 3.5 0 0 1-4.6 4.6L4.5 13.5a1.6 1.6 0 0 0 2.3 2.3l5.6-5.6a3.5 3.5 0 0 1 4.6-4.6l-2.2 2.2-1.7-.4-.4-1.7 2-2Z"/></svg>
                            @break
                        @case('warning')
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3 17.5 16h-15L10 3Z"/><path d="M10 8v3.5"/><circle cx="10" cy="14" r="0.75" fill="currentColor" stroke="none"/></svg>
                            @break
                        @case('release')
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5 12.2 7l5 .7-3.6 3.5.8 5-4.4-2.3-4.4 2.3.8-5-3.6-3.5 5-.7L10 2.5Z"/></svg>
                            @break
                        @default
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M10 9v4.5"/><circle cx="10" cy="6.5" r="0.75" fill="currentColor" stroke="none"/></svg>
                    @endswitch
                </span>
                <div class="min-w-0 flex-1">
                    <p @class(['text-[10px] font-medium uppercase tracking-wide', $labelClasses])>{{ $this->current->typeBadgeLabel() }}</p>
                    <p class="mt-0.5 text-sm font-medium text-ink">{{ $this->current->title }}</p>
                    <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $this->current->description }}</p>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2 pl-10">
                @if ($this->current->relatedRouteName())
                    <a
                        href="{{ route($this->current->relatedRouteName()) }}"
                        wire:navigate
                        wire:click="dismiss({{ $this->current->id }})"
                        class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                    >{{ $this->current->relatedModuleLabel() }} ansehen →</a>
                @endif

                {{-- Signature moment: the button itself counts down, so "there's
                     more" is felt at the one place you're already looking,
                     instead of a separate list. The local `remaining` ticks
                     down instantly on click, ahead of the round trip; the
                     server's own re-render (a fresh wire:key'd card) confirms
                     it a beat later. --}}
                <button
                    type="button"
                    x-on:click="remaining = Math.max(0, remaining - 1)"
                    wire:click="dismiss({{ $this->current->id }})"
                    class="flex items-center gap-1.5 rounded-card bg-forest px-3 py-1.5 text-xs font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
                >
                    Verstanden
                    <span
                        x-show="remaining > 0"
                        x-transition.scale.duration.150ms
                        x-text="remaining"
                        class="grid h-4 w-4 place-items-center rounded-full bg-white/25 text-[10px] leading-none"
                    ></span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
