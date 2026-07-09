<x-app-layout>
    @php
        $sections = [
            'auth' => 'Authentifizierung',
            'conventions' => 'Konventionen',
            'me' => 'Account',
            'tasks' => 'Tasks',
            'projects' => 'Projects',
            'schedule' => 'Zeitplan',
            'categories' => 'Kategorien',
            'templates' => 'Vorlagen',
            'shortcuts' => 'Apple Shortcuts',
            'errors' => 'Fehler & Limits',
        ];
    @endphp

    <div class="mx-auto max-w-4xl space-y-8 px-5 py-10 sm:px-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zu den Einstellungen" wire:navigate>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-xl font-medium text-ink">API-Dokumentation</h1>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <p class="text-sm leading-relaxed text-ink-soft">
                Eine token-authentifizierte JSON-API, die jede Aktion abdeckt, die auch in der App möglich ist —
                gedacht für <span class="font-medium text-ink">Apple Shortcuts</span> und andere Automatisierungen.
                Basis-URL: <code class="rounded bg-paper px-1.5 py-0.5 font-mono text-xs text-ink">{{ $apiBase }}</code>
            </p>

            <nav class="mt-5 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-line pt-4 text-sm">
                @foreach ($sections as $anchor => $label)
                    <a href="#{{ $anchor }}" class="text-overprint hover:underline">{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        {{-- Authentifizierung --}}
        <section id="auth" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Authentifizierung</h2>
            <p class="text-sm leading-relaxed text-ink-soft">
                Erstelle ein persönliches Zugriffstoken unter
                <a href="{{ route('settings') }}" class="text-overprint hover:underline" wire:navigate>Einstellungen → Shortcuts & API</a>.
                Das Token wird nur einmal angezeigt — sofort kopieren. Es trägt volle Rechte auf deinen Account,
                also wie ein Passwort behandeln. Jede Anfrage schickt es als <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Bearer</code>-Header:
            </p>
            <pre class="mt-3 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>curl {{ $apiBase }}/me \
  -H "Authorization: Bearer &lt;DEIN_TOKEN&gt;" \
  -H "Accept: application/json"</code></pre>
            <p class="mt-3 text-sm text-ink-soft">Ohne gültiges Token antwortet jeder Endpunkt mit <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">401</code>.</p>
        </section>

        {{-- Konventionen --}}
        <section id="conventions" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Konventionen</h2>
            <ul class="space-y-2 text-sm leading-relaxed text-ink-soft">
                <li>Alle Antworten sind JSON. Listen kommen als <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{"data": [...] }</code>, Einzelobjekte als <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{"data": {...} }</code>.</li>
                <li>Schreibende Endpunkte (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">POST</code>/<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">PATCH</code>) akzeptieren <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">application/json</code> im Body — setze <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Content-Type: application/json</code>.</li>
                <li>Daten sind <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">YYYY-MM-DD</code>, Uhrzeiten <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">HH:MM</code>, Zeitstempel ISO-8601.</li>
                <li><code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">PATCH</code>-Endpunkte sind Teil-Updates: nur mitgeschickte Felder werden geändert. Ein Feld weglassen ≠ es leeren — dafür explizit <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">null</code> senden, wo das Feld nullable ist (z. B. Deadline entfernen).</li>
                <li>Jede Ressource gehört exklusiv deinem Account — eine fremde/fehlende ID liefert <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">404</code>, nie Daten eines anderen Accounts.</li>
            </ul>
        </section>

        {{-- Account --}}
        <section id="me" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Account</h2>
            <p class="mb-4 text-sm text-ink-soft">Übersicht + die Einstellungen, die nicht Kategorien betreffen.</p>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-mono text-xs text-overprint">GET /me</p>
                    <p class="mt-1 text-ink-soft">Name, E-Mail, Pomodoro-Rhythmus, Zeitzone, lokale Uhrzeit, Board-Zähler (inbox/todos/tasks/today/projects).</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">PATCH /me</p>
                    <p class="mt-1 text-ink-soft">
                        Felder (alle optional): <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">task_reset_time</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">pomodoro_work</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">pomodoro_short_break</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">pomodoro_long_break</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">pomodoro_long_every</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">timezone_offset</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">timezone_auto_dst</code>.
                    </p>
                </div>
            </div>
        </section>

        {{-- Tasks --}}
        <section id="tasks" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Tasks</h2>
            <p class="mb-4 text-sm text-ink-soft">Inbox, To-Dos, Tasks und die eigenständigen Projekt-Tasks — jede Karte auf dem Board.</p>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-mono text-xs text-overprint">GET /tasks</p>
                    <p class="mt-1 text-ink-soft">
                        Query-Parameter: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">list</code> (inbox/todos/tasks/projects),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">project_id</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">today=1</code> (nur Today-Fokus),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">completed=1</code> (auch erledigte einschliessen).
                        Ohne <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">project_id</code> werden Projekt-Tasks ausgeschlossen (wie das Board).
                    </p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">GET /tasks/{id}</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /tasks</p>
                    <p class="mt-1 text-ink-soft">
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">title</code> (required),
                        optional <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">list</code> (Default <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">inbox</code>),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">project_id</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">deadline</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">due_date</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">is_important</code>.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>curl -X POST {{ $apiBase }}/tasks \
  -H "Authorization: Bearer &lt;TOKEN&gt;" -H "Content-Type: application/json" \
  -d '{"title": "Vokabeln lernen", "list": "todos", "due_date": "2026-07-11"}'</code></pre>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">PATCH /tasks/{id}</p>
                    <p class="mt-1 text-ink-soft">
                        Ein einziger Endpunkt für jede Karten-Aktion. Beliebige Teilmenge von:
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">title</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">is_completed</code> (abhaken),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">is_important</code> (markieren),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">is_today</code> (Today-Fokus, nur für To-Dos/Tasks ausserhalb eines Projekts),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">deadline</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">due_date</code> (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">null</code> zum Entfernen),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">list</code> (Spalte wechseln),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">project_id</code> (einem Projekt zuweisen; <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">null</code> gibt den Task zurück in die Inbox).
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>curl -X PATCH {{ $apiBase }}/tasks/42 \
  -H "Authorization: Bearer &lt;TOKEN&gt;" -H "Content-Type: application/json" \
  -d '{"is_completed": true}'</code></pre>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">DELETE /tasks/{id}</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /tasks/reorder</p>
                    <p class="mt-1 text-ink-soft">
                        Entspricht Drag &amp; Drop: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">list</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">today</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">ids</code> (vollständige, geordnete ID-Liste der Zielzone).
                    </p>
                </div>
            </div>
        </section>

        {{-- Projects --}}
        <section id="projects" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Projects</h2>
            <p class="mb-4 text-sm text-ink-soft">Die Projekte-Spalte und ihre Projektseiten.</p>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-mono text-xs text-overprint">GET /projects</p>
                    <p class="mt-1 text-ink-soft">Mit <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">done_count</code> und der geordneten <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">active_tasks</code>-Vorschau je Projekt.</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">GET /projects/{id}</p>
                    <p class="mt-1 text-ink-soft">Mit vollständiger <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">tasks</code>-Liste (aktiv + kürzlich erledigt).</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /projects</p>
                    <p class="mt-1 text-ink-soft"><code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">name</code> (required), optional <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">deadline</code>.</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">PATCH /projects/{id}</p>
                    <p class="mt-1 text-ink-soft">
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">name</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">deadline</code>,
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">external_url</code> (Jira/GitHub/Linear-Link),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">brainstorm</code> (Markdown-Notizen).
                    </p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">DELETE /projects/{id}</p>
                    <p class="mt-1 text-ink-soft">Aktive Tasks wandern zurück in die Inbox, wie in der App.</p>
                </div>
            </div>
        </section>

        {{-- Zeitplan --}}
        <section id="schedule" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Zeitplan</h2>
            <p class="mb-4 text-sm text-ink-soft">Termine, Kategorie-Blöcke und der Pomodoro-Fokus-Timer.</p>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-mono text-xs text-overprint">GET /schedule-events</p>
                    <p class="mt-1 text-ink-soft">
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">date</code> für einen Tag, oder
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">start</code>/<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">end</code> für eine Spanne.
                        Ohne Parameter: heute. Wiederkehrende Serien werden für das Fenster automatisch materialisiert.
                    </p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">GET /schedule-events/{id}</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /schedule-events</p>
                    <p class="mt-1 text-ink-soft">
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">kind</code> (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">appointment</code> oder <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">category</code>),
                        <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">date</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">start_time</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">end_time</code>.
                        Bei <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">appointment</code>: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">title</code> + <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">color</code>
                        (contour/overprint/forest/signal/ink). Bei <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">category</code>: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">category_id</code>.
                        Optional wiederkehrend: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">recurring: true</code> + <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">days: [1..7]</code> (ISO-Wochentage, 1=Montag).
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>curl -X POST {{ $apiBase }}/schedule-events \
  -H "Authorization: Bearer &lt;TOKEN&gt;" -H "Content-Type: application/json" \
  -d '{"kind": "appointment", "title": "Pause", "color": "contour",
       "date": "2026-07-09", "start_time": "14:00", "end_time": "14:15"}'</code></pre>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">PATCH /schedule-events/{id}</p>
                    <p class="mt-1 text-ink-soft">
                        Teil-Update — deckt auch Verschieben/Grösse ändern ab (einfach <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">start_time</code>/<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">end_time</code> setzen).
                        Ein wiederkehrendes Vorkommen auf ein anderes Datum verschieben löst es aus der Serie und hinterlässt einen Tombstone, damit es nicht neu entsteht.
                    </p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">DELETE /schedule-events/{id}</p>
                    <p class="mt-1 text-ink-soft">Einzeltermin: gelöscht. Serien-Vorkommen: nur dieses eine Vorkommen abgesagt.</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /schedule-events/{id}/start-focus</p>
                    <p class="mt-1 text-ink-soft">Startet den Pomodoro-Timer eines Kategorie-Blocks (nur wenn dessen Kategorie Pomodoro aktiviert hat).</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /schedule-events/{id}/stop-focus</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">GET /schedule-events/focus</p>
                    <p class="mt-1 text-ink-soft">
                        Der Fokus-Header von jetzt: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">focus_session</code> (der aktive/bald startende
                        Pomodoro-Block, oder <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">null</code>), <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">phase</code>
                        (Work/Pause + Restsekunden) und <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">suggestion</code> ("was jetzt tun").
                    </p>
                </div>
            </div>
        </section>

        {{-- Kategorien --}}
        <section id="categories" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Kategorien</h2>
            <p class="mb-4 text-sm text-ink-soft">Wiederverwendbare Zeitplan-Kategorien (Schule, Training, …).</p>

            <div class="space-y-4 text-sm">
                <div><p class="font-mono text-xs text-overprint">GET /event-categories</p></div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /event-categories</p>
                    <p class="mt-1 text-ink-soft"><code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">name</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">color</code>, optional <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">pomodoro_enabled</code>.</p>
                </div>
                <div>
                    <p class="font-mono text-xs text-overprint">PATCH /event-categories/{id}</p>
                    <p class="mt-1 text-ink-soft">Umbenennen/umfärben wirkt live auf alle vergangenen und zukünftigen Blöcke dieser Kategorie.</p>
                </div>
                <div><p class="font-mono text-xs text-overprint">DELETE /event-categories/{id}</p></div>
            </div>
        </section>

        {{-- Vorlagen --}}
        <section id="templates" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Vorlagen</h2>
            <p class="mb-4 text-sm text-ink-soft">Quick-Add-Vorlagen für den Zeitplan.</p>

            <div class="space-y-4 text-sm">
                <div><p class="font-mono text-xs text-overprint">GET /event-templates</p></div>
                <div>
                    <p class="font-mono text-xs text-overprint">POST /event-templates/{id}/apply</p>
                    <p class="mt-1 text-ink-soft"><code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">date</code> (required), optional <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">start_time</code> (sonst die Standardzeit der Vorlage). Legt einen konkreten Termin an.</p>
                </div>
                <div><p class="font-mono text-xs text-overprint">DELETE /event-templates/{id}</p></div>
            </div>
        </section>

        {{-- Apple Shortcuts --}}
        <section id="shortcuts" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-1 text-base font-medium text-ink">Apple Shortcuts</h2>
            <p class="mb-5 text-sm leading-relaxed text-ink-soft">
                Jeder Shortcut, der mit dieser API spricht, besteht im Kern aus einer
                <span class="font-medium text-ink">"Inhalt von URL abrufen"</span>-Aktion. So wird sie konfiguriert:
            </p>

            <div class="rounded-card border border-line bg-paper/60 p-4 text-sm">
                <ol class="list-decimal space-y-1.5 pl-5 text-ink-soft">
                    <li><span class="font-medium text-ink">URL:</span> <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{{ $apiBase }}/…</code></li>
                    <li><span class="font-medium text-ink">Methode:</span> GET/POST/PATCH/DELETE je nach Endpunkt</li>
                    <li><span class="font-medium text-ink">Kopfzeilen (Headers):</span> <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Authorization: Bearer &lt;Token&gt;</code>, <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Content-Type: application/json</code></li>
                    <li><span class="font-medium text-ink">Anfragetext (Request Body):</span> bei POST/PATCH → "JSON" wählen, Felder als Schlüssel/Wert-Paare</li>
                </ol>
                <p class="mt-3 text-xs text-ink-faint">Tipp: Token als Shortcuts-Textkonstante oder in "Alle Shortcuts" als wiederverwendbare Variable ablegen, statt es in jedem Shortcut neu einzutippen.</p>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <h3 class="mb-2 text-sm font-medium text-ink">① Schnell in die Inbox</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                        <li><span class="font-medium text-ink">Eingabe erfragen</span> (Text) — Prompt: "Was gibt's zu tun?"</li>
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — POST <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/tasks</code>, Body: <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{"title": Bereitgestellte Eingabe}</code></li>
                        <li><span class="font-medium text-ink">Ergebnis anzeigen</span> (optional) — "Hinzugefügt ✓"</li>
                    </ol>
                    <p class="mt-1.5 text-xs text-ink-faint">Als Home-Screen-Symbol oder Siri-Phrase ("Hey Siri, notiere …") die schnellste Erfassung unterwegs.</p>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-ink">② Was steht heute an?</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — GET <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/tasks?today=1</code></li>
                        <li><span class="font-medium text-ink">Wörterbuchwert abrufen</span> — Schlüssel <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">data</code></li>
                        <li><span class="font-medium text-ink">Für jeden Wert in Liste</span> → <span class="font-medium text-ink">Wörterbuchwert abrufen</span> (Schlüssel <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">title</code>) → Text sammeln</li>
                        <li><span class="font-medium text-ink">Ergebnis anzeigen</span> mit der gesammelten Liste</li>
                    </ol>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-ink">③ Einen Task per Zuruf abhaken</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                        <li><span class="font-medium text-ink">Eingabe erfragen</span> (Text) — welcher Task?</li>
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — GET <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/tasks</code></li>
                        <li><span class="font-medium text-ink">Wörterbuchwert abrufen</span> (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">data</code>) → <span class="font-medium text-ink">Elemente filtern</span>, wobei <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">title</code> die Eingabe enthält</li>
                        <li><span class="font-medium text-ink">Wörterbuchwert abrufen</span> (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">id</code>) vom ersten Treffer</li>
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — PATCH <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/tasks/&lt;id&gt;</code>, Body <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{"is_completed": true}</code></li>
                    </ol>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-ink">④ Fokus-Timer starten</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — GET <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/schedule-events/focus</code></li>
                        <li><span class="font-medium text-ink">Wörterbuchwert abrufen</span> (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">focus_session</code>) → (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">id</code>)</li>
                        <li><span class="font-medium text-ink">Hat einen Wert?</span> → wenn ja: <span class="font-medium text-ink">Inhalt von URL abrufen</span> — POST <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/schedule-events/&lt;id&gt;/start-focus</code></li>
                        <li>Sonst: <span class="font-medium text-ink">Warnung anzeigen</span> — "Gerade kein Fokus-Block."</li>
                    </ol>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-ink">⑤ Pause im Zeitplan eintragen</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                        <li><span class="font-medium text-ink">Aktuelles Datum</span> formatiert als <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">YYYY-MM-DD</code></li>
                        <li><span class="font-medium text-ink">Inhalt von URL abrufen</span> — POST <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">/schedule-events</code>,
                            Body <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{"kind":"appointment","title":"Pause","color":"contour","date":"…","start_time":"14:00","end_time":"14:15"}</code>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        {{-- Fehler & Limits --}}
        <section id="errors" class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Fehler & Limits</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs uppercase tracking-wide text-ink-faint">
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 font-medium">Bedeutung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line text-ink-soft">
                        <tr><td class="py-2 pr-4 font-mono text-xs">401</td><td class="py-2">Kein oder ungültiges Token.</td></tr>
                        <tr><td class="py-2 pr-4 font-mono text-xs">404</td><td class="py-2">Ressource existiert nicht oder gehört einem anderen Account.</td></tr>
                        <tr><td class="py-2 pr-4 font-mono text-xs">422</td><td class="py-2">Validierung fehlgeschlagen — Details unter <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">errors</code> im Body.</td></tr>
                        <tr><td class="py-2 pr-4 font-mono text-xs">429</td><td class="py-2">Zu viele Anfragen (Standardlimit: 60/Minute je Token).</td></tr>
                    </tbody>
                </table>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>{"message": "The end time field must be...", "errors": {"end_time": ["The end time field must be a date after start time."]}}</code></pre>
        </section>
    </div>
</x-app-layout>
