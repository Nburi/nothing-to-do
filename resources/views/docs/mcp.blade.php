<x-app-layout>
    @php
        $abilityLabel = fn (?string $a) => match ($a) {
            'mcp:write' => 'Schreiben',
            'mcp:delete' => 'Löschen',
            default => 'Lesen',
        };
        $abilityTone = fn (?string $a) => match ($a) {
            'mcp:write' => 'bg-contour-soft text-contour',
            'mcp:delete' => 'bg-signal-soft text-signal',
            default => 'bg-line text-ink-soft',
        };
        $moduleLabels = \App\Services\AppModules::CATALOG;
    @endphp

    <div class="mx-auto max-w-4xl space-y-8 px-5 py-10 sm:px-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zu den Einstellungen" wire:navigate>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-xl font-medium text-ink">MCP-Dokumentation</h1>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <p class="text-sm leading-relaxed text-ink-soft">
                Ein <span class="font-medium text-ink">Model Context Protocol</span>-Server, damit ein KI-Assistent
                (z. B. Claude) deine Aufgaben, Agenda, Kategorien und Einstellungen lesen und organisieren kann —
                immer für genau deinen Account, nie global. JSON-RPC 2.0 über HTTP (Streamable HTTP).
            </p>
            <p class="mt-4 text-sm font-medium text-ink">Für claude.ai, Claude Desktop und Claude Code — OAuth:</p>
            <pre class="mt-2 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>{{ $mcpUrl }}</code></pre>
            <p class="mt-4 text-sm font-medium text-ink">Für eigene Skripte und Automatisierungen — Token:</p>
            <pre class="mt-2 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>{{ $mcpTokenUrl }}</code></pre>
        </div>

        {{-- Auth --}}
        <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Claude verbinden (OAuth)</h2>
            <p class="text-sm leading-relaxed text-ink-soft">
                claude.ai und Claude Desktop bieten beim Hinzufügen eines eigenen Connectors nur ein Feld für
                die Server-URL an — ein Token kannst du dort nicht eintragen. Deshalb gibt es diesen zweiten
                Endpunkt, der sich per OAuth anmeldet. Der Ablauf:
            </p>
            <ol class="mt-3 space-y-2 text-sm leading-relaxed text-ink-soft">
                <li><span class="font-medium text-ink">1.</span> In Claude: <span class="font-medium text-ink">Einstellungen → Connectors → Eigenen Connector hinzufügen</span>.</li>
                <li><span class="font-medium text-ink">2.</span> Als URL <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{{ $mcpUrl }}</code> eintragen. Client-ID und Secret bleiben leer — der Server registriert Claude selbst (Dynamic Client Registration).</li>
                <li><span class="font-medium text-ink">3.</span> Claude öffnet diese App im Browser. Einmal <span class="font-medium text-ink">„Verbinden"</span> bestätigen — fertig.</li>
            </ol>
            <p class="mt-3 text-sm leading-relaxed text-ink-soft">
                Was Claude dabei darf, stellst du unter
                <a href="{{ route('settings') }}#developer" class="text-overprint hover:underline" wire:navigate>Einstellungen → Shortcuts, API &amp; MCP</a>
                ein: Lesen immer, Schreiben standardmässig an, Löschen standardmässig aus. Änderungen gelten
                sofort für bestehende Verbindungen — du musst nichts neu verbinden. Dort trennst du eine
                Verbindung auch wieder, was sie wirklich beendet (auch das Refresh-Token wird ungültig).
            </p>
            <p class="mt-3 text-xs leading-relaxed text-ink-faint">
                Technisch: OAuth 2.1 mit PKCE (S256), Discovery über
                <code class="rounded bg-paper px-1 py-0.5 font-mono">/.well-known/oauth-protected-resource</code> und
                <code class="rounded bg-paper px-1 py-0.5 font-mono">/.well-known/oauth-authorization-server</code>,
                dynamische Client-Registrierung über <code class="rounded bg-paper px-1 py-0.5 font-mono">/oauth/register</code>.
                Access-Token eine Stunde, Refresh-Token sechs Monate.
            </p>
        </section>

        {{-- Token auth --}}
        <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Mit Token verbinden</h2>
            <p class="text-sm leading-relaxed text-ink-soft">
                Für alles, was einen eigenen Header setzen kann. Erstelle unter
                <a href="{{ route('settings') }}#developer" class="text-overprint hover:underline" wire:navigate>Einstellungen → Shortcuts, API &amp; MCP</a>
                ein Token und wähle dort bewusst, ob es auch schreiben und/oder löschen darf — ein reines
                Lese-Token reicht für „was steht an", zum Organisieren braucht es „Schreiben". Dann
                <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">{{ $mcpTokenUrl }}</code> mit
                <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Authorization: Bearer &lt;TOKEN&gt;</code> aufrufen.
            </p>
            <p class="mt-3 text-sm text-ink-soft">
                Beide Endpunkte bieten dieselben Werkzeuge und antworten ohne gültige Anmeldung mit
                <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">401</code>. Der Server ist zustandslos —
                jeder Aufruf authentifiziert sich selbst, es gibt keine Sitzung, die offen bleiben könnte.
            </p>
        </section>

        {{-- What adapts --}}
        <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Was die KI sieht, passt sich an</h2>
            <p class="text-sm leading-relaxed text-ink-soft">
                Welche Werkzeuge ein KI-Assistent bei <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">tools/list</code>
                angeboten bekommt, wird bei jedem Aufruf neu berechnet — nicht einmalig festgelegt. Zwei Dinge
                steuern das:
            </p>
            <ul class="mt-3 space-y-2 text-sm leading-relaxed text-ink-soft">
                <li>
                    <span class="font-medium text-ink">Modul-Sichtbarkeit.</span> Blendest du in den Einstellungen
                    ein Modul aus (z. B. Agenda), verschwinden die zugehörigen Werkzeuge beim nächsten Aufruf
                    einfach — nicht als Fehler, sondern spurlos, genau wie sie aus der Navigation verschwinden.
                </li>
                <li>
                    <span class="font-medium text-ink">Rechte der Verbindung.</span> Bei einem Token sind das
                    dessen eigene Rechte, bei OAuth die Einstellungen deines Accounts. Ohne „Schreiben" ist nie
                    ein schreibendes Werkzeug zu sehen; ohne „Löschen erlauben" fehlt
                    <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">delete_task</code> ganz — auch
                    nicht als „gesperrt". Ein Aufruf eines nicht angebotenen Werkzeugs wird exakt wie ein nicht
                    existierendes behandelt (derselbe Fehler), damit eine Verbindung nie verrät, was mit mehr
                    Rechten möglich wäre.
                </li>
            </ul>
        </section>

        {{-- Tool catalog, grouped by ability --}}
        @foreach (['mcp:read' => 'Lesen', 'mcp:write' => 'Schreiben', 'mcp:delete' => 'Löschen'] as $group => $groupLabel)
            <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
                <h2 class="mb-1 text-base font-medium text-ink">Werkzeuge — {{ $groupLabel }}</h2>
                <p class="mb-4 text-sm text-ink-soft">
                    @if ($group === 'mcp:read')
                        Immer verfügbar, sobald ein Token gültig ist.
                    @elseif ($group === 'mcp:write')
                        Nur mit einem Token, das "Schreiben erlauben" gesetzt hat.
                    @else
                        Nur mit einem Token, das zusätzlich "Löschen erlauben" gesetzt hat — standardmässig aus.
                    @endif
                </p>
                <div class="space-y-4 text-sm">
                    @foreach ($tools as $tool)
                        @continue(($tool['requiredAbility'] ?? 'mcp:read') !== $group)
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono text-xs text-overprint">{{ $tool['name'] }}</p>
                                @if ($tool['requiredModule'])
                                    <span class="rounded-full bg-line px-2 py-0.5 text-[11px] font-medium text-ink-soft">
                                        nur wenn „{{ $moduleLabels[$tool['requiredModule']]['label'] ?? $tool['requiredModule'] }}" sichtbar ist
                                    </span>
                                @endif
                                @if ($tool['annotations']['destructiveHint'] ?? false)
                                    <span class="rounded-full bg-signal-soft px-2 py-0.5 text-[11px] font-medium text-signal">unwiderruflich</span>
                                @endif
                            </div>
                            <p class="mt-1 text-ink-soft">{{ $tool['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Errors --}}
        <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Fehler</h2>
            <ul class="space-y-2 text-sm leading-relaxed text-ink-soft">
                <li>Ein unbekanntes oder nicht angebotenes Werkzeug → JSON-RPC-Protokollfehler (<code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">-32602</code>).</li>
                <li>Falsche/fehlende Argumente, eine fremde ID, eine falsche <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">confirm_title</code> bei <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">delete_task</code> → ein normales Tool-Ergebnis mit <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">isError: true</code>, kein Protokollfehler — die KI bekommt eine lesbare Fehlermeldung zurück und kann reagieren.</li>
            </ul>
        </section>
    </div>
</x-app-layout>
