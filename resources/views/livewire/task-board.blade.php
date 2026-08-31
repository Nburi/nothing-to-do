<div>
    {{--
        The one seam TaskBoard branches on for which mental model to render
        (see App\Services\ListConcepts + CLAUDE.md's "To-Do-Listen-Konzepte").
        Every existing computed property/mutation on TaskBoard/ManagesTasks is
        shared across every concept; a concept session only ever adds a new
        `@case` + its own partial here, never touches another concept's
        branch. $this->listConcept already self-heals (ListConcepts::for())
        to 'three_things' for a stored value that isn't currently available,
        so @default below is a second, redundant safety net, not the normal path.
    --}}
    @switch($this->listConcept)
        @case('three_things')
            @include('livewire.partials.board-three-things')
            @break

        @case('kanban')
            @include('livewire.partials.board-kanban')
            @break

        {{-- 'simple' / 'eisenhower' — added by their own sessions, each its
             own @case + partials/board-<key>.blade.php. --}}

        @default
            @include('livewire.partials.board-three-things')
    @endswitch
</div>
