{{-- Desktop task card. A SortableJS item (data-id). Tap body = important, circle = done. --}}
<div
    wire:key="task-{{ $task->id }}"
    data-id="{{ $task->id }}"
    class="group/card relative flex items-start gap-2.5 rounded-card border py-2.5 pl-3 pr-2 shadow-map transition-colors duration-200
        {{ $task->is_important
            ? 'border-line border-t-[2.5px] border-t-overprint bg-overprint-soft'
            : 'border-line bg-surface hover:border-ink-faint/50' }}"
>

    <button
        type="button"
        wire:click.stop="toggleComplete({{ $task->id }})"
        class="mt-px grid h-5 w-5 flex-none place-items-center rounded-full border-2 border-line text-transparent transition hover:border-forest hover:text-forest focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
        aria-label="Erledigt markieren: {{ $task->title }}"
    >
        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M2.5 6.4 4.8 8.7 9.5 3.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <button
        type="button"
        wire:click="toggleImportant({{ $task->id }})"
        class="min-w-0 flex-1 cursor-pointer rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
        title="Tippen markiert als wichtig"
    >
        <span class="block break-words text-sm leading-snug {{ $task->is_important ? 'font-medium text-ink' : 'text-ink' }}">{{ $task->title }}</span>

        @if ($label = $task->effectiveDateLabel())
            <span class="tnum mt-1 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium
                {{ $task->isOverdue()
                    ? 'bg-signal-soft text-signal'
                    : ($task->effectiveIsHard() ? 'bg-contour-soft text-contour' : 'text-ink-faint') }}">
                @unless ($task->isOverdue())
                    <span class="inline-block h-1 w-1 rounded-full {{ $task->effectiveIsHard() ? 'bg-contour' : 'bg-ink-faint' }}" aria-hidden="true"></span>
                @endunless
                {{ $label }}
            </span>
        @endif
    </button>

    <div x-data="{ open: false }" class="relative flex-none">
        <button
            type="button"
            @click.stop="open = !open"
            class="grid h-7 w-7 place-items-center rounded-card text-ink-faint opacity-0 transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-overprint group-hover/card:opacity-100"
            :class="open && 'opacity-100'"
            aria-label="Aktionen"
        >
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <circle cx="8" cy="3" r="1.4" /><circle cx="8" cy="8" r="1.4" /><circle cx="8" cy="13" r="1.4" />
            </svg>
        </button>

        <div
            x-show="open"
            x-transition.opacity.duration.120ms
            @click.outside="open = false"
            @keydown.escape.window="open = false"
            class="absolute right-0 top-8 z-20 w-36 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
            style="display: none;"
        >
            <button type="button" wire:click="startEdit({{ $task->id }})" @click="open = false" class="block w-full px-3 py-1.5 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                Bearbeiten
            </button>
            <button type="button" wire:click="deleteTask({{ $task->id }})" @click="open = false" class="block w-full px-3 py-1.5 text-left text-sm text-signal transition hover:bg-signal-soft">
                Löschen
            </button>
        </div>
    </div>
</div>
