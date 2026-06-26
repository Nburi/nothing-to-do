@php
    use Illuminate\Support\Carbon;
    use App\Models\ScheduleEvent;

    $span = $dayEnd - $dayStart;
    $ppm = 0.8;
    $pomo = auth()->user()->pomodoro();
    $target = Carbon::parse($targetDate);
    $wdFull = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $dayName = $wdFull[$target->dayOfWeekIso - 1];
    $steps = ['Zeitplan', 'Fokus', 'Review'];

    $cls = fn ($token) => match ($token) {
        'forest' => ['bg-forest-soft', 'border-forest/40', 'text-forest', 'bg-forest'],
        'overprint' => ['bg-overprint-soft', 'border-overprint/40', 'text-overprint', 'bg-overprint'],
        'signal' => ['bg-signal-soft', 'border-signal/40', 'text-signal', 'bg-signal'],
        'ink-faint', 'ink' => ['bg-surface', 'border-line', 'text-ink-faint', 'bg-ink-faint/50'],
        default => ['bg-contour-soft', 'border-contour/40', 'text-contour', 'bg-contour'],
    };
@endphp

<div class="mx-auto max-w-xl px-4 pb-28 pt-5">
    {{-- Progress --}}
    <div class="mb-5">
        <div class="flex items-center justify-between">
            <a href="{{ url('/app') }}" wire:navigate class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Brief abbrechen">
                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </a>
            <span class="text-xs text-ink-faint">Brief · Schritt {{ $step }} / 3</span>
        </div>
        <div class="mt-3 flex gap-1.5">
            @foreach ($steps as $i => $label)
                @php $n = $i + 1; @endphp
                <div @class([
                    'h-1 flex-1 rounded transition-colors',
                    'bg-forest' => $n < $step,
                    'bg-overprint' => $n === $step,
                    'bg-line' => $n > $step,
                ])></div>
            @endforeach
        </div>
        <div class="mt-1.5 flex justify-between">
            @foreach ($steps as $i => $label)
                <span class="text-[10px] {{ ($i + 1) === $step ? 'font-medium text-overprint' : 'text-ink-faint' }}">{{ $i + 1 }} · {{ $label }}</span>
            @endforeach
        </div>
    </div>

    {{-- ════════ STEP 1 · Zeitplan + freie Zeit ════════ --}}
    @if ($step === 1)
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-medium text-ink">{{ $dayName }} planen</h1>
                <p class="text-sm text-ink-soft">Termine prüfen · Arbeitszeit markieren</p>
            </div>
            <span class="flex-none rounded-card bg-forest-soft px-2.5 py-1 text-xs font-medium text-forest">
                {{ $this->capacity }} {{ $this->capacity === 1 ? 'Session' : 'Sessions' }} möglich
            </span>
        </div>

        <div class="rounded-card border border-line bg-surface p-2">
            <div
                class="relative"
                style="height: {{ $span * $ppm }}px; touch-action: none"
                data-grid data-ppm="{{ $ppm }}" data-day-start="{{ $dayStart }}"
                x-data="freePaint()"
                @pointerdown="begin($event)" @pointermove="move($event)" @pointerup="end()" @pointercancel="end()"
            >
                @for ($h = intval($dayStart / 60); $h <= intval($dayEnd / 60); $h++)
                    <div class="pointer-events-none absolute inset-x-0 border-t border-line/40" style="top: {{ ($h * 60 - $dayStart) * $ppm }}px"></div>
                    <span class="pointer-events-none tnum absolute left-0 -translate-y-1/2 text-[10px] text-ink-faint" style="top: {{ ($h * 60 - $dayStart) * $ppm }}px">{{ sprintf('%02d', $h) }}</span>
                @endfor

                {{-- Appointments (read-only here) --}}
                @foreach ($this->dayAppointments as $a)
                    @php [$bg, $bd, $tx, $bar] = $cls($a->colorToken()); @endphp
                    <div class="pointer-events-none absolute left-[3.4rem] right-2 overflow-hidden rounded-[7px] border {{ $bg }} {{ $bd }} px-2 py-1"
                        style="top: {{ ($a->startMinutes() - $dayStart) * $ppm }}px; height: {{ max($a->durationMinutes() * $ppm, 16) }}px">
                        <span class="absolute inset-y-0 left-0 w-1 {{ $bar }}"></span>
                        <p class="truncate pl-1.5 text-[12px] font-medium text-ink">{{ $a->title }}</p>
                    </div>
                @endforeach

                {{-- Painted free-time blocks --}}
                @foreach ($freeBlocks as $i => $b)
                    @php $sess = $this->sessionsIn($b['start'], $b['end']); $dur = $b['end'] - $b['start']; @endphp
                    <div class="absolute left-[3.4rem] right-2 overflow-hidden rounded-[7px] border-2 border-dashed border-forest bg-forest-soft/60 px-2 py-1"
                        style="top: {{ ($b['start'] - $dayStart) * $ppm }}px; height: {{ max($dur * $ppm, 18) }}px">
                        <div class="flex items-start justify-between gap-1">
                            <div class="min-w-0">
                                <p class="truncate text-[12px] font-medium text-forest">Freie Arbeitszeit</p>
                                <p class="truncate text-[11px] text-ink-soft">{{ intdiv($dur, 60) }} Std {{ $dur % 60 }} Min · ≈ {{ $sess }} {{ $sess === 1 ? 'Session' : 'Sessions' }}</p>
                            </div>
                            <button wire:click="removeFreeBlock({{ $i }})" class="grid h-5 w-5 flex-none place-items-center rounded text-forest/80 transition hover:bg-forest/10" aria-label="Entfernen">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach

                {{-- Provisional paint preview --}}
                <div x-show="painting" x-cloak class="pointer-events-none absolute left-[3.4rem] right-2 rounded-[7px] border-2 border-dashed border-forest bg-forest-soft/50" x-bind:style="`top:${provTop}px; height:${provHeight}px`"></div>

                @if (empty($freeBlocks))
                    <div class="pointer-events-none absolute inset-x-4 bottom-3 text-center">
                        <p class="text-xs text-ink-faint">Tippe & ziehe auf eine freie Stelle, um Arbeitszeit zu markieren.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button wire:click="openEventForm('{{ $targetDate }}')" class="inline-flex items-center gap-1.5 rounded-card border border-line bg-surface px-3 py-1.5 text-sm text-ink-soft transition hover:text-ink active:scale-95">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Termin
            </button>
            @foreach ($this->templates as $t)
                <button wire:click="applyTemplate({{ $t->id }}, '{{ $targetDate }}')" class="rounded-card border border-line bg-surface px-2.5 py-1.5 text-xs text-ink-soft transition hover:text-ink active:scale-95">+ {{ $t->name }}</button>
            @endforeach
        </div>
    @endif

    {{-- ════════ STEP 2 · Fokus wählen ════════ --}}
    @if ($step === 2)
        @php
            $cap = $this->capacity;
            $dem = $this->demand;
            $over = $dem > $cap;
            $fillPct = $cap > 0 ? min(100, round($dem / $cap * 100)) : ($dem > 0 ? 100 : 0);
        @endphp
        <div class="mb-3">
            <h1 class="text-lg font-medium text-ink">Was nimmst du dir vor?</h1>
            <p class="text-sm text-ink-soft">To-Dos & Tasks wählen — Sessions live</p>
        </div>

        <div class="mb-4">
            <div class="mb-1.5 flex items-center justify-between">
                <span class="text-xs text-ink-soft">Arbeitszeit belegt</span>
                <span class="tnum text-xs font-medium {{ $over ? 'text-signal' : 'text-ink' }}">{{ $dem }} / {{ $cap }} Sessions</span>
            </div>
            <div class="flex h-2 overflow-hidden rounded-full bg-line">
                <div class="{{ $over ? 'bg-signal' : 'bg-forest' }} transition-[width] duration-300" style="width: {{ $fillPct }}%"></div>
            </div>
            @if ($over)
                <div class="mt-2 flex items-center gap-2 rounded-card border border-signal/30 bg-signal-soft px-3 py-2">
                    <svg class="h-4 w-4 flex-none text-signal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <span class="text-xs text-ink-soft">{{ $dem - $cap }} {{ $dem - $cap === 1 ? 'Session' : 'Sessions' }} mehr gewünscht als geplant</span>
                </div>
            @endif
        </div>

        {{-- Tasks --}}
        <h2 class="mb-2 text-[11px] font-medium uppercase tracking-wide text-ink-faint">Tasks</h2>
        <div class="space-y-2">
            @forelse ($this->candidateTasks as $task)
                @php $sel = isset($this->taskSessions[$task->id]); $label = $task->effectiveDateLabel(); @endphp
                <div @class(['flex items-center gap-3 rounded-card border bg-surface px-3 py-2.5 transition', 'border-forest/50' => $sel, 'border-line' => ! $sel])>
                    <button wire:click="toggleTask({{ $task->id }})" @class(['grid h-5 w-5 flex-none place-items-center rounded-md border transition', 'border-forest bg-forest text-white' => $sel, 'border-line text-transparent' => ! $sel]) aria-label="Auswählen">
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $task->title }}</p>
                        <p class="text-[11px] {{ $task->isOverdue() ? 'text-signal' : 'text-ink-faint' }}">
                            @if ($label) {{ $task->effectiveIsHard() ? 'Deadline' : 'Wunsch' }} {{ $label }}
                            @else wartet seit {{ max(0, (int) $task->created_at->diffInDays(now())) }} Tagen @endif
                        </p>
                    </div>
                    @if ($sel)
                        <div class="flex flex-none items-center gap-1.5">
                            <button wire:click="setSessions({{ $task->id }}, {{ $this->taskSessions[$task->id] - 1 }})" class="grid h-7 w-7 place-items-center rounded-md border border-line bg-paper text-ink-soft transition hover:text-ink active:scale-95" aria-label="Weniger">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                            <span class="tnum w-8 text-center text-sm font-medium text-ink">{{ $this->taskSessions[$task->id] }}×</span>
                            <button wire:click="setSessions({{ $task->id }}, {{ $this->taskSessions[$task->id] + 1 }})" class="grid h-7 w-7 place-items-center rounded-md border border-line bg-paper text-ink-soft transition hover:text-ink active:scale-95" aria-label="Mehr">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="rounded-card border border-dashed border-line px-3 py-4 text-center text-xs text-ink-faint">Keine offenen Tasks.</p>
            @endforelse
        </div>

        {{-- To-Do-Session --}}
        <div class="mt-5">
            <div class="mb-2 flex items-center gap-2">
                <h2 class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">To-Do-Session</h2>
                <span class="text-[10px] text-ink-faint">25 Min · max. 1 / Tag</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @forelse ($this->candidateTodos as $todo)
                    @php $sel = in_array($todo->id, $selectedTodos, true); @endphp
                    <button wire:click="toggleTodo({{ $todo->id }})" @class([
                        'inline-flex items-center gap-1.5 rounded-card border px-2.5 py-1.5 text-xs transition active:scale-95',
                        'border-forest bg-forest-soft text-forest' => $sel,
                        'border-line bg-surface text-ink-soft hover:text-ink' => ! $sel,
                    ])>
                        @if ($sel)
                            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6 4.5 8.5 10 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @endif
                        {{ $todo->title }}
                    </button>
                @empty
                    <p class="text-xs text-ink-faint">Keine offenen To-Dos.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ════════ STEP 3 · Review ════════ --}}
    @if ($step === 3)
        @php
            $events = collect($this->plan['events']);
            $titles = $this->candidateTasks->pluck('title', 'id');
            $cWork = $events->where('type', 'work_session')->count();
            $cTodo = $events->where('type', 'todo_session')->count();
            $cBreak = $events->where('type', 'break')->count();
            $cAppt = $this->dayAppointments->count();

            $rows = collect();
            foreach ($this->dayAppointments as $a) {
                $rows->push(['min' => $a->startMinutes(), 'time' => $a->start_time, 'token' => $a->colorToken(), 'label' => $a->title, 'sub' => 'Termin']);
            }
            foreach ($events as $e) {
                $min = ScheduleEvent::toMinutes($e['start_time']);
                if ($e['type'] === 'break') {
                    $rows->push(['min' => $min, 'time' => $e['start_time'], 'token' => 'ink-faint', 'label' => 'Pause', 'sub' => null]);
                } elseif ($e['type'] === 'todo_session') {
                    $rows->push(['min' => $min, 'time' => $e['start_time'], 'token' => 'forest', 'label' => 'To-Do-Session', 'sub' => count($selectedTodos).' To-Dos']);
                } else {
                    $t = $titles[$e['suggested_task_id']] ?? null;
                    $rows->push(['min' => $min, 'time' => $e['start_time'], 'token' => 'forest', 'label' => 'Work-Session', 'sub' => $t ? 'Vorschlag: '.$t : 'Fokuszeit']);
                }
            }
            $rows = $rows->sortBy('min')->values();
        @endphp

        <div class="mb-3">
            <h1 class="text-lg font-medium text-ink">Dein {{ $dayName }}</h1>
            <p class="text-sm text-ink-soft">Sessions sind Vorschläge — nichts ist fix zugewiesen</p>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <span class="rounded-card bg-forest-soft px-2.5 py-1 text-xs font-medium text-forest">{{ $cWork }} Work-Sessions</span>
            @if ($cTodo) <span class="rounded-card bg-forest-soft px-2.5 py-1 text-xs font-medium text-forest">{{ $cTodo }} To-Do-Session</span> @endif
            <span class="rounded-card border border-line bg-surface px-2.5 py-1 text-xs text-ink-soft">{{ $cBreak }} Pausen</span>
            @if ($cAppt) <span class="rounded-card bg-contour-soft px-2.5 py-1 text-xs font-medium text-contour">{{ $cAppt }} Termine</span> @endif
        </div>

        @if ($this->plan['unplaced'] > 0)
            <div class="mb-4 flex items-center gap-2 rounded-card border border-signal/30 bg-signal-soft px-3 py-2">
                <svg class="h-4 w-4 flex-none text-signal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <span class="text-xs text-ink-soft">{{ $this->plan['unplaced'] }} {{ $this->plan['unplaced'] === 1 ? 'Session hat' : 'Sessions haben' }} keinen Platz gefunden</span>
            </div>
        @endif

        <div class="relative rounded-card border border-line bg-surface p-4 pl-16">
            <div class="absolute bottom-5 left-[3.4rem] top-5 w-0.5 rounded bg-line"></div>
            @forelse ($rows as $r)
                @php [$bg, $bd, $tx, $bar] = $cls($r['token']); @endphp
                <div class="relative mb-2.5 last:mb-0">
                    <span class="tnum absolute -left-12 top-2 w-10 text-right text-[11px] text-ink-soft">{{ $r['time'] }}</span>
                    <span class="absolute -left-[0.85rem] top-3 h-2 w-2 rounded-full {{ $bar }}"></span>
                    <div class="rounded-[8px] border {{ $bd }} {{ $bg }} px-3 py-2">
                        <p class="text-[13px] font-medium {{ $tx }}">{{ $r['label'] }}</p>
                        @if ($r['sub']) <p class="truncate text-[11px] text-ink-soft">{{ $r['sub'] }}</p> @endif
                    </div>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-ink-faint">Noch nichts geplant. Geh zurück und markiere Arbeitszeit.</p>
            @endforelse
        </div>
    @endif

    {{-- ════════ Footer nav ════════ --}}
    @php $blockNext = $step === 1 && $this->freeMinutes === 0; @endphp
    <div class="fixed inset-x-0 bottom-0 border-t border-line bg-paper/90 px-4 py-3 backdrop-blur-sm">
        <div class="mx-auto max-w-xl">
            @if ($step === 3 && $this->hasExistingPlan)
                <p class="mb-2 text-center text-[11px] text-ink-faint">Ersetzt den bestehenden Plan für diesen Tag.</p>
            @elseif ($blockNext)
                <p class="mb-2 text-center text-[11px] text-ink-faint">Markiere zuerst etwas Arbeitszeit.</p>
            @endif
            <div class="flex items-center gap-3">
                @if ($step > 1)
                    <button wire:click="prevStep" class="rounded-card border border-line bg-surface px-4 py-2.5 text-sm font-medium text-ink-soft transition hover:text-ink active:scale-95">Zurück</button>
                @endif
                @if ($step < 3)
                    <button wire:click="nextStep" @disabled($blockNext) @class([
                        'flex-1 rounded-card bg-forest px-4 py-2.5 text-sm font-medium text-white transition active:scale-[0.98]',
                        'hover:brightness-110' => ! $blockNext,
                        'cursor-not-allowed opacity-50' => $blockNext,
                    ])>Weiter</button>
                @else
                    <button wire:click="finalize" class="flex-1 rounded-card bg-forest px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Tagesplan erstellen</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Appointment add/edit sheet (shared with the Schedule page) --}}
    @include('livewire.partials.schedule-event-form')
</div>
