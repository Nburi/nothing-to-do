{{-- Cross-concept way to reach existing Projects. 3 Things has its own full Projekte
     column (drag-and-drop, "new project" drop zone, standalone project-list tasks) —
     Simple/Eisenhower/Kanban never had any equivalent, so a Project captured via
     QuickCapture's "Projekt" target while one of those concepts was active was fully
     persisted but practically unreachable: no column, no link, no card, anywhere.
     This is deliberately the lighter-weight fix (a compact, read-only-ish access
     strip) rather than replicating 3 Things' full drag-capable column into three more
     layouts — reusing project-card.blade.php gives every project a real link to its
     own page (and, for free, the same drop-a-task-here-to-assign zone that card
     already carries) without adding any new gesture code. Zero footprint when the
     account has no projects yet, same convention as homework-preview-strip. --}}
@if ($this->projects->isNotEmpty())
    <div class="{{ $spacing }}">
        <h2 class="mb-2 flex items-center gap-2 px-1 text-sm font-medium text-ink">
            Projekte
            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->projects->count() }}</span>
        </h2>
        <div class="-mx-1 flex gap-2.5 overflow-x-auto px-1 pb-1">
            @foreach ($this->projects as $project)
                <div class="w-56 flex-none">
                    @include('livewire.partials.project-card', ['project' => $project])
                </div>
            @endforeach
        </div>
    </div>
@endif
