{{-- The outer <div> is the component's required root tag and always exists,
     even with nothing to show — Livewire rejects a root view that can render
     to nothing at all. Everything visible lives in the @if below; an empty
     outer div has no size and no effect on layout either way. --}}
<div>
@if ($this->current || $this->welcomeMessage)
    @php
        // Mobile app/group.show pages pin a bottom nav (h-16-ish) plus, on
        // 'app', the capture FAB right above it — same clearance the FAB
        // itself already reserves (layouts/app.blade.php's $showCaptureFab
        // block), just on the left so the two never overlap.
        $hasBottomNav = request()->routeIs(['app', 'group.show']);
    @endphp

    @if ($this->current)
        @php
            // A ring/counter only earns its place once there's an actual backlog
            // to show progress through — a single announcement stays exactly as
            // plain as it always was (see CLAUDE.md, GERATEN: count()>1 threshold).
            $showProgress = $this->initialQueueTotal > 1;
            $isLast = $this->remainingAfterCurrent === 0;
        @endphp
        <div
            wire:key="announcement-toast-{{ $this->current->id }}"
            x-data="{
                remaining: {{ $this->remainingAfterCurrent }},
                ring: {{ ($this->positionInQueue - 1) / max(1, $this->initialQueueTotal) }},
                dismissing: false,
            }"
            x-show="! dismissing"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="fixed inset-x-4 z-40 sm:inset-x-auto sm:left-5 sm:max-w-sm {{ $hasBottomNav ? 'bottom-[84px] sm:bottom-5' : 'bottom-5' }}"
            role="status"
        >
            {{-- Signature moment: instead of a plain bar, dismissing fills a ring
                 around the card's own icon one segment at a time — the same
                 circular-progress language as the Pomodoro focus ring, reused
                 here for finishing a backlog rather than counting one down. The
                 last segment holds at a full circle for a beat before the card
                 actually closes (see announcement-toast-card's "Verstanden"
                 handler), so clearing the backlog has a small moment of its own
                 instead of just vanishing. --}}
            @include('livewire.partials.announcement-toast-card', [
                'announcement' => $this->current,
                'remaining' => $this->remainingAfterCurrent,
                'interactive' => true,
                'welcomeMessage' => $this->positionInQueue === 1 ? $this->welcomeMessage : null,
                'showProgress' => $showProgress,
                'position' => $this->positionInQueue,
                'total' => $this->initialQueueTotal,
                'isLast' => $isLast,
            ])
        </div>
    @else
        {{-- A long-gap return with nothing queued: just the greeting, on its
             own, fading out by itself — there's no backlog to progress
             through here, so no ring and nothing to click. --}}
        <div
            wire:key="welcome-only"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition duration-300 ease-in"
            x-transition:leave-end="opacity-0"
            class="fixed inset-x-4 z-40 sm:inset-x-auto sm:left-5 sm:max-w-sm {{ $hasBottomNav ? 'bottom-[84px] sm:bottom-5' : 'bottom-5' }}"
            role="status"
        >
            <div class="rounded-card border border-line bg-surface px-4 py-3 shadow-map">
                <p class="text-sm font-medium text-ink">{{ $this->welcomeMessage }}</p>
            </div>
        </div>
    @endif
@endif
</div>
