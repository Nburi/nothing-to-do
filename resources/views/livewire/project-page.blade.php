<div>
    @php
        $done = $this->doneCount;
        $total = $this->totalCount;
        $pct = $total > 0 ? round(($done / $total) * 100) : 0;
        $hasNotes = $this->brainstormHtml !== '';
    @endphp

    <div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-6 md:pb-12">
        {{-- Back --}}
        <a href="{{ route('app') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded text-sm text-ink-soft transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m10 3-5 5 5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Alle Listen
        </a>

        {{-- Header --}}
        <div class="mt-3 flex items-start justify-between gap-3">
            @if ($renaming)
                <form wire:submit="saveRename" class="flex flex-1 items-center gap-2">
                    <input
                        type="text"
                        wire:model="projectName"
                        autofocus
                        class="min-w-0 flex-1 rounded-card border-line bg-paper text-lg font-medium text-ink focus:border-overprint focus:ring-0"
                    />
                    <button type="submit" class="flex-none rounded-card bg-forest px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Speichern</button>
                    <button type="button" wire:click="cancelRename" class="flex-none rounded-card px-2 py-1.5 text-sm text-ink-soft transition hover:bg-surface">Abbrechen</button>
                </form>
                @error('projectName') <p class="text-xs text-signal">{{ $message }}</p> @enderror
            @else
                <div class="min-w-0">
                    <h1 class="break-words text-xl font-medium tracking-tight text-ink">{{ $this->project->name }}</h1>
                    <p class="mt-1 text-[13px] text-ink-faint">
                        @if ($total === 0)
                            Noch keine Aufgaben.
                        @else
                            <span class="tnum">{{ $done }}</span> von <span class="tnum">{{ $total }}</span> erledigt
                        @endif
                    </p>
                </div>

                <div x-data="{ open: false }" class="relative flex-none">
                    <button
                        type="button"
                        @click.stop="open = !open"
                        @keydown.escape.window="open = false"
                        class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                        aria-label="Projekt-Aktionen"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="8" cy="3" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="8" cy="13" r="1.4"/></svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition.opacity.duration.120ms
                        @click.outside="open = false"
                        class="absolute right-0 top-9 z-20 w-44 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-map"
                        style="display: none;"
                    >
                        <button type="button" wire:click="$set('renaming', true)" @click="open = false" class="block w-full px-3 py-1.5 text-left text-sm text-ink-soft transition hover:bg-paper hover:text-ink">
                            Umbenennen
                        </button>
                        <button
                            type="button"
                            wire:click="deleteProject"
                            wire:confirm="Projekt löschen? Offene Aufgaben wandern zurück in die Inbox."
                            @click="open = false"
                            class="block w-full px-3 py-1.5 text-left text-sm text-signal transition hover:bg-signal-soft"
                        >
                            Projekt löschen
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Progress bar --}}
        @if ($total > 0)
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ $total }}" aria-label="Projektfortschritt">
                <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $pct }}%"></div>
            </div>
        @endif

        {{-- Aufgaben ⇄ Brainstorming: two views of one project, switched client-side. --}}
        <div
            x-data="{
                tab: 'tasks',
                saved: false,
                _t: null,
                openBrainstorm() { this.tab = 'brainstorm'; this.$nextTick(() => { if (this.$refs.ta) { this.$refs.ta.focus(); this.autosize(); } }); },
                autosize() { const ta = this.$refs.ta; if (! ta) return; ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 288) + 'px'; },
                wrap(before, after) {
                    const ta = this.$refs.ta; if (! ta) return;
                    const s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e);
                    ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
                    ta.focus(); ta.setSelectionRange(s + before.length, s + before.length + sel.length);
                    ta.dispatchEvent(new Event('input')); this.autosize();
                },
                prefixLines(prefix) {
                    const ta = this.$refs.ta; if (! ta) return;
                    const v = ta.value, ls = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
                    let le = v.indexOf('\n', ta.selectionEnd); if (le === -1) le = v.length;
                    const block = v.slice(ls, le).split('\n').map(l => prefix + l).join('\n');
                    ta.value = v.slice(0, ls) + block + v.slice(le);
                    ta.focus(); ta.setSelectionRange(ls, ls + block.length);
                    ta.dispatchEvent(new Event('input')); this.autosize();
                },
                flashSaved() { this.saved = true; clearTimeout(this._t); this._t = setTimeout(() => this.saved = false, 1600); },
            }"
            @brainstorm-saved.window="flashSaved()"
            @brainstorm-focus.window="$nextTick(() => document.getElementById('brainstorm-editor')?.focus())"
            class="mt-5"
        >
            {{-- Segmented switch --}}
            <div role="tablist" aria-label="Projektansicht" class="inline-flex rounded-card border border-line bg-paper p-0.5">
                <button
                    type="button" role="tab" x-ref="tabTasks"
                    :aria-selected="tab === 'tasks'" :tabindex="tab === 'tasks' ? 0 : -1"
                    @click="tab = 'tasks'"
                    @keydown.arrow-right.prevent="tab = 'brainstorm'; $nextTick(() => $refs.tabBrain.focus())"
                    class="rounded-[0.5rem] px-3.5 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    :class="tab === 'tasks' ? 'bg-surface text-ink shadow-map' : 'text-ink-soft hover:text-ink'"
                >
                    <span>Aufgaben</span>@if ($total > 0)<span class="tnum ml-1.5 text-ink-faint">{{ $total }}</span>@endif
                </button>
                <button
                    type="button" role="tab" x-ref="tabBrain"
                    :aria-selected="tab === 'brainstorm'" :tabindex="tab === 'brainstorm' ? 0 : -1"
                    @click="openBrainstorm()"
                    @keydown.arrow-left.prevent="tab = 'tasks'; $nextTick(() => $refs.tabTasks.focus())"
                    class="rounded-[0.5rem] px-3.5 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                    :class="tab === 'brainstorm' ? 'bg-surface text-ink shadow-map' : 'text-ink-soft hover:text-ink'"
                >
                    Brainstorming
                </button>
            </div>

            {{-- Panel · Aufgaben --}}
            <div role="tabpanel" x-show="tab === 'tasks'">
                {{-- Quick-add --}}
                <form wire:submit="addTask" class="mt-4">
                    <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2.5 shadow-map focus-within:border-ink-faint/60">
                        <input
                            type="text"
                            wire:model="newTitle"
                            placeholder="Aufgabe zum Projekt …"
                            autocomplete="off"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-faint focus:ring-0"
                        />
                        <button type="submit" class="grid h-8 w-8 flex-none place-items-center rounded-card bg-forest text-white transition hover:brightness-110 active:scale-95" aria-label="Hinzufügen">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    @error('newTitle') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror
                </form>

                {{-- Task list --}}
                @php
                    $activeTasks = $this->tasks->where('is_completed', false);
                    $completedTasks = $this->tasks->where('is_completed', true);
                @endphp
                <div class="mt-4 flex flex-col gap-2">
                    @forelse ($activeTasks as $task)
                        @include('livewire.partials.project-task-card', ['task' => $task])
                    @empty
                        @if ($completedTasks->isEmpty())
                            <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-12 text-center">
                                <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                    <path d="M8 16a2 2 0 0 1 2-2h9l3 4h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V16Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>
                                <p class="max-w-[26ch] text-xs leading-relaxed text-ink-faint">Noch keine offenen Aufgaben. Füge oben eine hinzu oder hol dir eine aus der Inbox.</p>
                            </div>
                        @endif
                    @endforelse

                    @if ($completedTasks->isNotEmpty())
                        <div class="mt-1 border-t border-line/50 pt-2">
                            <p class="mb-1.5 px-1 text-[10px] font-medium uppercase tracking-[0.12em] text-ink-faint">Erledigt</p>
                            @foreach ($completedTasks as $task)
                                @include('livewire.partials.project-task-card', ['task' => $task])
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Pull tasks in from the inbox --}}
                @if ($this->inboxTasks->isNotEmpty())
                    <div x-data="{ open: false }" class="mt-6 rounded-card border border-line bg-paper/60">
                        <button
                            type="button"
                            @click="open = !open"
                            class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm font-medium text-ink-soft transition hover:text-ink"
                            :aria-expanded="open"
                        >
                            <span>Aus der Inbox hinzufügen
                                <span class="tnum ml-1 rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $this->inboxTasks->count() }}</span>
                            </span>
                            <svg class="h-4 w-4 flex-none text-ink-faint transition" :class="open && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>

                        <div x-show="open" x-collapse style="display: none;" class="border-t border-line px-2 py-2">
                            <ul class="flex flex-col gap-1">
                                @foreach ($this->inboxTasks as $task)
                                    <li wire:key="inbox-pick-{{ $task->id }}">
                                        <button
                                            type="button"
                                            wire:click="assignToProject({{ $task->id }})"
                                            class="group/pick flex w-full items-center gap-2.5 rounded-card px-2.5 py-2 text-left transition hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
                                        >
                                            <span class="grid h-5 w-5 flex-none place-items-center rounded-card border border-line text-ink-faint transition group-hover/pick:border-forest group-hover/pick:text-forest">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                            </span>
                                            <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $task->title }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel · Brainstorming --}}
            <div role="tabpanel" x-show="tab === 'brainstorm'" style="display: none;" class="mt-4">
                @if (! $editingBrainstorm)
                {{-- Read view --}}
                <div wire:key="brainstorm-read">
                    @if ($hasNotes)
                        <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map">
                            <div class="flex items-center justify-end border-b border-line px-2 py-1.5">
                                <button type="button" wire:click="editBrainstorm" class="inline-flex items-center gap-1 rounded-card px-2 py-1 text-xs font-medium text-ink-faint transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                    Bearbeiten
                                </button>
                            </div>
                            <div class="prose-topo cursor-text px-4 py-4 sm:px-5" @click="if (! $event.target.closest('a')) $wire.editBrainstorm()">
                                {!! $this->brainstormHtml !!}
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="editBrainstorm" class="group/empty flex w-full flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line bg-paper/40 px-4 py-12 text-center transition hover:border-ink-faint/60 hover:bg-paper/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                            <svg class="h-9 w-9 text-line transition group-hover/empty:text-ink-faint" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M30 9.5 38.5 18 18 38.5l-9 1.5 1.5-9L30 9.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                <path d="m26.5 13 8.5 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span class="text-sm font-medium text-ink">Notiz beginnen</span>
                            <span class="max-w-[34ch] text-xs leading-relaxed text-ink-faint">Halte Ideen, Gedanken und offene Fragen zum Projekt fest. Markdown wird unterstützt.</span>
                        </button>
                    @endif
                </div>
                @else
                {{-- Edit view --}}
                <div wire:key="brainstorm-edit">
                    <div class="overflow-hidden rounded-card border border-line bg-surface shadow-map focus-within:border-ink-faint/60">
                        {{-- Formatting toolbar (buttons keep focus in the textarea) --}}
                        <div class="flex flex-wrap items-center gap-0.5 border-b border-line px-1.5 py-1.5">
                            <button type="button" @mousedown.prevent="prefixLines('## ')" title="Überschrift" aria-label="Überschrift" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <span class="text-[13px] font-bold">H</span>
                            </button>
                            <button type="button" @mousedown.prevent="wrap('**', '**')" title="Fett" aria-label="Fett" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <span class="text-[13px] font-bold">B</span>
                            </button>
                            <button type="button" @mousedown.prevent="wrap('*', '*')" title="Kursiv" aria-label="Kursiv" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <span class="font-serif text-[13px] italic">i</span>
                            </button>
                            <span class="mx-1 h-4 w-px bg-line" aria-hidden="true"></span>
                            <button type="button" @mousedown.prevent="prefixLines('- ')" title="Liste" aria-label="Liste" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="3" cy="4.5" r="1" fill="currentColor"/><circle cx="3" cy="11.5" r="1" fill="currentColor"/><path d="M6.5 4.5h7M6.5 11.5h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </button>
                            <button type="button" @mousedown.prevent="prefixLines('- [ ] ')" title="Aufgabe" aria-label="Aufgabe" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 8 1.8 1.8L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <button type="button" @mousedown.prevent="wrap('[', '](url)')" title="Link" aria-label="Link" class="grid h-7 w-7 place-items-center rounded-[0.4rem] text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 9.5 9.5 6.5M7 4.6l.9-.9a2.4 2.4 0 0 1 3.4 3.4l-.9.9M9 11.4l-.9.9a2.4 2.4 0 0 1-3.4-3.4l.9-.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>

                        <textarea
                            id="brainstorm-editor"
                            x-ref="ta"
                            wire:model.live.debounce.800ms="brainstorm"
                            @input="autosize()"
                            x-init="$nextTick(() => autosize())"
                            rows="10"
                            placeholder="Ideen, Gedanken, offene Fragen … einfach lostippen."
                            class="block w-full resize-none border-0 bg-transparent px-4 py-3.5 text-[15px] leading-relaxed text-ink placeholder:text-ink-faint focus:ring-0"
                        ></textarea>
                    </div>
                    @error('brainstorm') <p class="mt-1.5 px-1 text-xs text-signal">{{ $message }}</p> @enderror

                    <div class="mt-2 flex items-center justify-between gap-3 px-0.5">
                        <p class="text-[11px] leading-snug text-ink-faint">Markdown: **fett**, *kursiv*, # Titel, - Liste, [Text](url)</p>
                        <div class="flex flex-none items-center gap-3">
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-forest" x-show="saved" x-transition.opacity.duration.200ms style="display: none;">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Gespeichert
                            </span>
                            <button type="button" wire:click="stopEditingBrainstorm" class="rounded-card bg-forest px-4 py-1.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">
                                Fertig
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Shared edit sheet --}}
    @include('livewire.partials.edit-sheet')
</div>
