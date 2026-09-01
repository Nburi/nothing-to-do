{{--
    Real-data thumbnail for one App\Services\ListConcepts::CATALOG entry —
    the user's own active board tasks, bucketed the same way that concept's
    real board buckets them. Shared by Settings' "Listen-Konzept" card and
    the Onboarding tutorial's Konzept step, so the two previews can never
    drift apart. Expects $conceptKey (a ListConcepts::CATALOG key) and
    $previewTasks (Collection<Task>, from listConceptPreviewTasks()).
--}}
@if ($conceptKey === 'three_things')
    @php
        $threeThingsColumns = [
            'inbox' => ['label' => 'Inbox', 'tasks' => $previewTasks->where('list', 'inbox')],
            'todos' => ['label' => 'To-Dos', 'tasks' => $previewTasks->where('list', 'todos')],
            'tasks' => ['label' => 'Tasks', 'tasks' => $previewTasks->where('list', 'tasks')],
        ];
    @endphp
    <div class="grid grid-cols-3 gap-2">
        @foreach ($threeThingsColumns as $column)
            <div class="rounded-[0.5rem] border border-line/60 bg-surface p-2">
                <p class="mb-1 text-[9px] font-medium uppercase tracking-[0.08em] text-ink-faint">{{ $column['label'] }}</p>
                @forelse ($column['tasks']->take(2) as $previewTask)
                    <p class="truncate text-[10px] leading-relaxed text-ink-soft">{{ $previewTask->title }}</p>
                @empty
                    <p class="text-[10px] text-ink-faint">—</p>
                @endforelse
            </div>
        @endforeach
    </div>
@elseif ($conceptKey === 'simple')
    {{-- One flat list, no columns to split into — up to 4 of the same real
         tasks the other thumbnails read from, stacked. --}}
    <div class="rounded-[0.5rem] border border-line/60 bg-surface p-2">
        @forelse ($previewTasks->take(4) as $previewTask)
            <p class="truncate text-[10px] leading-relaxed text-ink-soft">{{ $previewTask->title }}</p>
        @empty
            <p class="text-[10px] text-ink-faint">—</p>
        @endforelse
    </div>
@elseif ($conceptKey === 'eisenhower')
    @php
        $eisenhowerQuadrants = [
            ['label' => 'Wichtig & Dringend', 'tasks' => $previewTasks->filter(fn ($t) => $t->is_important && $t->isUrgent())],
            ['label' => 'Wichtig & Nicht dringend', 'tasks' => $previewTasks->filter(fn ($t) => $t->is_important && ! $t->isUrgent())],
            ['label' => 'Nicht wichtig & Dringend', 'tasks' => $previewTasks->filter(fn ($t) => ! $t->is_important && $t->isUrgent())],
            ['label' => 'Nicht wichtig & Nicht dringend', 'tasks' => $previewTasks->filter(fn ($t) => ! $t->is_important && ! $t->isUrgent())],
        ];
    @endphp
    <div class="grid grid-cols-2 gap-2">
        @foreach ($eisenhowerQuadrants as $quadrant)
            <div class="rounded-[0.5rem] border border-line/60 bg-surface p-2">
                <p class="mb-1 text-[9px] font-medium uppercase tracking-[0.08em] text-ink-faint">{{ $quadrant['label'] }}</p>
                @forelse ($quadrant['tasks']->take(2) as $previewTask)
                    <p class="truncate text-[10px] leading-relaxed text-ink-soft">{{ $previewTask->title }}</p>
                @empty
                    <p class="text-[10px] text-ink-faint">—</p>
                @endforelse
            </div>
        @endforeach
    </div>
@elseif ($conceptKey === 'kanban')
    {{-- Only Backlog/In Arbeit are shown — ListConcepts::previewTasksFor()
         samples active tasks only (same as every other concept's own
         thumbnail, e.g. Eisenhower's four quadrants above), so a completed
         task never appears in this preview and an "Erledigt" bucket here
         would always read as a misleading, permanently-empty placeholder. --}}
    @php
        $kanbanColumns = [
            ['label' => 'Backlog', 'tasks' => $previewTasks->where('is_today', false)],
            ['label' => 'In Arbeit', 'tasks' => $previewTasks->where('is_today', true)],
        ];
    @endphp
    <div class="grid grid-cols-2 gap-2">
        @foreach ($kanbanColumns as $column)
            <div class="rounded-[0.5rem] border border-line/60 bg-surface p-2">
                <p class="mb-1 text-[9px] font-medium uppercase tracking-[0.08em] text-ink-faint">{{ $column['label'] }}</p>
                @forelse ($column['tasks']->take(2) as $previewTask)
                    <p class="truncate text-[10px] leading-relaxed text-ink-soft">{{ $previewTask->title }}</p>
                @empty
                    <p class="text-[10px] text-ink-faint">—</p>
                @endforelse
            </div>
        @endforeach
    </div>
@endif
