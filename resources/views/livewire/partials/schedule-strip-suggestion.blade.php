{{-- "What to work on" nudge inside the focus card (Bereit + Läuft/work states). Shares
     $suggestion from the including view — see TaskBoard::taskSuggestion(). --}}
<p class="truncate text-xs text-ink-faint">
    Vorschlag:
    @if ($suggestion['kind'] === 'todos')
        <span class="text-ink-soft">{{ $suggestion['title'] }} · {{ $suggestion['subtitle'] }}</span>
    @elseif ($suggestion['kind'] === 'project')
        <a href="{{ route('project.show', $suggestion['project_id']) }}" wire:navigate class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['title'] }} · {{ $suggestion['subtitle'] }}</a>
    @else
        <button type="button" wire:click="startEdit({{ $suggestion['task_id'] }})" class="text-ink-soft hover:text-ink hover:underline">{{ $suggestion['title'] }}</button>
    @endif
</p>
