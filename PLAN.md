# PLAN — Fällige Termine im Zeitplan

## Ausgangslage (bestätigt im Code)
- `Task.deadline` = **Deadline · hart**, `Task.due_date` = **Wunschtermin · weich** (beide `date`, keine Uhrzeit).
- Prüfungen/Hausaufgaben sind `AgendaEntry` (`type` = `exam`|`homework`, Feld `date`), inkl. geteilter Klassen-Einträge.
- `App\Livewire\Schedule` (`/app/schedule`) ist ein zeitskaliertes Raster 06:00–23:00 (Woche auf Desktop, ein Tag
  auf Mobile). Diese Einträge haben keine Uhrzeit — sie brauchen eine eigene "Ganztags"-Zone oberhalb des Rasters,
  nicht einen Platz auf der Zeitachse.
- Bestehende Farbsprache (aus `task-card.blade.php` / `agenda-entry.blade.php`, wird 1:1 übernommen):
  Deadline (hart) = **contour**, Wunschtermin (weich) = neutral/`ink-faint`, überfällig = **signal**,
  Hausaufgabe = **forest**, Prüfung = **overprint**.

## Entschieden mit dem User
1. **Vorschau nur für harte Termine** — Deadline, Hausaufgabe, Prüfung. Wunschtermin (weich) erscheint nur am
   eigenen Tag, nie als Vorschau.
2. **Klick = direkt abhaken** (Task `toggleComplete` / Agenda `toggleDoneFor`). Ein kleiner Pfeil daneben
   springt zur Quelle (Board bzw. Agenda-Seite) — kein Deep-Link auf den einzelnen Eintrag.
3. **Erledigtes wird ausgeblendet**, konsistent mit Board/Agenda.

## Produkt

Neue "Ganztags-Zone" oberhalb des Stunden-Rasters, in beiden Ansichten (Desktop-Wochen-Raster, Mobile-Tagesansicht):
pro Tag eine kleine Liste von Chips — Titel + farbiger Punkt je Typ, Checkbox links, Pfeil-Icon rechts (öffnet
Board/Agenda). Vorschau-Chips (N Tage vorher) sind gestrichelt umrandet und tragen ein kleines "in Nd"-Label
(Tooltip: "in N Tagen fällig"). Mehr als 2 Einträge an einem Tag → "+N weitere" (Alpine-Disclosure, kein
Round-Trip), gleiches Muster wie an anderen Stellen der App (Notfallmodus, Bastelideen). Mockup wurde dem User
gezeigt und bestätigt.

## Umsetzung

### Datenmodell
- Migration: `users.deadline_preview_enabled` (bool, default **true**) + `users.deadline_preview_days`
  (unsigned tinyint, default **2**).
- `User`: Fillable-Attribut, Cast, `$attributes` Default `true` für `deadline_preview_enabled` (gleiches Muster
  wie `show_presence`, wegen des bekannten "fresh model"-Gotchas, siehe CLAUDE.md §10).

### Settings
Neue Karte unter "Zeitplan & Fokus" (`App\Livewire\Settings`): Toggle "Vorschau aktivieren" + Zahlenfeld "Tage
vorher" (min 0, max 14), gemeinsam gespeichert über `saveDeadlinePreview()` — gleiches Formular-Muster wie die
Pomodoro-Karte.

### `App\Livewire\Schedule`
- Neue `#[Computed] deadlineItems()`: liest aktive `Task`s mit `deadline`/`due_date` sowie für den User sichtbare,
  offene `AgendaEntry`s, baut je Eintrag 1 (Ist-Tag) oder 2 Datensätze (Ist-Tag + Vorschau-Tag, nur bei harten
  Terminen und aktivierter Vorschau), gruppiert nach Datum, gefiltert auf die sichtbare Woche.
- Neue Actions `toggleDeadlineTaskDone(int $id)` / `toggleDeadlineAgendaDone(int $id)`, beide über die
  Owner-/Sichtbarkeits-Relation aufgelöst (kein Vertrauen auf die id allein).

### Views
- Neue Partials `partials/schedule-deadline-strip.blade.php` (ein Tag) und
  `partials/schedule-deadline-item.blade.php` (ein Chip), in `schedule.blade.php` sowohl im Desktop- als auch
  im Mobile-Zweig eingebunden.

### Tests
`ScheduleDeadlineItemsTest.php`: Anzeige am eigenen Tag, Vorschau N Tage vorher, keine Vorschau bei
deaktiviertem Setting, benutzerdefiniertes N, Wunschtermin bekommt nie eine Vorschau, erledigte Einträge werden
ausgeblendet, Toggle-Actions sind owner-/sichtbarkeits-scoped, Settings-Validierung.

### Bewusst außerhalb des Umfangs
Die "Vorbereitung für morgen"-Ansicht (`/app/prepare`, Schritt 3) hat ein eigenes, kleineres Tages-Zeitfenster
und würde eine eigene Verdrahtung derselben Logik brauchen — nicht Teil dieser Anfrage, wird im Abschlussbericht
als mögliche spätere Erweiterung erwähnt.
