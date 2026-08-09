# PLAN.md — Schnellerfassung (Quick Capture)

> Ersetzt den vorherigen Plan (Bastelideen). Der steht weiterhin in der Git-Historie, Commit `09a562b`.

## Ausgangslage

Das Dashboard war überladen — im laufenden Dev-Server bei 1440 × 900 gemessen:

- **5** Nav-Pills im Header (Vorbereiten, Zeitplan, Agenda, Bastelideen, Notfall), alle gleich gewichtet
- **3** Eingabefelder auf einer Seite: Aufgabe (52 px), Bastelidee (50 px), Projekt (in der Projekte-Spalte)
- **309 px** bis zur ersten Aufgabenkarte — 34 % der Bildschirmhöhe
- Mobil: rund 200 px von 812, und die *unwichtigere* Aktion (Bastelidee) hatte den *breiteren* Button

Kernbefund: nicht die Anzahl war das Problem, sondern die **gleiche Gewichtung**. Aufgaben-Leiste und
Bastelideen-Leiste hatten denselben Rahmen, denselben Schatten und denselben grünen Button an derselben Stelle.

Vier Optionen wurden als interaktives Mockup mit Jetzt/Nachher-Umschalter vorgelegt. **User hat sich für
Option 4 — Schnellerfassung — entschieden**, im Wissen um die genannten Nachteile (verstecktes Muster, zweites
Muster auf Mobile, deutlich mehr eigenes JS). Zusätzlich: **die Bastelideen-Seite bekommt ein eigenes
Eingabefeld** (sie hatte bisher gar keines — Erfassung ging ausschliesslich über das Board).

## Produkt

Auf dem Dashboard steht **kein Eingabefeld** mehr. Erfasst wird über ein zentriertes Panel, das von überall in
der App erreichbar ist.

**Öffnen:** Taste `N` · `+`-Button im Header (Desktop) · schwebender Knopf über der Bottom-Nav (Mobile)
**Schliessen:** `Esc` · Klick ausserhalb

**Im Panel:** ein Titelfeld, darunter eine Ziel-Chip-Reihe — **Inbox · To-Do · Task · Projekt · Bastelidee**,
per Zifferntaste `1`–`5` erreichbar. `Enter` speichert.

**Zusatzfelder** klappen wie bei den bisherigen Leisten progressiv auf, damit nichts verloren geht:

| Ziel | Zusatzfelder |
|---|---|
| Inbox / To-Do / Task | Deadline, Wunschtermin |
| Projekt | Deadline |
| Bastelidee | Wo anfangen |

### Bewusste Entscheidungen

- **Nach dem Speichern bleibt das Panel offen** und leert nur das Titelfeld, mit einer kurzen Bestätigungszeile
  („Postenbeschreibung studieren → Inbox"). Grund: mehrere Sachen hintereinander abladen ist der Hauptzweck,
  und die alten Leisten blieben nach dem Hinzufügen ebenfalls stehen. `Esc` beendet.
- **Standardziel ist immer Inbox.** Genau dafür ist die Inbox da — reinwerfen, ohne zu entscheiden. Die
  Zifferntasten sind der schnelle Weg raus, falls es doch sofort einsortiert werden soll.
- **Das Panel liegt im Layout, nicht im Board.** „Von überall erfassbar" war der ausschlaggebende Vorteil von
  Option 4; als Teil von `TaskBoard` ginge das nicht.
- **Der Header-Diät ist Teil dieser Option** (so im Mockup gezeigt): Notfall erscheint nur noch, wenn der Modus
  aktiv ist, Bastelideen zieht ins Avatar-Menü. Bleiben 3 Pills + `+`.
- **Kein `confirm()`** und keine neuen Abhängigkeiten — das Panel ist handgeschriebenes Alpine, wie alle anderen
  Gesten-Komponenten der App auch.

## Umsetzung

**Kein bestehender Skill einschlägig.** Geprüft: `emil-design-eng` und `impeccable` passen thematisch (UI-Politur,
Komponenten-Design), aber `CLAUDE.md` legt für dieses Projekt bereits jede relevante Konvention fest —
Topografie-Tokens, das armed-Doppelklick-Muster, die Alpine/Livewire-Fallen. Ein generischer Design-Skill würde
eher dagegen arbeiten. Umsetzung folgt den Projektkonventionen.

### Architektur

- **`App\Livewire\QuickCapture`** (klassenbasiert, ASCII-Dateiname) — im Layout innerhalb `@auth` eingebunden,
  also auf jeder Seite vorhanden. Felder: `title`, `target`, `deadline`, `dueDate`, `whereToBegin`.
  `save()` verzweigt nach `target` in `tasks()` / `projects()` / `craftIdeas()` — alles über die
  Owner-Relation, nie über eine rohe Id.
- **Auffrischen des Boards:** `QuickCapture` ist eine eigene Komponente, ein Speichern rendert `TaskBoard` also
  nicht automatisch neu. `save()` dispatcht `captured`; `TaskBoard`, `CraftIdeas` und `ProjectPage` hören per
  `#[On('captured')]` darauf und rendern neu.
- **Alpine:** ein `quickCapture`-Store in `resources/js/app.js` für `open`/`close`, plus ein
  `@keydown.window`-Handler für `N`, der abbricht, wenn der Fokus in einem `input`/`textarea`/
  `contenteditable` steht. Fokusfalle und Fokus-Rückgabe an das auslösende Element beim Schliessen.
  `prefers-reduced-motion` wird respektiert.

### Reihenfolge (Branch `feature/quick-capture`)

1. `QuickCapture`-Komponente + Panel-Markup + Alpine-Öffnen/Schliessen/Tastatur + Header-`+` + Mobile-FAB.
2. Die drei Eingabefelder aus `task-board.blade.php` und die zugehörigen Properties/Actions aus
   `TaskBoard.php` entfernen; `captured`-Listener verdrahten.
3. Header-Diät: Notfall nur bei aktivem Modus, Bastelideen ins Avatar-Menü.
4. Eigenes Erfassungsfeld auf `/app/crafts`.
5. Tests: `CraftIdeasTest`s Dashboard-Tests wandern auf `QuickCapture`; neue Tests für alle fünf Ziele,
   Ownership, Validierung. Volle Suite grün, danach Browser-QA (Desktop + Mobile, hell + dunkel).

### Risiken, die im Auge behalten werden

- Das Panel ist verstecktes UI. Gegenmittel: sichtbarer `+`-Button im Header und FAB auf Mobile — die Taste ist
  der Beschleuniger, nicht der einzige Weg.
- Der `x-data`-Morph-Trap aus `CLAUDE.md` §10: alles, was serverseitig gerenderte Werte einschliesst, braucht
  einen passenden `wire:key`.
