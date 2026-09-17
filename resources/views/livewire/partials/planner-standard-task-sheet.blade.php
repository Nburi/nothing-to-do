{{--
    "Standardaufgabe" quick-add sheet — opened from the two chips above the
    backlog (planner.blade.php). Deliberately click/tap driven, not a drag
    gesture: this always needs a short server round trip anyway (duration/
    subject/exam has to be picked before the Task can even be built), so
    there is no drag-and-drop shortcut being skipped here — a modal form is
    the plain, robust way to collect a few fields regardless of breakpoint,
    mirroring category-link-sheet.blade.php's shape (animate-rise, no leave
    transition). Driven directly by the component's own $standardTemplate
    property (not an Alpine store), same as this app's other Livewire-state
    sheets — no client-side "which template is this" state to keep in sync.
--}}
@if ($standardTemplate)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ \App\Services\PlannerStandardTasks::label($standardTemplate) }} einplanen">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeStandardTask"></div>
        <div class="animate-rise relative max-h-[88dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-map sm:rounded-card" @keydown.escape.window="$wire.closeStandardTask()">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-medium text-ink">{{ \App\Services\PlannerStandardTasks::label($standardTemplate) }}</h2>
                <button type="button" wire:click="closeStandardTask" class="grid h-8 w-8 flex-none place-items-center rounded-card text-ink-faint transition hover:bg-paper hover:text-ink" aria-label="Schließen">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm leading-relaxed text-ink-soft">
                {{ \App\Services\PlannerStandardTasks::CATALOG[$standardTemplate]['hint'] }}
            </p>

            <form wire:submit="saveStandardTask" class="space-y-4">
                <div>
                    <label for="standard-task-date" class="mb-1.5 block px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Tag</label>
                    <select id="standard-task-date" wire:model="standardDate" class="w-full rounded-card border-line bg-paper text-sm text-ink focus:border-overprint focus:ring-0">
                        @foreach ($this->standardTaskDayOptions as $option)
                            <option value="{{ $option['date'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('standardDate') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="standard-task-duration" class="mb-1.5 block px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Länge für diesen Tag (Minuten)</label>
                    <input
                        id="standard-task-duration"
                        type="number"
                        wire:model="standardDuration"
                        min="{{ \App\Services\PlannerStandardTasks::MIN_DURATION }}"
                        max="{{ \App\Services\PlannerStandardTasks::MAX_DURATION }}"
                        step="5"
                        class="w-full rounded-card border-line bg-paper text-sm tnum text-ink focus:border-overprint focus:ring-0"
                    />
                    @error('standardDuration') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>

                @if ($standardTemplate === 'study')
                    <div>
                        <label class="mb-1.5 block px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Was lernen?</label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (\App\Services\PlannerStandardTasks::STUDY_MODES as $modeKey => $modeLabel)
                                <button
                                    type="button"
                                    wire:click="setStandardStudyMode('{{ $modeKey }}')"
                                    class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $standardStudyMode === $modeKey ? 'bg-forest text-white' : 'bg-paper text-ink-soft hover:bg-line/60' }}"
                                >{{ $modeLabel }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if ($standardStudyMode === 'subject')
                        <div>
                            <label for="standard-task-subject" class="mb-1.5 block px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Fach</label>
                            <input
                                id="standard-task-subject"
                                type="text"
                                wire:model="standardStudySubject"
                                placeholder="z. B. Mathematik"
                                autocomplete="off"
                                class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                            />
                            @error('standardStudySubject') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                        </div>
                    @elseif ($standardStudyMode === 'exam')
                        @if ($this->standardStudyExamOptions->isNotEmpty())
                            <div>
                                <p class="mb-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Offene Prüfungen</p>
                                <div class="space-y-1">
                                    @foreach ($this->standardStudyExamOptions as $entry)
                                        <button
                                            type="button"
                                            wire:key="standard-exam-{{ $entry->id }}"
                                            wire:click="pickStandardStudyExam({{ $entry->id }})"
                                            class="flex w-full items-center justify-between gap-2 rounded-card border px-3 py-2 text-left text-sm transition {{ $standardStudyAgendaEntryId === $entry->id ? 'border-forest/40 bg-forest-soft text-ink' : 'border-line text-ink hover:bg-paper' }}"
                                        >
                                            <span class="min-w-0 flex-1 truncate">{{ $entry->subject }} · {{ $entry->title }}</span>
                                            <span class="flex-none text-xs text-ink-faint">{{ $entry->dateLabel() }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div>
                            <label for="standard-task-exam-subject" class="mb-1.5 block px-1 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">{{ $this->standardStudyExamOptions->isNotEmpty() ? 'Oder Fach/Titel eingeben' : 'Fach oder Prüfungstitel' }}</label>
                            <input
                                id="standard-task-exam-subject"
                                type="text"
                                wire:model="standardStudySubject"
                                placeholder="z. B. Französisch"
                                autocomplete="off"
                                class="w-full rounded-card border-line bg-paper text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:ring-0"
                            />
                            @error('standardStudySubject') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @endif

                <button type="submit" class="w-full rounded-card bg-forest px-3.5 py-2.5 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Einplanen</button>
            </form>
        </div>
    </div>
@endif
