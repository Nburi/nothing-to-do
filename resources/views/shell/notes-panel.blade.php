{{-- Step 4.3b: the note cards of a project or group (Niels, 2026-11-18: own column, chip sheet on the
     phone, Markdown). Quick field (Enter saves, # suggests tags), tag filter, "Lose" and one pile
     per stage; drag between piles on desktop, the stage chip everywhere. Edit in place.
     @param Collection $notes, ?Collection $stages (null for a group), array $userTags, ?string $noteTag,
            ?int $editingNote --}}
@php
    $isProject = $stages !== null;
    $used = $notes->flatMap(fn ($n) => $n->tags->pluck('name'))->unique()->sort()->values();
    $shown = $noteTag ? $notes->filter(fn ($n) => $n->tags->contains('name', $noteTag)) : $notes;
    $piles = $isProject
        ? collect([['key' => 'none', 'label' => __('projects.notes.loose'), 'hint' => __('projects.notes.loose_hint'), 'stage' => null]])
            ->concat($stages->values()->map(fn ($s, $i) => ['key' => 'stage-'.$s->id, 'label' => __('projects.notes.in_stage', ['name' => ($i + 1).' · '.$s->name]), 'hint' => null, 'stage' => $s]))
        : collect([['key' => 'none', 'label' => null, 'hint' => null, 'stage' => null]]);
    $chip = 'inline-flex min-h-8 items-center gap-1 rounded-full border px-2.5 text-xs transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
    $input = 'w-full rounded-card border bg-paper px-3 py-2 text-sm text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent';
@endphp
<section class="flex flex-col gap-3 rounded-panel bg-surface p-4" style="border: var(--t-bw) solid var(--t-line)" aria-labelledby="notes-title"
    x-data="{ sheet: null }" @keydown.escape.window="sheet = null">
    <div class="flex items-baseline justify-between gap-2">
        <h2 id="notes-title" class="font-display text-[15px] text-ink" style="font-weight: var(--t-dw)">{{ __('projects.notes.title') }}</h2>
        <span class="font-mono text-xs text-ink-faint tabular-nums">{{ $notes->count() }}</span>
    </div>

    {{-- Quick note. --}}
    <form wire:submit="addNote" class="relative flex flex-col gap-1" x-data="noteComposer({ tags: @js($userTags) })">
        <label for="note-draft" class="sr-only">{{ __('projects.notes.title') }}</label>
        <textarea id="note-draft" x-ref="field" wire:model="noteDraft" rows="2" maxlength="{{ \App\Services\Projects\NoteEditor::BODY_MAX }}"
            placeholder="{{ __('projects.notes.placeholder') }}" @input="suggest()" @click="suggest()" @keydown="key($event)" @blur="setTimeout(() => matches = [], 150)"
            aria-describedby="note-hint @error('noteDraft') note-draft-error @enderror" aria-invalid="{{ $errors->has('noteDraft') ? 'true' : 'false' }}"
            @class([$input, 'resize-y', 'border-bad' => $errors->has('noteDraft'), 'border-line' => ! $errors->has('noteDraft')])></textarea>
        <ul x-show="matches.length" x-cloak class="absolute top-full left-0 z-20 mt-1 flex min-w-40 flex-col rounded-card bg-surface p-1 shadow-card" style="border: var(--t-bw) solid var(--t-line)" role="listbox">
            <template x-for="(tag, i) in matches" :key="tag">
                <li><button type="button" @mousedown.prevent="pick(tag)" :class="i === active ? 'bg-accent-soft text-accent-ink' : 'text-ink'" class="w-full rounded-[6px] px-2 py-1 text-left text-sm" x-text="'#' + tag" role="option" :aria-selected="i === active"></button></li>
            </template>
        </ul>
        <div class="flex items-center justify-between gap-2">
            <p id="note-hint" class="text-xs text-ink-faint">{{ __('projects.notes.hint') }}</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="addNote" class="inline-flex min-h-9 shrink-0 items-center rounded-card border border-line bg-surface px-3 text-sm font-semibold text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-50">{{ __('projects.notes.add') }}</button>
        </div>
        @error('noteDraft')<p id="note-draft-error" class="text-sm text-bad">{{ $message }}</p>@enderror
    </form>

    {{-- Tag filter (tags are shared across projects; the filter works inside this page). --}}
    @if ($used->isNotEmpty())
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ __('projects.notes.filter') }}">
            <button type="button" wire:click="filterTag(null)" aria-pressed="{{ $noteTag === null ? 'true' : 'false' }}"
                @class([$chip, 'border-ink bg-ink text-paper' => $noteTag === null, 'border-line bg-paper text-ink-soft hover:text-ink' => $noteTag !== null])>{{ __('projects.notes.all') }}</button>
            @foreach ($used as $tag)
                <button type="button" wire:key="tagf-{{ $tag }}" wire:click="filterTag(@js($tag))" aria-pressed="{{ $noteTag === $tag ? 'true' : 'false' }}"
                    @class([$chip, 'border-accent bg-accent text-accent-fg' => $noteTag === $tag, 'border-line bg-paper text-ink-soft hover:text-ink' => $noteTag !== $tag])>#{{ $tag }}</button>
            @endforeach
        </div>
    @endif

    @if ($notes->isEmpty())
        <p class="rounded-card border border-dashed border-line px-3 py-3 text-sm text-ink-faint">{{ $isProject ? __('projects.notes.empty') : __('projects.notes.empty_group') }}</p>
    @elseif ($noteTag && $shown->isEmpty())
        <p class="rounded-card border border-dashed border-line px-3 py-3 text-sm text-ink-faint">{{ __('projects.notes.no_match', ['tag' => $noteTag]) }}</p>
    @endif

    @foreach ($piles as $pile)
        @php
            $items = $shown->filter(fn ($n) => $pile['stage'] ? $n->stage_id === $pile['stage']->id : ($n->stage_id === null || ! $isProject || ! $stages->contains('id', $n->stage_id)))->values();
        @endphp
        @continue($notes->isEmpty() || ($noteTag && $items->isEmpty()))
        <div wire:key="pile-{{ $pile['key'] }}" class="flex flex-col gap-1.5">
            @if ($pile['label'])
                <p class="font-mono text-[10.5px] uppercase tracking-wider text-ink-faint">{{ $pile['label'] }}@if ($pile['hint']) · <span class="normal-case tracking-normal">{{ $pile['hint'] }}</span>@endif</p>
            @endif
            <ul x-data="notePile" data-pile="{{ $pile['key'] }}" class="flex min-h-9 flex-col gap-1.5 rounded-card">
                @forelse ($items as $note)
                    <li wire:key="note-{{ $note->id }}" data-note="{{ $note->id }}" class="group/note relative flex gap-2 rounded-card bg-paper px-3 py-2 text-sm" style="border: var(--t-bw) solid var(--t-line)">
                        @if ($editingNote === $note->id)
                            <form wire:submit="saveNote" class="flex w-full flex-col gap-2" x-data x-init="$nextTick(() => { $refs.e.focus(); $refs.e.setSelectionRange($refs.e.value.length, $refs.e.value.length) })">
                                <label for="edit-note-{{ $note->id }}" class="sr-only">{{ __('projects.notes.edit') }}</label>
                                <textarea id="edit-note-{{ $note->id }}" x-ref="e" wire:model="editNoteBody" rows="4" maxlength="{{ \App\Services\Projects\NoteEditor::BODY_MAX }}"
                                    @keydown.escape="$wire.cancelNote()" @keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.closest('form').requestSubmit() }"
                                    @class([$input, 'resize-y', 'border-bad' => $errors->has('editNoteBody'), 'border-line' => ! $errors->has('editNoteBody')])></textarea>
                                @error('editNoteBody')<p class="text-sm text-bad">{{ $message }}</p>@enderror
                                <div class="flex gap-2">
                                    <button type="submit" class="inline-flex min-h-9 items-center rounded-card bg-accent px-3 text-sm font-semibold text-accent-fg">{{ __('projects.save') }}</button>
                                    <button type="button" wire:click="cancelNote" class="inline-flex min-h-9 items-center rounded-card px-3 text-sm font-semibold text-ink-soft">{{ __('projects.cancel') }}</button>
                                </div>
                            </form>
                        @else
                            <span data-note-grip class="hidden w-2 shrink-0 cursor-grab self-stretch rounded-full bg-line opacity-60 transition group-hover/note:opacity-100 wb:block" title="{{ __('projects.notes.drag') }}" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1" x-data="{ open: false, long: false }" x-init="$nextTick(() => long = $refs.b.scrollHeight > $refs.b.clientHeight + 4)">
                                <div x-ref="b" :class="open ? '' : 'max-h-[6.2em] overflow-hidden'" class="prose-topo wrap-break-word text-sm text-ink"
                                    @click="const t = $event.target.closest('[data-tag]'); if (t) $wire.filterTag(t.dataset.tag)">{!! $note->html() !!}</div>
                                <button type="button" x-show="long" x-cloak @click="open = ! open" class="mt-0.5 text-xs font-semibold text-accent-ink hover:underline" x-text="open ? @js(__('projects.notes.less')) : @js(__('projects.notes.more'))"></button>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @if ($isProject)
                                        <button type="button" @click="sheet = {{ $note->id }}" class="{{ $chip }} border-line bg-surface text-ink-soft hover:text-ink" aria-haspopup="dialog">
                                            {{ $note->stage_id && ($st = $stages->firstWhere('id', $note->stage_id)) ? $st->name : __('projects.notes.stage_chip') }} <span aria-hidden="true">▾</span>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="noteToStep({{ $note->id }})" wire:loading.attr="disabled" class="{{ $chip }} border-transparent font-semibold text-accent-ink hover:bg-accent-soft">{{ __('projects.notes.to_step') }}</button>
                                    <details class="relative ml-auto" x-data @click.outside="$el.open = false">
                                        <summary class="grid size-8 cursor-pointer list-none place-items-center rounded-full text-ink-soft hover:bg-surface focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent [&::-webkit-details-marker]:hidden" aria-label="{{ __('projects.notes.actions') }}">
                                            @include('shell.icon', ['name' => 'dots', 'class' => 'size-4'])
                                        </summary>
                                        <div class="absolute right-0 z-20 mt-1 flex w-40 flex-col rounded-card bg-surface p-1 shadow-card" style="border: var(--t-bw) solid var(--t-line)">
                                            <button type="button" wire:click="editNote({{ $note->id }})" class="rounded-[6px] px-2.5 py-2 text-left text-sm text-ink hover:bg-paper">{{ __('projects.notes.edit') }}</button>
                                            <button type="button" wire:click="deleteNote({{ $note->id }})" class="rounded-[6px] px-2.5 py-2 text-left text-sm text-bad hover:bg-bad-soft">{{ __('projects.notes.delete') }}</button>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        @endif
                    </li>
                @empty
                    @if ($isProject)
                        <li class="hidden rounded-card border border-dashed border-line px-3 py-2 text-xs text-ink-faint wb:block">{{ __('projects.notes.drop_here') }}</li>
                    @endif
                @endforelse
            </ul>
        </div>
    @endforeach

    {{-- The stage sheet: bottom sheet on the phone, small dialog on desktop. --}}
    @if ($isProject)
        <div x-show="sheet" x-cloak class="fixed inset-0 z-50 flex items-end justify-center wb:items-center" role="dialog" aria-modal="true" aria-labelledby="note-sheet-title">
            <div class="absolute inset-0 bg-black/40" @click="sheet = null"></div>
            <div class="relative flex w-full flex-col gap-1 rounded-t-panel bg-surface p-4 pb-[max(16px,env(safe-area-inset-bottom))] shadow-card wb:w-80 wb:rounded-panel" x-trap.noscroll="sheet">
                <p id="note-sheet-title" class="mb-1 font-mono text-[11px] uppercase tracking-widest text-ink-faint">{{ __('projects.notes.stage_sheet') }}</p>
                @foreach ($piles as $pile)
                    <button type="button" wire:key="sheet-{{ $pile['key'] }}" @click="$wire.setNoteStage(sheet, @js($pile['key'])); sheet = null"
                        class="min-h-11 rounded-card px-3 text-left text-sm text-ink hover:bg-paper focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent" style="border: var(--t-bw) solid var(--t-line)">
                        {{ $pile['stage'] ? $pile['stage']->name : __('projects.notes.loose') }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</section>
