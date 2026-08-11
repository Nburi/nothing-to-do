{{-- A task group inside a board column. Variables: $box (see TaskBoard::groupBoxesFor),
     $mobile (bool). Shows the group's name, its progress and the next two entries
     for this column; the name opens the group's own dashboard.

     The whole box is a drop target: a card dropped here joins the group and keeps
     its list, so an Inbox card lands in the group's Inbox. The deliberate visual
     restraint (a 3px left edge, no new accent colour) is because a group is
     structure, not urgency — green and red stay reserved for Heute and Notfall. --}}
@php
    $group = $box['group'];
    $pct = $box['total'] > 0 ? round(($box['done'] / $box['total']) * 100) : 0;
    $naming = $namingGroupId === $group->id;
@endphp

<div
    wire:key="group-box-{{ $group->id }}-{{ $list }}"
    class="group/gbox mb-2.5 rounded-card border border-line border-l-[3px] border-l-ink-faint/55 bg-surface/55 p-2.5 transition-colors [body.dragging-task_&]:border-l-forest [body.dragging-task_&]:bg-forest-soft/30"
    data-group-id="{{ $group->id }}"
    x-data
    x-init="window.groupDropZone($el, $wire)"
>
    <div class="mb-2 flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5 flex-none text-ink-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <rect x="2" y="4.5" width="12" height="9" rx="1.6" stroke="currentColor" stroke-width="1.3"/>
            <path d="M4 2.5h8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
        </svg>

        @if ($naming)
            {{-- Fresh group straight out of the drag gesture: name it here and
                 stay on the board. Escape or an empty Enter keeps "Neue Gruppe". --}}
            <form wire:submit="saveGroupName" class="flex min-w-0 flex-1 items-center gap-1.5">
                <input
                    type="text"
                    wire:model="groupNameDraft"
                    autofocus
                    placeholder="Name der Gruppe"
                    aria-label="Name der neuen Gruppe"
                    @keydown.escape="$wire.stopNamingGroup()"
                    class="min-w-0 flex-1 rounded-[0.4rem] border border-forest bg-paper px-1.5 py-0.5 text-xs font-medium text-ink placeholder:font-normal placeholder:text-ink-faint focus:border-forest focus:ring-0"
                />
                <button type="submit" class="flex-none rounded-[0.4rem] bg-forest px-2 py-0.5 text-[11px] font-medium text-white transition hover:brightness-110">OK</button>
            </form>
        @else
            <a
                href="{{ route('group.show', $group) }}"
                wire:navigate
                class="min-w-0 flex-1 truncate rounded text-xs font-medium text-ink underline decoration-dotted decoration-ink-faint/60 underline-offset-2 transition hover:decoration-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
            >{{ $group->name }}</a>
            <span class="tnum flex-none text-[11px] text-ink-faint">{{ $box['done'] }}/{{ $box['total'] }}</span>
            <svg class="h-3 w-3 flex-none text-ink-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @endif
    </div>

    @if ($box['total'] > 0)
        <div class="mb-2 h-1 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $box['done'] }}" aria-valuemax="{{ $box['total'] }}" aria-label="Fortschritt {{ $group->name }}">
            <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
        </div>
    @endif

    @if ($box['compact'])
        <p class="px-0.5 text-[11px] leading-snug text-ink-faint">
            @if ($box['inbox'] > 0)
                {{ $box['inbox'] }} {{ $box['inbox'] === 1 ? 'Aufgabe' : 'Aufgaben' }} in der Gruppen-Inbox — noch nicht sortiert
            @elseif ($box['total'] > 0)
                Alle Aufgaben erledigt.
            @else
                Noch keine Aufgaben.
            @endif
        </p>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($box['preview'] as $task)
                @include($mobile ? 'livewire.partials.task-card-mobile' : 'livewire.partials.task-card', ['task' => $task])
            @endforeach
        </div>

        @if ($box['more'] > 0)
            <a
                href="{{ route('group.show', $group) }}"
                wire:navigate
                class="mt-1.5 block px-0.5 text-[11px] text-ink-faint transition hover:text-ink-soft"
            >+{{ $box['more'] }} weitere in dieser Gruppe</a>
        @endif
    @endif
</div>
