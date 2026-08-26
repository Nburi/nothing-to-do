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
        {{-- Signature moment: the button itself counts down, so "there's more"
             is felt at the one place you're already looking, instead of a
             separate list. The local `remaining` ticks down instantly on
             click, ahead of the round trip; the server's own re-render (a
             fresh wire:key'd card) confirms it a beat later. --}}
        @include('livewire.partials.announcement-toast-card', [
            'announcement' => $this->current,
            'remaining' => $this->remainingAfterCurrent,
            'interactive' => true,
        ])
    </div>
@endif
</div>
