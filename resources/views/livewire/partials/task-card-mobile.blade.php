@php
    $isInbox = $task->isInbox();
    // right = swipe-right action (anchored left), left = swipe-left action (anchored right)
    $rightIntent = $isInbox ? 'todos' : ($task->is_today ? 'untoday' : 'today');
    $leftIntent = $isInbox ? 'tasks' : 'edit';
    $meta = [
        'todos'   => ['label' => 'To-Dos',      'bg' => 'bg-forest',  'fg' => 'text-white'],
        'tasks'   => ['label' => 'Tasks',        'bg' => 'bg-contour', 'fg' => 'text-white'],
        'today'   => ['label' => 'Heute',        'bg' => 'bg-forest',  'fg' => 'text-white'],
        'untoday' => ['label' => 'Kein Heute',   'bg' => 'bg-ink',     'fg' => 'text-paper'],
        'edit'    => ['label' => 'Bearbeiten',   'bg' => 'bg-ink',     'fg' => 'text-paper'],
    ];
    $rm = $meta[$rightIntent];
    $lm = $meta[$leftIntent];
@endphp

<div
    wire:key="m-task-{{ $task->id }}"
    class="relative select-none"
    x-data="swipeCard({ id: {{ $task->id }}, right: '{{ $rightIntent }}', left: '{{ $leftIntent }}' })"
>
    {{-- swipe-right action, anchored left --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-start gap-2 rounded-card pl-5 text-sm font-medium {{ $rm['bg'] }} {{ $rm['fg'] }}"
        x-show="dir === 'right'" :style="{ opacity: progress }" style="display: none;"
    >
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        {{ $rm['label'] }}
    </div>

    {{-- swipe-left action, anchored right --}}
    <div
        class="pointer-events-none absolute inset-0 flex items-center justify-end gap-2 rounded-card pr-5 text-sm font-medium {{ $lm['bg'] }} {{ $lm['fg'] }}"
        x-show="dir === 'left'" :style="{ opacity: progress }" style="display: none;"
    >
        {{ $lm['label'] }}
        <span :style="'transform: scale(' + (0.85 + progress * 0.15) + ')'" class="inline-flex">
            @if ($leftIntent === 'edit')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M14 2l4 4-10 10H4v-4L14 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @else
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endif
        </span>
    </div>

    {{-- the card that tracks the finger --}}
    {{-- touch-action lives in a class, not inline style: Alpine's :style transform
         binding replaces the whole style attribute each frame and would wipe an
         inline touch-action, letting the browser steal the horizontal swipe. --}}
    <div
        @class([
            'relative flex touch-pan-y items-start gap-3 rounded-card border py-3 pl-3 pr-2 shadow-map',
            'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft' => $task->is_important && !$task->is_completed,
            'border-line bg-surface' => !$task->is_important && !$task->is_completed,
            'border-line bg-surface opacity-50' => $task->is_completed,
        ])
        :class="{ 'transition-transform duration-200 ease-tactile': !dragging }"
        :style="'transform: translateX(' + dx + 'px)'"
        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"
    >

        <button
            type="button"
            wire:click.stop="toggleComplete({{ $task->id }})"
            @class([
                'mt-px grid h-[22px] w-[22px] flex-none place-items-center rounded-full border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-forest',
                'border-forest bg-forest text-white' => $task->is_completed,
                'border-line text-transparent hover:border-forest hover:text-forest' => !$task->is_completed,
            ])
            aria-label="{{ $task->is_completed ? 'Als offen markieren' : 'Erledigt markieren' }}: {{ $task->title }}"
        >
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <button type="button" wire:click="toggleImportant({{ $task->id }})" class="min-w-0 flex-1 text-left">
            <span @class([
                'block break-words text-[15px] leading-snug',
                'line-through text-ink-faint' => $task->is_completed,
                'font-medium text-ink' => !$task->is_completed && $task->is_important,
                'text-ink' => !$task->is_completed && !$task->is_important,
            ])>{{ $task->title }}</span>
            @if (!$task->is_completed && ($label = $task->effectiveDateLabel()))
                <span class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium
                    {{ $task->isOverdue() ? 'bg-signal-soft text-signal' : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}">
                    @unless ($task->isOverdue())
                        <span class="inline-block h-1 w-1 rounded-full {{ $task->effectiveIsHard() ? 'bg-contour' : 'bg-ink-faint' }}" aria-hidden="true"></span>
                    @endunless
                    {{ $label }}
                </span>
            @endif
        </button>

        <button
            type="button"
            @click.stop="menuOpen = true"
            class="grid h-7 w-7 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
            aria-label="Aktionen"
        >
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="8" cy="3" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="8" cy="13" r="1.4"/></svg>
        </button>
    </div>

    <div
        x-show="menuOpen"
        x-transition.opacity.duration.120ms
        @click.outside="menuOpen = false"
        @keydown.escape.window="menuOpen = false"
        class="absolute right-2 top-14 z-20 w-40 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
        style="display: none;"
    >
        <button type="button" wire:click="startEdit({{ $task->id }})" @click="menuOpen = false" class="block w-full px-3 py-2 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
            Bearbeiten
        </button>
        <button type="button" wire:click="deleteTask({{ $task->id }})" @click="menuOpen = false" class="block w-full px-3 py-2 text-left text-sm text-signal transition hover:bg-signal-soft">
            Löschen
        </button>
    </div>
</div>
