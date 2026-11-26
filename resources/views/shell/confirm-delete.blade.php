{{-- Step 4.3d: the calm confirm before a project or group is deleted (no native confirm(), CONVENTIONS 7).
     It says what stays (every step goes back to the board) and what goes (stages, note cards, with their
     numbers), and that it cannot be undone. A bottom sheet below 720 px, a centred card from there; both are
     at most 28rem wide (max-w-md) and centred, so a narrow desktop window never gets a full-width bar.
     "Behalten" is first in the DOM, so the focus trap starts on the safe choice; Escape and a tap on the
     backdrop cancel. While the request runs the button shows "Wird gelöscht …"; a failure shows one plain
     line in the dialog, which stays open, and nothing is deleted.
     @param string $kind ('project'|'group'), string $name, array $impact (ProjectEditor::impact()), ?string $error --}}
@php
    $things = collect([
        $kind === 'project' && $impact['stages'] > 0 ? trans_choice('projects.delete.stages', $impact['stages'], ['count' => $impact['stages']]) : null,
        $impact['notes'] > 0 ? trans_choice('projects.delete.notes', $impact['notes'], ['count' => $impact['notes']]) : null,
    ])->filter()->implode(' · ');
    $btn = 'inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-4 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-60';
@endphp
<div class="fixed inset-0 z-50 flex items-end justify-center wb:items-center wb:p-6" data-confirm-delete
    x-data="{ open: true }" x-trap.noscroll="open" @keydown.escape.window="$wire.cancelDelete()"
    role="alertdialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-text">
    <div class="absolute inset-0 bg-black/40" wire:click="cancelDelete" aria-hidden="true"></div>
    <div class="relative flex max-h-[90dvh] w-full flex-col gap-3 overflow-y-auto rounded-t-panel bg-surface p-5 pb-[max(20px,env(safe-area-inset-bottom))] shadow-card max-w-md wb:rounded-panel"
        style="border: var(--t-bw) solid var(--t-line)">
        <h2 id="delete-title" class="font-display text-lg text-ink wrap-break-word" style="font-weight: var(--t-dw)">{{ __('projects.delete.title_'.$kind, ['name' => $name]) }}</h2>
        <div id="delete-text" class="flex flex-col gap-2 text-sm text-ink-soft">
            <p>{{ trans_choice('projects.delete.steps', $impact['open'], ['count' => $impact['open']]) }}
                @if ($impact['done'] > 0) {{ trans_choice('projects.delete.done_steps', $impact['done'], ['count' => $impact['done']]) }} @endif</p>
            <p>
                @if ($things !== '') {{ __($kind === 'project' ? 'projects.delete.gone_things' : 'projects.delete.gone_things_group', ['things' => $things]) }}
                @else {{ __('projects.delete.nothing_else') }} @endif
            </p>
            <p class="font-semibold text-ink">{{ __('projects.delete.final') }}</p>
        </div>
        @if ($error)
            <p class="rounded-card bg-bad-soft px-3 py-2 text-sm text-bad" role="alert" data-delete-error>{{ $error }}</p>
        @endif
        <div class="mt-1 flex flex-col-reverse gap-2 wb:flex-row wb:justify-end">
            <button type="button" wire:click="cancelDelete" wire:loading.attr="disabled" wire:target="deleteOwner" class="{{ $btn }} border border-line bg-paper text-ink hover:bg-surface">{{ __('projects.delete.cancel') }}</button>
            <button type="button" wire:click="deleteOwner" wire:loading.attr="disabled" wire:target="deleteOwner" data-confirm-delete-button class="{{ $btn }} bg-bad text-surface hover:brightness-110">
                <span wire:loading.remove wire:target="deleteOwner">{{ __('projects.delete.confirm') }}</span>
                <span wire:loading wire:target="deleteOwner">{{ __('projects.delete.deleting') }}</span>
            </button>
        </div>
    </div>
</div>
