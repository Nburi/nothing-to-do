{{-- One step in the emergency sequence. Drag handle + position + title (opens
     the shared edit sheet) + which dashboard column it surfaces under + delete. --}}
<div
    wire:key="etask-{{ $task->id }}"
    data-id="{{ $task->id }}"
    class="flex items-center gap-2 rounded-card border border-line bg-surface py-2 pl-2 pr-2.5 shadow-map"
>
    <span class="grid h-7 w-7 flex-none cursor-grab place-items-center rounded-card text-ink-faint active:cursor-grabbing" aria-hidden="true">
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="4" r="1.1"/><circle cx="5" cy="8" r="1.1"/><circle cx="5" cy="12" r="1.1"/><circle cx="11" cy="4" r="1.1"/><circle cx="11" cy="8" r="1.1"/><circle cx="11" cy="12" r="1.1"/></svg>
    </span>

    <span class="tnum grid h-5 w-5 flex-none place-items-center rounded-full border border-line text-[10px] text-ink-soft" aria-hidden="true">{{ $order }}</span>

    <button
        type="button"
        wire:click="startEdit({{ $task->id }})"
        class="min-w-0 flex-1 truncate rounded px-1 py-1 text-left text-sm text-ink transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
        title="Bearbeiten: {{ $task->title }}"
    >
        {{ $task->title }}
    </button>

    <div class="flex flex-none overflow-hidden rounded-[0.45rem] border border-line text-[10.5px]" role="group" aria-label="Zielliste: {{ $task->title }}">
        @foreach (['inbox' => 'Inbox', 'todos' => 'To-Dos', 'tasks' => 'Tasks'] as $value => $label)
            <button
                type="button"
                wire:click="setTaskList({{ $task->id }}, '{{ $value }}')"
                aria-pressed="{{ $task->emergency_list === $value ? 'true' : 'false' }}"
                @class([
                    'px-2 py-1 transition',
                    'bg-forest-soft font-medium text-forest' => $task->emergency_list === $value,
                    'text-ink-faint hover:text-ink-soft' => $task->emergency_list !== $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <button
        type="button"
        x-data="{ armed: false, _t: null }"
        @click="if (armed) { $wire.deleteTask({{ $task->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
        @click.outside="armed = false; clearTimeout(_t)"
        @keydown.escape.window="armed = false; clearTimeout(_t)"
        :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
        class="grid h-7 w-7 flex-none place-items-center rounded-card transition focus:outline-none focus-visible:ring-2 focus-visible:ring-signal"
        aria-label="Löschen: {{ $task->title }}"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3 4.5h10M6.5 3h3M4.5 4.5l.5 9h6l.5-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
</div>
