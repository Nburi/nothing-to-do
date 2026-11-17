{{-- First-visit hint for the Zeitplan: a brand-new account has no categories, so the
     "Zeichnen:" footer that normally teaches draw-a-block-onto-the-grid does not exist
     yet, and the grid itself is blank. Said out loud once, gone the moment the user
     has a category or a single event. Shown by schedule.blade.php only when
     categories AND events are both empty. $compact = the phone layout (no border box
     around it, tighter). --}}
<div @class([
    'rounded-card border border-line bg-surface px-4 py-3 text-center',
    'mt-2 flex-none' => $compact ?? false,
    'mt-4' => ! ($compact ?? false),
])>
    <p class="text-sm text-ink-soft">Noch nichts geplant.</p>
    <p class="mt-1 text-xs leading-relaxed text-ink-faint">
        Tippe auf „+ Termin" für einen einzelnen Eintrag. Für Blöcke, die du direkt aufs Raster zeichnest
        (Schule, Training, Lernen …), lege zuerst
        <a href="{{ route('settings') }}#schedule" wire:navigate class="font-medium text-forest underline-offset-2 hover:underline">Kategorien in den Einstellungen</a> an.
    </p>
</div>
