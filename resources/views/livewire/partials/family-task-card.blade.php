@php
    $assigneeColor = $task->assigned_to ? ($memberColors[$task->assigned_to] ?? null) : null;
    $isClaimed = $task->assigned_to !== null && ! $task->is_completed;
    $isDone = $task->is_completed;
@endphp
{{-- Familie card. Three states, one tap target on the body:
     unclaimed → claim (Signature Moment A), claimed → complete, done → reopen.
     See CLAUDE.md, "Familie — geteilte Aufgaben" for the full interaction model. --}}
<div
    wire:key="family-task-{{ $task->id }}"
    data-family-task="{{ $task->id }}"
    @class([
        'group/fam relative flex min-h-[104px] flex-col justify-between rounded-card border p-3 shadow-map transition-colors duration-200',
        'family-claim-flood text-white' => $isClaimed,
        'border-line bg-surface opacity-55' => $isDone,
        'border-dashed border-ink-faint/40 bg-surface' => ! $isClaimed && ! $isDone,
    ])
    @if ($isClaimed) style="--fam-tap: {{ \App\Livewire\Support\FamilyColors::cssVar($assigneeColor) }}" @endif
>
    <div class="flex items-start justify-between gap-1.5">
        <button
            type="button"
            @if ($isDone)
                wire:click="reopenTask({{ $task->id }})"
                aria-label="Wieder öffnen: {{ $task->title }}"
            @elseif ($isClaimed)
                wire:click="completeTask({{ $task->id }})"
                aria-label="Als erledigt markieren: {{ $task->title }}"
            @else
                wire:click="claimTask({{ $task->id }})"
                aria-label="Für dich übernehmen: {{ $task->title }}"
            @endif
            class="min-w-0 flex-1 rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
        >
            @if ($isDone)
                <svg class="mb-1 h-4 w-4 text-forest" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M5.2 8.2 7.2 10.2 11 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            @endif
            <span @class([
                'block break-words text-sm leading-snug',
                'text-ink-faint line-through' => $isDone,
                'font-medium text-white' => $isClaimed,
                'text-ink-soft' => ! $isClaimed && ! $isDone,
            ])>{{ $task->title }}</span>
            @if ($task->notes)
                <span @class([
                    'mt-1 block max-w-full truncate text-[11px]',
                    'text-white/75' => $isClaimed,
                    'text-ink-faint' => ! $isClaimed,
                ])>{{ $task->notes }}</span>
            @endif
        </button>

        {{-- Never color-only: the initial + name are what actually say who this
             is for, the color is a scan-at-a-glance layer on top. --}}
        @if ($task->assigned_to && $task->assignee)
            <span
                @class([
                    'grid h-6 w-6 flex-none place-items-center rounded-full text-[11px] font-medium',
                    'bg-white/25 text-white' => $isClaimed,
                    'text-white' => $isDone,
                ])
                @if ($isDone && $assigneeColor)
                    style="background-color: {{ \App\Livewire\Support\FamilyColors::rgb($assigneeColor) }}; opacity: 0.65"
                @endif
                title="{{ $task->assignee->name }}"
                aria-hidden="true"
            >{{ Str::of($task->assignee->name)->trim()->substr(0, 1)->upper() }}</span>
        @endif
    </div>

    <div class="mt-2 flex items-center justify-between gap-1">
        <span @class([
            'text-[10.5px]',
            'text-white/70' => $isClaimed,
            'text-ink-faint' => ! $isClaimed,
        ])>
            @if ($isDone)
                {{ $task->completer?->name ?? 'Erledigt' }}
            @elseif ($isClaimed)
                {{ $task->assignee?->name }}
            @else
                Frei
            @endif
        </span>

        {{-- Always visible but muted on touch (no hover there), fully revealed
             on hover/focus on desktop — same convention as the task card's
             own quick-date ghost placeholder (CLAUDE.md, Quick actions). --}}
        <div class="flex items-center gap-0.5 opacity-60 transition group-hover/fam:opacity-100 focus-within:opacity-100">
            <button
                type="button"
                wire:click="startEditTask({{ $task->id }})"
                @class([
                    'grid h-6 w-6 place-items-center rounded-card transition focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-overprint',
                    'text-white/80 hover:bg-white/20 hover:text-white' => $isClaimed,
                    'text-ink-faint hover:bg-paper hover:text-ink' => ! $isClaimed,
                ])
                aria-label="Bearbeiten: {{ $task->title }}"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
</div>
