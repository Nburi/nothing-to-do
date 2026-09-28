{{-- Cross-concept way to reach existing Task-Gruppen. Simple/Eisenhower/Kanban all
     hide a grouped task from its normal spot unless it's important or today (the
     exact same rule 3 Things' own boardTasks() uses), but — unlike 3 Things — none
     of them render the group's own box anywhere, so a grouped task with no important/
     today flag could vanish from the board entirely with no way back to it short of
     the group's URL. Same fix shape as projects-quick-access.blade.php (found and
     fixed for the identical reason with Projects, see that partial's own docblock):
     a compact, read-only-ish strip reusing a card partial, rather than replicating
     3 Things' full per-list group boxes into three more layouts. Zero footprint when
     the account has no groups at all. --}}
@if ($this->taskGroups->isNotEmpty())
    <div class="{{ $spacing }}">
        <h2 class="mb-2 flex items-center gap-2 px-1 text-sm font-medium text-ink">
            Gruppen
            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->taskGroups->count() }}</span>
        </h2>
        <div class="-mx-1 flex gap-2.5 overflow-x-auto px-1 pb-1">
            @foreach ($this->taskGroups as $group)
                <div class="w-56 flex-none" wire:key="group-quick-{{ $group->id }}">
                    @include('livewire.partials.group-card', ['group' => $group])
                </div>
            @endforeach
        </div>
    </div>
@endif
