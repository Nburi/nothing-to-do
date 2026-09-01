@php
    $stepCount = 6 + count($this->activeFeatureSteps);
    $conceptTitles = [
        'three_things' => 'Die 3 Dinge',
        'simple' => 'Simple',
        'eisenhower' => 'Eisenhower-Matrix',
        'kanban' => 'Kanban',
    ];
    $currentConceptRow = collect($this->listConceptRows)->firstWhere('key', $this->listConcept);
    $featureStepBaseIndex = 5;
    $lastStepIndex = $featureStepBaseIndex + count($this->activeFeatureSteps);
@endphp

<div
    x-data="{ quizAnswers: {}, skipped: false }"
    x-init="$store.onboarding.init({{ $stepCount }})"
    class="mx-auto max-w-xl px-4 pb-16 pt-6 sm:px-6"
>
    {{-- Skipping shows this instead of an immediate redirect — the user
         leaves on their own terms rather than being yanked away mid-click.
         skip() already stamped onboarding_completed_at by the time this is
         visible (wire:click and this @click fire independently on the same
         button — see CLAUDE.md's dual-fire convention). --}}
    <template x-if="skipped">
        <div class="space-y-5 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5l10 5-10 5V5Z"/></svg>
            </div>
            <h1 class="text-xl font-medium tracking-tight text-ink">Übersprungen</h1>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                Du findest diese Einführung jederzeit wieder unter Einstellungen → Tutorial.
            </p>
            <a
                href="{{ route(auth()->user()->defaultLandingRouteName()) }}"
                wire:navigate
                class="inline-block rounded-card bg-forest px-5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
            >Weiter zur App</a>
        </div>
    </template>

    <div x-show="!skipped">

        <div class="mb-10 flex items-center gap-4">
            <div class="flex-1">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-line">
                    <div
                        class="h-full rounded-full bg-forest transition-all duration-300 ease-out"
                        :style="`width: ${(($store.onboarding.step + 1) / $store.onboarding.total) * 100}%`"
                    ></div>
                </div>
                <p class="mt-1.5 text-xs text-ink-faint">
                    Schritt <span x-text="$store.onboarding.step + 1"></span> von <span x-text="$store.onboarding.total"></span>
                </p>
            </div>
            <button
                type="button"
                wire:click="skip"
                @click="skipped = true"
                x-show="$store.onboarding.step < $store.onboarding.total - 1"
                class="flex-none text-sm text-ink-faint transition hover:text-ink-soft"
            >Überspringen</button>
        </div>

        <div class="min-h-[420px]">

            {{-- 0 — Willkommen --}}
            <div x-cloak x-show="$store.onboarding.step === 0" class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <x-logo class="h-7 w-7 text-forest" />
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">Willkommen bei nothing-to-do</h1>
                <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                    Ein persönliches Produktivitätssystem — gebaut, damit nichts vergessen geht, ohne dass dich
                    eine riesige Liste erschlägt. Diese kurze Einführung stellt dir zuerst eine Frage, um sich
                    auf dich einzustellen, und zeigt dir danach, wie die App tickt. Du kannst jederzeit
                    überspringen und sie später in den Einstellungen erneut ansehen.
                </p>
            </div>

            {{-- 1 — Frage (multiple choice; resolves a suggested Listen-Konzept +
                 Zusatzbereiche, see App\Services\OnboardingQuiz) --}}
            <div x-cloak x-show="$store.onboarding.step === 1" class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M7.5 7.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7v.5"/>
                        <circle cx="10" cy="14.5" r="0.75" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">Warum suchst du gerade eine To-Do-Liste?</h1>
                <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                    Wähl alles aus, was zutrifft. Wir schlagen dir danach eine passende Ansicht und ein paar
                    Bereiche vor — du kannst beides im nächsten Schritt sofort wieder ändern.
                </p>

                <div class="mx-auto max-w-sm space-y-2 text-left">
                    @foreach ($this->quizAnswers as $answer)
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-card border p-3 text-sm transition"
                            :class="quizAnswers['{{ $answer['key'] }}'] ? 'border-forest bg-forest-soft/40 text-ink' : 'border-line bg-surface text-ink-soft hover:border-ink-faint/60'"
                        >
                            <input
                                type="checkbox"
                                x-model="quizAnswers['{{ $answer['key'] }}']"
                                class="mt-0.5 h-4 w-4 flex-none rounded border-line text-forest focus:ring-forest"
                            >
                            <span class="leading-snug">{{ $answer['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 2 — Konzept: the resolved suggestion, live-switchable to any of
                 the four (real, immediate-save picks — see setListConcept()) --}}
            <div x-cloak x-show="$store.onboarding.step === 2" class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 10s3-5.5 8-5.5 8 5.5 8 5.5-3 5.5-8 5.5-8-5.5-8-5.5Z"/>
                        <circle cx="10" cy="10" r="2"/>
                    </svg>
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">Deine Ansicht</h1>
                <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                    Basierend auf deiner Antwort schlagen wir dir das hier vor. Wechsle jederzeit — alle vier
                    zeigen genau dieselben Aufgaben, nur anders sortiert.
                </p>

                <div class="mx-auto max-w-sm space-y-4">
                    <div class="flex flex-wrap justify-center gap-1.5 rounded-[0.6rem] bg-surface p-1">
                        @foreach ($this->listConceptRows as $row)
                            <button
                                type="button"
                                wire:click="setListConcept('{{ $row['key'] }}')"
                                wire:key="onboarding-concept-tab-{{ $row['key'] }}"
                                class="flex-1 rounded-[0.45rem] px-2 py-1.5 text-xs font-medium transition"
                                @class([
                                    'bg-forest text-white shadow-sm' => $row['current'],
                                    'text-ink-soft hover:text-ink' => ! $row['current'],
                                ])
                            >{{ $row['label'] }}</button>
                        @endforeach
                    </div>

                    <p class="text-sm leading-relaxed text-ink-soft">{{ $currentConceptRow['description'] }}</p>

                    @include('livewire.partials.list-concept-preview', ['conceptKey' => $this->listConcept, 'previewTasks' => $this->listConceptPreviewTasks])
                </div>
            </div>

            {{-- 3 — Feature-Galerie: every Zusatzbereich, pre-set from the
                 quiz, freely re-toggled here (real toggles, shared with
                 Settings via ManagesModuleSettings). The Startseite picker
                 rides along, exactly as it already did before this rework —
                 it belongs with module visibility either way. --}}
            <div x-cloak x-show="$store.onboarding.step === 3" class="space-y-6">
                <div class="space-y-5 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                        <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/></svg>
                    </div>
                    <h1 class="text-xl font-medium tracking-tight text-ink">Welche Bereiche brauchst du?</h1>
                    <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">
                        Wir haben dir ein paar Bereiche vorausgewählt, basierend auf deiner Antwort. Schalte
                        aus, was du nicht brauchst — alles lässt sich jederzeit in den Einstellungen wieder
                        ändern.
                    </p>
                </div>

                <div class="space-y-2">
                    @foreach ($this->moduleRows as $row)
                        <div
                            wire:key="onboarding-module-row-{{ $row['key'] }}"
                            class="flex items-center gap-3 rounded-card border border-line bg-surface p-3.5 transition-opacity duration-300 {{ $row['hidden'] ? 'opacity-45' : 'opacity-100' }}"
                        >
                            <div class="min-w-0 flex-1">
                                <p @class(['text-sm font-medium', 'text-ink' => ! $row['hidden'], 'text-ink-faint' => $row['hidden']])>{{ $row['label'] }}</p>
                                <p class="mt-0.5 text-xs text-ink-soft leading-relaxed">{{ $row['description'] }}</p>
                            </div>
                            <button
                                type="button"
                                wire:click="toggleModule('{{ $row['key'] }}')"
                                @class([
                                    'relative h-6 w-10 flex-none rounded-full transition',
                                    'bg-forest' => ! $row['hidden'],
                                    'bg-line' => $row['hidden'],
                                ])
                                aria-label="{{ $row['label'] }} {{ $row['hidden'] ? 'einblenden' : 'ausblenden' }}"
                            >
                                <span @class([
                                    'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition',
                                    'left-[1.125rem]' => ! $row['hidden'],
                                    'left-0.5' => $row['hidden'],
                                ])></span>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-card border border-line bg-surface p-4">
                    <p class="mb-3 text-sm font-medium text-ink">Startseite</p>
                    <div class="flex flex-wrap gap-2 rounded-[0.6rem] bg-paper p-1">
                        @foreach ($this->landingPageOptions as $option)
                            <button
                                type="button"
                                wire:click="setDefaultPage('{{ $option['key'] }}')"
                                @class([
                                    'rounded-[0.45rem] px-3.5 py-1.5 text-sm transition',
                                    'bg-forest text-white shadow-sm' => $defaultPage === $option['key'],
                                    'text-ink-soft hover:text-ink' => $defaultPage !== $option['key'],
                                ])
                            >{{ $option['label'] }}</button>
                        @endforeach
                    </div>
                </div>

                <p class="text-center text-xs text-ink-faint">
                    Im Header lassen sich zudem einzelne Kurzinfos (z. B. deine Serie) ein-/ausblenden und
                    anordnen — unter Einstellungen → Header-Badges.
                </p>
            </div>

            {{-- 4 — Kernkonzept-Vertiefung: one step, content depends on the
                 currently chosen Listen-Konzept. --}}
            <div x-cloak x-show="$store.onboarding.step === 4" class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="12" width="14" height="4" rx="1"/><rect x="5" y="7" width="10" height="4" rx="1"/><rect x="7" y="2" width="6" height="4" rx="1"/></svg>
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">{{ $conceptTitles[$this->listConcept] ?? $currentConceptRow['label'] }}</h1>
                <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                    @if ($this->listConcept === 'three_things')
                        <p>Bleibt eine Aufgabe klein und in einem Rutsch erledigt → <span class="font-medium text-ink">To-Do</span>. Braucht sie mehr Zeit oder Konzentration, bleibt aber ein einzelner Schritt → <span class="font-medium text-ink">Task</span>. Hat sie mehrere Teilschritte und ist nicht dringend → <span class="font-medium text-ink">Project</span> mit eigener Seite.</p>
                    @elseif ($this->listConcept === 'kanban')
                        <p>Jede Karte hat einen Status — <span class="font-medium text-ink">Offen</span>, <span class="font-medium text-ink">In Arbeit</span> oder <span class="font-medium text-ink">Erledigt</span>. Die Grösse einer Aufgabe (To-Do/Task/Project) bleibt dabei bestehen — nur der Status ändert sich, wenn du eine Karte in die nächste Spalte ziehst.</p>
                    @elseif ($this->listConcept === 'eisenhower')
                        <p>„Wichtig" setzt du per Tipp auf den Titel, eine <span class="font-medium text-ink">Frist</span> per Tipp aufs Datum. Beides zusammen landet im Feld „Sofort", keins von beidem im Feld „Später".</p>
                    @elseif ($this->listConcept === 'simple')
                        <p>Alles liegt untereinander, in der Reihenfolge, in die du es ziehst. Oben = als Nächstes dran.</p>
                    @endif
                </div>

                <p class="mx-auto max-w-sm text-xs leading-relaxed text-ink-faint">
                    Das ist nicht die einzige Ansicht.
                    <a href="{{ route('settings').'?highlight='.urlencode('#list-concept') }}" wire:navigate class="font-medium text-forest underline decoration-forest/40 underline-offset-2 hover:decoration-forest">Probier andere Listen-Konzepte in den Einstellungen aus →</a>
                </p>
            </div>

            {{-- 5.. — one step per currently active Zusatzbereich, in
                 App\Services\AppModules::CATALOG's fixed order. --}}
            @foreach ($this->activeFeatureSteps as $i => $moduleKey)
                @php $meta = \App\Services\AppModules::CATALOG[$moduleKey]; @endphp
                <div
                    x-cloak
                    x-show="$store.onboarding.step === {{ $featureStepBaseIndex + $i }}"
                    wire:key="onboarding-feature-step-{{ $moduleKey }}"
                    class="space-y-5 text-center"
                >
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                        @switch ($moduleKey)
                            @case ('prepare')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 6h9m-9 4h12m-12 4h6"/></svg>
                                @break
                            @case ('schedule')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                                @break
                            @case ('weekplan')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                @break
                            @case ('agenda')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h6l2 2v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M12 3v2h2"/><path d="M7.5 10h5M7.5 13h3.5"/></svg>
                                @break
                            @case ('crafts')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5a5 5 0 0 0-3 9v1.5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V11.5a5 5 0 0 0-3-9Z"/><path d="M8 17h4"/><path d="M8.5 14.5h3"/></svg>
                                @break
                            @case ('emergency')
                                <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 18 17H2L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="1" fill="currentColor"/></svg>
                                @break
                            @case ('progress')
                                <x-flame-icon class="h-7 w-7 text-forest" />
                                @break
                        @endswitch
                    </div>
                    <h1 class="text-xl font-medium tracking-tight text-ink">{{ $meta['label'] }}</h1>
                    <p class="mx-auto max-w-sm text-sm leading-relaxed text-ink-soft">{{ $meta['description'] }}</p>
                    <p class="mx-auto max-w-sm text-xs leading-relaxed text-ink-faint">
                        Du kannst diesen Bereich jederzeit unter Einstellungen → Module wieder aus-/einblenden.
                    </p>
                </div>
            @endforeach

            {{-- last — Abschluss --}}
            <div x-cloak x-show="$store.onboarding.step === {{ $lastStepIndex }}" class="space-y-5 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-forest-soft">
                    <svg class="h-7 w-7 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10.5l4 4 8-9"/></svg>
                </div>
                <h1 class="text-xl font-medium tracking-tight text-ink">Bereit.</h1>
                <div class="mx-auto max-w-sm space-y-3 text-left text-sm leading-relaxed text-ink-soft">
                    <p>
                        Du nutzt jetzt <span class="font-medium text-ink">{{ $conceptTitles[$this->listConcept] ?? $currentConceptRow['label'] }}</span>
                        @if (count($this->activeFeatureSteps) > 0)
                            mit <span class="font-medium text-ink">{{ count($this->activeFeatureSteps) }}</span>
                            {{ count($this->activeFeatureSteps) === 1 ? 'zusätzlichen Bereich' : 'zusätzlichen Bereichen' }}.
                        @else
                            — ganz ohne Zusatzbereiche.
                        @endif
                    </p>
                    <p>
                        In den Einstellungen findest du ausserdem: den Pomodoro-Rhythmus, Push-Benachrichtigungen,
                        deine Zeitzone, Zeitplan-Kategorien und — für Fortgeschrittene — eine token-basierte API
                        für Apple Shortcuts.
                    </p>
                    <p>Dieses Tutorial findest du jederzeit wieder unter Einstellungen → Tutorial.</p>
                </div>
            </div>

        </div>

        <div class="mt-10 flex items-center justify-between gap-3">
            <button
                type="button"
                @click="$store.onboarding.back()"
                x-show="$store.onboarding.step > 0"
                x-cloak
                class="rounded-card border border-line bg-surface px-4 py-2 text-sm text-ink-soft transition hover:border-ink-faint/60 hover:text-ink"
            >Zurück</button>

            <button
                type="button"
                x-show="$store.onboarding.step < $store.onboarding.total - 1"
                x-cloak
                :disabled="$store.onboarding.step === 1 && ! Object.values(quizAnswers).some(Boolean)"
                @click="
                    $store.onboarding.step === 1
                        ? $wire.applyQuizAnswers(Object.keys(quizAnswers).filter(k => quizAnswers[k])).then(() => $store.onboarding.next())
                        : $store.onboarding.next()
                "
                class="ml-auto rounded-card bg-forest px-5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
            >Weiter</button>

            <button
                type="button"
                wire:click="finish"
                x-show="$store.onboarding.step === $store.onboarding.total - 1"
                x-cloak
                class="ml-auto rounded-card bg-forest px-5 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]"
            >Los geht's</button>
        </div>
    </div>
</div>
