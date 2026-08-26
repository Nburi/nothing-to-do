{{-- The visual card shown by App\Livewire\FeatureAnnouncementToast, extracted
     so App\Livewire\Admin\AnnouncementEditor's preview renders the exact same
     markup a user would actually see — no second copy to drift out of sync.
     $announcement: a FeatureAnnouncement (persisted or a throwaway unsaved
     instance for a form-state preview). $remaining: the "N more" badge count,
     0 when not applicable. $interactive: true for the real toast (wire:click
     dismiss + wire:navigate link); false renders the same look inertly, for
     an admin preview where clicking must not do anything. --}}
@php
    $iconClasses = match ($announcement->type) {
        'maintenance' => 'bg-contour-soft text-contour',
        'warning' => 'bg-signal-soft text-signal',
        'release' => 'bg-forest-soft text-forest',
        default => 'bg-line text-ink-soft',
    };
    $labelClasses = match ($announcement->type) {
        'maintenance' => 'text-contour',
        'warning' => 'text-signal',
        'release' => 'text-forest',
        default => 'text-ink-soft',
    };
@endphp
<div class="rounded-card border border-line bg-surface p-4 shadow-map sm:p-5">
    <div class="flex items-start gap-3">
        <span @class(['mt-0.5 grid h-7 w-7 flex-none place-items-center rounded-full', $iconClasses]) aria-hidden="true">
            @switch($announcement->type)
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
            <p @class(['text-[10px] font-medium uppercase tracking-wide', $labelClasses])>{{ $announcement->typeBadgeLabel() }}</p>
            <p class="mt-0.5 text-sm font-medium text-ink">{{ $announcement->title !== '' ? $announcement->title : 'Titel' }}</p>
            <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $announcement->description !== '' ? $announcement->description : 'Kurzbeschreibung' }}</p>
        </div>
    </div>

    <div class="mt-3 flex items-center gap-2 pl-10">
        @if ($announcement->linkHref())
            @if ($interactive)
                <a
                    href="{{ $announcement->linkHref() }}"
                    @if ($announcement->isExternalLink())
                        target="_blank"
                        rel="noopener noreferrer"
                    @else
                        wire:navigate
                    @endif
                    wire:click="dismiss({{ $announcement->id }})"
                    class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                >{{ $announcement->linkLabel() }} →</a>
            @else
                <span class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft">{{ $announcement->linkLabel() }} →</span>
            @endif
        @endif

        @if ($interactive)
            <button
                type="button"
                x-on:click="remaining = Math.max(0, remaining - 1)"
                wire:click="dismiss({{ $announcement->id }})"
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
        @else
            <span class="flex items-center gap-1.5 rounded-card bg-forest px-3 py-1.5 text-xs font-medium text-white">
                Verstanden
                @if ($remaining > 0)
                    <span class="grid h-4 w-4 place-items-center rounded-full bg-white/25 text-[10px] leading-none">{{ $remaining }}</span>
                @endif
            </span>
        @endif
    </div>
</div>
