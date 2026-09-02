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
                immer für genau deinen Account, nie global. Ein Endpunkt, JSON-RPC 2.0 über HTTP (Streamable HTTP,
                Protokollversion <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">2025-06-18</code>):
            </p>
            <pre class="mt-3 overflow-x-auto rounded-card border border-line bg-paper p-4 text-xs text-ink"><code>{{ $mcpUrl }}</code></pre>
        </div>

        {{-- Auth --}}
        <section class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <h2 class="mb-3 text-base font-medium text-ink">Verbinden</h2>
            <p class="text-sm leading-relaxed text-ink-soft">
                Erstelle unter <a href="{{ route('settings') }}#developer" class="text-overprint hover:underline" wire:navigate>Einstellungen → Shortcuts, API & MCP</a>
                ein neues Token. Wähle dort bewusst, ob es auch schreiben und/oder löschen darf — ein reines
                Lese-Token reicht für "was steht an", zum Organisieren braucht es "Schreiben". In deinem
                MCP-Client (z. B. Claude Desktop, Claude Code) trägst du die URL oben plus das Token als
                <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">Authorization: Bearer &lt;TOKEN&gt;</code>-Header ein.
            </p>
            <p class="mt-3 text-sm text-ink-soft">
                Ohne gültiges Token antwortet der Endpunkt mit <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">401</code>.
                Der Server ist zustandslos — jeder Aufruf authentifiziert sich selbst, es gibt keine Sitzung, die
                offen bleiben könnte.
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
                    <span class="font-medium text-ink">Token-Rechte.</span> Ein Lese-Token sieht nie ein
                    schreibendes Werkzeug; ein Token ohne "Löschen erlauben" sieht <code class="rounded bg-paper px-1 py-0.5 font-mono text-xs">delete_task</code>
                    gar nicht — auch nicht als "gesperrt". Ein Aufruf eines nicht angebotenen Werkzeugs wird
                    exakt wie ein nicht existierendes behandelt (derselbe Fehler), damit ein Token nie verrät,
                    was mit mehr Rechten möglich wäre.
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
