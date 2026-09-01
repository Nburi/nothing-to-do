{{-- The visual card shown by App\Livewire\FeatureAnnouncementToast, extracted
     so App\Livewire\Admin\AnnouncementEditor's preview renders the exact same
     markup a user would actually see — no second copy to drift out of sync.
     $announcement: a FeatureAnnouncement (persisted or a throwaway unsaved
     instance for a form-state preview). $remaining: the "N more" badge count,
     0 when not applicable. $interactive: true for the real toast (wire:click
     dismiss + wire:navigate link); false renders the same look inertly, for
     an admin preview where clicking must not do anything.
     $welcomeMessage/$showProgress/$position/$total/$isLast: the backlog/
     welcome-back extras — all optional, default to "off" so the admin
     preview (which never passes them) renders exactly as before.
     $linkTestable: true lets the "Ansehen" link (only) be clicked from an
     otherwise-inert ($interactive=false) admin preview, so a chosen module/
     highlight-selector or external URL can actually be tried out before
     publishing. Always opens in a new tab — even for a module link, which
     the real toast wire:navigates to in place — so following it never
     navigates the admin away from their in-progress, unsaved edit form.
     Never fires the dismiss side effects, since there is nothing to dismiss
     for an unsaved/still-being-previewed announcement. --}}
@php
    $welcomeMessage ??= null;
    $showProgress ??= false;
    $position ??= null;
    $total ??= null;
    $isLast ??= false;
    $linkTestable ??= false;

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
    // Circumference of the progress ring (r=17 in a 40x40 viewBox) — a fixed
    // constant shared between the dasharray and the dashoffset expression.
    $ringCircumference = round(2 * M_PI * 17, 2);
@endphp
<div class="rounded-card border border-line bg-surface p-4 shadow-map sm:p-5">
    @if (! empty($welcomeMessage))
        <p class="mb-2 text-xs font-medium text-ink-soft">{{ $welcomeMessage }}</p>
    @endif

    <div class="flex items-start gap-3">
        <span class="relative mt-0.5 grid h-7 w-7 flex-none place-items-center" aria-hidden="true">
            @if ($showProgress)
                <svg class="absolute -inset-1.5 h-10 w-10 -rotate-90" viewBox="0 0 40 40">
                    <circle cx="20" cy="20" r="17" fill="none" stroke-width="2" class="stroke-line" />
                    <circle
                        cx="20" cy="20" r="17" fill="none" stroke-width="2" stroke-linecap="round"
                        stroke-dasharray="{{ $ringCircumference }}"
                        x-bind:stroke-dashoffset="{{ $ringCircumference }} * (1 - ring)"
                        class="stroke-forest transition-[stroke-dashoffset] duration-500 ease-out"
                    ></circle>
                </svg>
            @endif
            <span @class(['grid h-7 w-7 place-items-center rounded-full', $iconClasses])>
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
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
                <p @class(['text-[10px] font-medium uppercase tracking-wide', $labelClasses])>{{ $announcement->typeBadgeLabel() }}</p>
                @if ($showProgress)
                    <p class="flex-none text-[10px] font-medium text-ink-faint">{{ $position }} von {{ $total }}</p>
                @endif
            </div>
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
                    x-on:click="
                        remaining = Math.max(0, remaining - 1);
                        @if ($showProgress && $isLast)
                            ring = 1;
                            setTimeout(() => $wire.dismiss({{ $announcement->id }}), 550);
                        @else
                            $wire.dismiss({{ $announcement->id }});
                        @endif
                    "
                    class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                >{{ $announcement->linkLabel() }} →</a>
            @elseif ($linkTestable)
                <a
                    href="{{ $announcement->linkHref() }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
                >{{ $announcement->linkLabel() }} →</a>
            @else
                <span class="rounded-card border border-line bg-paper px-3 py-1.5 text-xs font-medium text-ink-soft">{{ $announcement->linkLabel() }} →</span>
            @endif
        @endif

        @if ($interactive)
            <button
                type="button"
                x-on:click="
                    remaining = Math.max(0, remaining - 1);
                    @if ($showProgress && $isLast)
                        ring = 1;
                        setTimeout(() => $wire.dismiss({{ $announcement->id }}), 550);
                    @elseif ($showProgress)
                        ring = Math.min(1, ring + (1 / {{ max(1, $total ?? 1) }}));
                        $wire.dismiss({{ $announcement->id }});
                    @else
                        $wire.dismiss({{ $announcement->id }});
                    @endif
                "
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

            @if ($showProgress)
                {{-- Same consequence level as "Verstanden" (nothing is deleted,
                     only marked seen), so this stays a plain single click —
                     no armed-double-click, matching CLAUDE.md §10's rule that
                     that pattern is reserved for genuinely destructive actions. --}}
                <button
                    type="button"
                    wire:click="skipAll"
                    class="text-xs font-medium text-ink-faint transition hover:text-ink-soft"
                >Alle überspringen</button>
            @endif
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
