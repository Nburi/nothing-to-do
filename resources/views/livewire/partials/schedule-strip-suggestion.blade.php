{{-- "What to work on" nudge inside the focus card (Bereit + Läuft/work states). Shares
     $suggestion from the including view — see TaskBoard::taskSuggestion(). --}}
<p class="truncate text-xs text-ink-faint">
    Vorschlag:
    @if ($suggestion['kind'] === 'emergency')
        <button type="button" wire:click="startEdit({{ $suggestion['task_id'] }})" class="font-medium text-signal hover:underline">{{ $suggestion['subtitle'] }} · {{ $suggestion['title'] }}</button>
    @elseif ($suggestion['kind'] === 'todos')
        <span class="text-ink-soft">{{ $suggestion['title'] }} · {{ $suggestion['subtitle'] }}</span>
    @elseif ($suggestion['kind'] === 'project')
        <a href="{{ route('project.show', $suggestion['project_id']) }}" wire:navigate class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['title'] }} · {{ $suggestion['subtitle'] }}</a>
    @elseif ($suggestion['kind'] === 'category_group')
        <a href="{{ route('group.show', $suggestion['group_id']) }}" wire:navigate class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['title'] }} · {{ $suggestion['subtitle'] }}</a>
    @elseif ($suggestion['kind'] === 'category_agenda')
        <a href="{{ route('agenda') }}" wire:navigate class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['subtitle'] }} · {{ $suggestion['title'] }}</a>
    @elseif ($suggestion['kind'] === 'agenda_generic' || $suggestion['kind'] === 'category_text')
        <span class="text-ink-soft">{{ $suggestion['title'] }}{{ isset($suggestion['subtitle']) ? ' · '.$suggestion['subtitle'] : '' }}</span>
    @else
        <button type="button" wire:click="startEdit({{ $suggestion['task_id'] }})" class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['title'] }}</button>
    @endif
</p>
