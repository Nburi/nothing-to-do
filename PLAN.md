# PLAN — Task-Gruppen

> Arbeitsplan für das Feature „Task-Gruppierungen“ (Refactor; ein früherer Versuch wurde verworfen).
> Entstanden im `idea-to-app`-Workflow. Nach dem Merge kann diese Datei gelöscht werden.

## Ausgangsproblem

Alles Mehrstufige landet heute als **Projekt** — echte Projekte, kleine mehrstufige Aufgaben und
Projektideen. Die Projekte-Spalte wird dadurch unübersichtlich. Die Aufteilung soll sein:

| Ebene | Ort | Status |
|---|---|---|
| Projektideen | Bastelideen (`/app/crafts`) | ✅ gebaut |
| Mehrstufige Aufgabe | **Task-Gruppe** | ← dieser Plan |
| Grösseres Vorhaben (Vortrag) | Projekt (soll später ausgebaut werden) | vorhanden |
| Lebensprojekt (mehrjährig) | eigener Ort | später |

## Anforderungen

**Datenmodell**
- `TaskGroup` (Besitzer, Name, Notizen, `sort_order`); `tasks.group_id` nullable — orthogonal zu `list`.
- Eine Aufgabe ist entweder in einem Projekt **oder** in einer Gruppe, nie in beidem.

**Erstellen und befüllen**
- Über das N-Menü (QuickCapture) als eigenes Ziel „Gruppe“ — Gruppe wählen oder neu anlegen, plus Liste
  innerhalb der Gruppe. Damit lassen sich Aufgaben auch direkt in eine bestehende Gruppe erfassen.
- Desktop: eine Karte auf eine andere ziehen und ~350 ms halten; eine Karte auf eine Gruppen-Box ziehen
  behält ihre Liste (Inbox-Aufgabe → Gruppen-Inbox).
- Mobile: Feld „Gruppe“ im Bearbeitungs-Sheet, plus Long-Press auf eine Karte → „Projekt oder Gruppe“.
- Im Gruppen-Dashboard: „Aus der Inbox hinzufügen“-Picker wie auf der Projektseite.

**Gruppen-Dashboard** (`/app/groups/{group}`)
- Aufbau wie das Maindashboard: Kanban auf Desktop, Bottom-Navigation auf Mobile.
- Vier Listen: Inbox, To-Dos, Tasks, **Notizen** (Markdown, an der Stelle der Projekte-Spalte).
- Aufgaben haben Deadlines, sind von Hand sortierbar und als wichtig markierbar — **die
  Wichtig-Markierung beeinflusst die Reihenfolge nicht** (Reihenfolge: Deadline, dann manuell).
- Kein „Heute“-Bereich in den Gruppen-Spalten — Tagesfokus bleibt Sache des Maindashboards.

**Maindashboard**
- Pro Gruppe eine abgetrennte Box in der jeweiligen Spalte mit Name (klickbar), Fortschrittsbalken und
  den 2 nächsten Einträgen dieser Liste.
- Als wichtig markierte Gruppen-Aufgaben erscheinen als normale Karte in der Spalte (und deshalb
  nicht zusätzlich in der Box-Vorschau).
- Die Gruppen-Inbox erscheint nicht als eigene Box; eine Gruppe ohne Board-Aufgaben zeigt stattdessen
  eine kompakte Box in der Tasks-Spalte, damit sie nicht unsichtbar wird.

**UX**
- Leerzustände in jeder Spalte, Ladezustände über Livewire, Inline-Validierung beim Namen.
- Kein `confirm()` — „Gruppe auflösen“ nutzt das armed Double-Click-Muster.
- Auflösen/Löschen gibt Aufgaben frei (`group_id = null`), löscht sie nie.

## Produkt

Visuell festgehalten im Mockup (`gruppen-mockup.html`, Scratchpad): Gruppen-Box im Board, Drag-Geste
mit „Gruppieren“-Ring, Gruppen-Dashboard Desktop und Mobile.

Gestalterische Entscheidungen:
- Die Gruppen-Box führt **keine neue Farbe** ein — 3 px linke Kante in `ink-faint/55`, Fläche
  `surface/55`. Grün (Heute) und Rot (Notfall) bleiben reserviert; eine Gruppe ist Struktur, kein Alarm.
- Fortschrittsbalken identisch zur Projektkarte (`bg-line` Spur, `bg-forest` Füllung) — gleiche
  Bedeutung, gleiche Optik.
- Der Name ist der einzige Klickbereich, der wegführt; die Karten in der Box bleiben normale Karten
  mit allen Schnellaktionen.

## Umsetzung

**Stack:** unverändert — Laravel 13 / Livewire 4 / Alpine / Tailwind v3 / SortableJS. Keine neue
Abhängigkeit, kein neuer Skill nötig.

**Reihenfolge (ein Commit pro Schritt, Branch `feature/task-groups`):**

1. **Migration + Modell** — `task_groups` (`user_id`, `name`, `notes`, `sort_order`), `tasks.group_id`
   (`nullOnDelete`). `TaskGroup` mit `hasMany Task`, Scopes `forUser`/`ordered`, Fortschritts-Helfer.
   `Task`: `group()`, Scopes `inGroup`/`ungrouped`, `groupOrdered()` (wie `boardOrdered`, aber ohne
   `is_important`). `User::taskGroups()`.
2. **Gruppen-Dashboard** — `App\Livewire\GroupPage` (`/app/groups/{group}`, `route('group.show')`),
   nutzt `ManagesTasks` (Edit-Sheet, Schnellaktionen kostenlos). Vier Spalten, Bottom-Nav auf Mobile,
   Notizen-Markdown analog Projekt-Brainstorming. Umbenennen, Auflösen, Aufgabe aus Gruppe lösen.
3. **Maindashboard-Integration** — `TaskBoard`: Board-Queries schliessen gruppierte Aufgaben aus,
   ausser sie sind wichtig; neue Computed `groupsFor(list)`; `partials/task-group-box.blade.php` in
   Desktop-Spalte und Mobile-Liste.
4. **Erstellen per Drag** — `boardSortable` bekommt `onMove`-Verharren-Erkennung (~350 ms) mit
   `group-arm`-Klasse; `TaskBoard::groupTasks()`; Inline-Namensfeld auf der frischen Box; Drop auf eine
   bestehende Gruppen-Box → `assignTaskToGroup()`.
5. **QuickCapture + Mobile-Picker** — neues Ziel `group`; Picker-Sheet um Gruppen erweitert.
6. **Tests + Dokumentation** — PHPUnit für Modell, Sichtbarkeit/Scoping, Dashboard-Aktionen und
   Board-Integration; `CLAUDE.md` §7 ergänzen, `CHANGELOG.md` nachführen.

**Risiken / Achtungspunkte**
- Der Drag-Konflikt ist der heikle Teil: `onMove` darf normales Einsortieren nicht stören, und ein
  Gruppieren-Drop darf nicht zusätzlich `reorder()` auslösen.
- `wire:key` an allem, was Alpine-Zustand über einen Livewire-Morph hält (CLAUDE.md §10).
- API/Shortcuts kennen Gruppen zunächst nicht — bewusst ausgeklammert, in `TODO.md` notiert.
