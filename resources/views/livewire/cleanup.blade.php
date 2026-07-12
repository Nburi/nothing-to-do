<div
    x-data
    x-init="$store.cleanup.init({
        inbox: @js($this->inboxQueue->pluck('id')),
        review: @js($this->reviewQueue->pluck('id')),
    })"
    class="mx-auto max-w-md px-4 pb-10 pt-5"
>
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft transition hover:text-ink">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3 2 8l4 5M2 8h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Schliessen
        </a>
        <p class="tnum text-xs text-ink-faint" x-show="$store.cleanup.phase !== 'done'" x-text="$store.cleanup.remainingLabel" style="display: none"></p>
    </div>

    {{-- Phase 1: Inbox sortieren --}}
    <div x-cloak x-show="$store.cleanup.phase === 'inbox'">
        <h1 class="mb-5 text-center text-xs font-medium uppercase tracking-wide text-ink-faint">Inbox sortieren</h1>
        <div class="relative grid" style="min-height: 260px">
            @forelse ($this->inboxQueue as $task)
                @include('livewire.partials.cleanup-card-inbox', ['task' => $task])
            @empty
            @endforelse
        </div>
    </div>

    {{-- Phase 2: Heute / Deadline / Wichtig --}}
    <div x-cloak x-show="$store.cleanup.phase === 'review'">
        <h1 class="mb-5 text-center text-xs font-medium uppercase tracking-wide text-ink-faint">Heute, Deadline, Wichtig</h1>
        <div class="relative grid" style="min-height: 260px">
            @forelse ($this->reviewQueue as $task)
                @include('livewire.partials.cleanup-card-review', ['task' => $task])
            @empty
            @endforelse
        </div>
    </div>

    {{-- Done --}}
    <div x-cloak x-show="$store.cleanup.phase === 'done'" class="animate-punch-in flex flex-col items-center gap-4 py-24 text-center">
        <div class="grid h-14 w-14 place-items-center rounded-full bg-forest-soft text-forest">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5 8 14.5 16 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1 class="text-lg font-medium text-ink">Aufgeräumt!</h1>
        <p class="max-w-[28ch] text-sm text-ink-faint">Alles sortiert und getaggt.</p>
        <a href="{{ route('app') }}" wire:navigate class="rounded-card bg-forest px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-95">
            Zum Board
        </a>
    </div>
</div>
